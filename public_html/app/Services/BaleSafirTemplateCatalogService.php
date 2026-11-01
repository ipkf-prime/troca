<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Bale Safir template catalog. No template names or text-field names are coded
 * into the transport layer: every placeholder has a configurable Excel mapping.
 *
 * Each template references an externally approved Safir service template.
 * The catalog never creates/approves provider templates on Safir's behalf.
 */
final class BaleSafirTemplateCatalogService
{
    private const MAX_TEMPLATES_DEFAULT = 200;

    public function all(): array
    {
        return $this->load();
    }

    public function get(string $code): ?array
    {
        return $this->load()[$code] ?? null;
    }

    public function save(array $input): array
    {
        $code = trim((string) ($input['code'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $botId = trim((string) ($input['bot_id'] ?? ''));
        $templateId = trim((string) ($input['template_id'] ?? ''));
        $phoneColumn = $this->cleanColumn((string) ($input['phone_column'] ?? ''));
        $messageType = trim((string) ($input['message_type'] ?? ''));
        $price = trim((string) ($input['unit_price_irr'] ?? ''));
        $providerDate = trim((string) ($input['provider_created_at'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        $approved = (string) ($input['provider_approved'] ?? '0') === '1';

        if (!preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $code)
            || $title === '' || mb_strlen($title, 'UTF-8') > 160
            || !preg_match('/^[1-9][0-9]{5,19}$/D', $botId)
            || !preg_match('/^[A-Za-z0-9_-]{2,64}$/D', $templateId)
            || mb_strlen($messageType, 'UTF-8') > 80
            || !preg_match('/^[0-9]{0,12}$/D', $price)
            || mb_strlen($providerDate, 'UTF-8') > 40
            || $phoneColumn === ''
            || $body === '' || mb_strlen($body, 'UTF-8') > 4000
        ) {
            throw new RuntimeException('safir_template_invalid');
        }

        $variables = self::placeholders($body);
        if (count($variables) > 100) {
            throw new RuntimeException('safir_template_variables_invalid');
        }

        $variableItems = $input['variable_items'] ?? null;
        $labels = [];
        $mappings = [];

        if (is_array($variableItems)) {
            if (count($variableItems) < 1 || count($variableItems) > 100) {
                throw new RuntimeException('safir_template_variables_invalid');
            }
            foreach ($variableItems as $item) {
                if (!is_array($item)) {
                    throw new RuntimeException('safir_template_mapping_invalid');
                }
                $key = trim((string) ($item['key_en'] ?? ''));
                $label = trim((string) ($item['label_fa'] ?? ''));
                $source = trim((string) ($item['excel_column'] ?? ''));
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D', $key)
                    || $label === '' || mb_strlen($label, 'UTF-8') > 120
                    || $source === '' || isset($mappings[$key])
                ) {
                    throw new RuntimeException('safir_template_mapping_invalid');
                }
                $columns = array_values(array_filter(array_map(
                    fn (string $part): string => $this->cleanColumn($part),
                    explode('+', $source)
                ), static fn (string $value): bool => $value !== ''));
                if ($columns === [] || count($columns) > 8) {
                    throw new RuntimeException('safir_template_mapping_invalid');
                }
                $mappings[$key] = $columns;
                $labels[$key] = $label;
            }
            $declared = array_keys($mappings);
            sort($declared, SORT_STRING);
            $used = $variables;
            sort($used, SORT_STRING);
            if ($used !== $declared) {
                throw new RuntimeException('safir_template_variables_mismatch');
            }
        } else {
            // Backward-compatible path for existing forms and stored templates.
            if (count($variables) > 50) {
                throw new RuntimeException('safir_template_variables_invalid');
            }
            $rawMapping = $input['mappings'] ?? [];
            if (!is_array($rawMapping)) {
                throw new RuntimeException('safir_template_mapping_invalid');
            }
            foreach ($variables as $variable) {
                $raw = trim((string) ($rawMapping[$variable] ?? ''));
                $columns = array_values(array_filter(array_map(
                    fn (string $part): string => $this->cleanColumn($part),
                    explode('+', $raw)
                ), static fn (string $column): bool => $column !== ''));
                if ($columns === [] || count($columns) > 8) {
                    throw new RuntimeException('safir_template_mapping_invalid');
                }
                $mappings[$variable] = $columns;
            }
        }
        $lock = $this->lock();
        try {
            $templates = $this->load();
            $limit = max(10, min(10000, (int) \IPKF\Support\Env::get('BALE_SAFIR_MAX_TEMPLATES', self::MAX_TEMPLATES_DEFAULT)));
            if (!isset($templates[$code]) && count($templates) >= $limit) {
                throw new RuntimeException('safir_catalog_full');
            }
            $existing = $templates[$code] ?? null;
            $templates[$code] = [
                'code' => $code,
                'title' => $title,
                'bot_id' => $botId,
                'template_id' => $templateId,
                'phone_column' => $phoneColumn,
                'message_type' => array_key_exists('message_type', $input) ? $messageType : (string) ($existing['message_type'] ?? ''),
                'unit_price_irr' => array_key_exists('unit_price_irr', $input) ? $price : (string) ($existing['unit_price_irr'] ?? ''),
                'provider_created_at' => array_key_exists('provider_created_at', $input) ? $providerDate : (string) ($existing['provider_created_at'] ?? ''),
                'body' => $body,
                'mappings' => $mappings,
                'variable_labels' => $labels !== [] ? $labels : (array) ($existing['variable_labels'] ?? []),
                'active' => (bool) ($existing['active'] ?? true),
                'provider_approved' => $approved,
                'updated_at' => gmdate('c'),
            ];
            $this->persist($templates);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $templates[$code];
    }

    public function setActive(string $code, bool $active): void
    {
        $lock = $this->lock();
        try {
            $templates = $this->load();
            if (!isset($templates[$code])) {
                throw new RuntimeException('safir_template_missing');
            }
            $templates[$code]['active'] = $active;
            $templates[$code]['updated_at'] = gmdate('c');
            $this->persist($templates);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function placeholders(string $body): array
    {
        preg_match_all('/\{\{([^{}]*)\}\}/u', $body, $all);
        preg_match_all('/\{\{([\p{L}_][\p{L}\p{N}_]*)\}\}/u', $body, $valid);
        if (count($all[0]) !== count($valid[0]) ||
            preg_match('/\{\{|\}\}/u', str_replace($valid[0], '', $body))) {
            throw new RuntimeException('safir_template_variables_invalid');
        }
        return array_values(array_unique($valid[1]));
    }

    private function cleanColumn(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', str_replace(
            ["ي", "ك", "\u{FEFF}"], ['ی', 'ک', ''], $value
        )) ?? '');
        if (mb_strlen($value, 'UTF-8') > 120 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new RuntimeException('safir_template_mapping_invalid');
        }
        return $value;
    }

    private function catalogPath(): ?string
    {
        $path = trim((string) \IPKF\Support\Env::get('BALE_SAFIR_CATALOG_PATH', ''));
        if ($path === '') {
            return null;
        }
        if (!str_starts_with($path, '/')
            || str_contains($path, "\0")
            || str_contains($path, '/../')
            || str_ends_with($path, '/..')
            || !str_ends_with($path, '.json')
        ) {
            throw new RuntimeException('safir_catalog_path_invalid');
        }
        return $path;
    }

    private function initialPath(): string
    {
        return BASE_PATH . '/resources/ui-content/bale-safir-templates.json';
    }

    private function load(): array
    {
        $configured = $this->catalogPath();
        $path = is_string($configured) && is_readable($configured) ? $configured : $this->initialPath();
        if (!is_readable($path)) {
            throw new RuntimeException('safir_catalog_missing');
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || !is_array($decoded['templates'] ?? null)) {
            throw new RuntimeException('safir_catalog_invalid');
        }
        $indexed = [];
        foreach ($decoded['templates'] as $template) {
            if (!is_array($template)) {
                continue;
            }
            $key = (string) ($template['code'] ?? '');
            if ($key !== '' && preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $key)) {
                $indexed[$key] = $template;
            }
        }
        return $indexed;
    }

    private function lock()
    {
        $path = $this->catalogPath();
        if ($path === null) {
            throw new RuntimeException('safir_catalog_path_missing');
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('safir_storage_unavailable');
        }
        $lock = fopen($directory . '/.bale_safir_templates.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('safir_storage_unavailable');
        }
        return $lock;
    }

    private function persist(array $templates): void
    {
        $path = $this->catalogPath();
        if ($path === null) {
            throw new RuntimeException('safir_catalog_path_missing');
        }
        $directory = dirname($path);
        $tmp = tempnam($directory, 'safir_');
        if (!is_string($tmp)) {
            throw new RuntimeException('safir_storage_unavailable');
        }
        try {
            $json = json_encode(
                ['version' => 1, 'templates' => array_values($templates)],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            if (file_put_contents($tmp, $json, LOCK_EX) === false
                || !chmod($tmp, 0600)
                || !rename($tmp, $path)
            ) {
                throw new RuntimeException('safir_storage_unavailable');
            }
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
