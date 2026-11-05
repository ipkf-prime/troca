<?php
declare(strict_types=1);

namespace App\Services;

use IPKF\Support\Env;
use IPKF\Support\Session;
use RuntimeException;
use ZipArchive;

final class BaleSafirCampaignService
{
    private const SESSION_KEY = 'ipkf_bale_safir_campaign_v1';
    private const MAX_UPLOAD_BYTES = 2097152;
    private const MAX_UNCOMPRESSED_BYTES = 4194304;
    private const MAX_ROWS_DEFAULT = 5000;

    public function page(int $userId, string $editingCode = ''): array
    {
        $catalog = new BaleSafirTemplateCatalogService();
        $templates = $catalog->all();
        $campaign = $this->campaign($userId);
        $editTemplate = $templates[$editingCode] ?? null;

        $counts = ['ready' => 0, 'sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0];
        foreach (($campaign['rows'] ?? []) as $row) {
            $status = (string) ($row['status'] ?? 'skipped');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return [
            'templates' => array_values($templates),
            'edit_template' => $editTemplate,
            'campaign' => $campaign,
            'counts' => $counts,
            'send_enabled' => $this->sendEnabled(),
            'pilot_mode' => $this->pilotEnabled(),
            'test_phone_masked' => $this->maskPhone((string) Env::get('BALE_SAFIR_TEST_PHONE', '')),
            'credential_ready' => trim((string) Env::get('BALE_SAFIR_API_ACCESS_KEY', '')) !== '',
            'manual_test_history' => ($editingCode !== '' && is_array($editTemplate))
                ? (new BaleSafirTestReportService())->recent(10, $editingCode) : [],
            'manual_test_enabled' => filter_var(Env::get('BALE_SAFIR_TEST_SEND_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
                && (strtolower((string) Env::get('APP_ENV', 'production')) !== 'production'
                    || filter_var(Env::get('BALE_SAFIR_TEST_PRODUCTION_APPROVED', false), FILTER_VALIDATE_BOOLEAN))
                && trim((string) Env::get('BALE_SAFIR_API_ACCESS_KEY', '')) !== ''
                && $this->normalizePhone((string) Env::get('BALE_SAFIR_TEST_PHONE', '')) !== null,

        ];
    }

    public function saveTemplate(int $userId, array $input): void
    {
        (new BaleSafirTemplateCatalogService())->save($input);
        // Catalog changes do not silently change the template snapshot of an existing
        // campaign. requireCampaign() enforces version equality before any send.
    }

    public function toggleTemplate(int $userId, string $code, bool $active): void
    {
        (new BaleSafirTemplateCatalogService())->setActive($code, $active);
    }

    public function import(int $userId, array $file, string $templateCode): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null)
            || !is_uploaded_file($file['tmp_name'])
            || (int) ($file['size'] ?? 0) <= 0
            || (int) ($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES
            || !preg_match('/\.xlsx$/i', (string) ($file['name'] ?? ''))
        ) {
            throw new RuntimeException('safir_invalid_upload');
        }

        $template = (new BaleSafirTemplateCatalogService())->get($templateCode);
        if (!is_array($template) || empty($template['active'])) {
            throw new RuntimeException('safir_template_unavailable');
        }
        $rows = $this->parseWorkbook((string) $file['tmp_name']);
        $needed = [(string) $template['phone_column']];
        foreach ($template['mappings'] as $columns) {
            $needed = array_merge($needed, $columns);
        }
        $headers = array_keys($rows[0]['columns']);
        foreach (array_unique($needed) as $column) {
            if (!in_array($column, $headers, true)) {
                throw new RuntimeException('safir_xlsx_mapping_missing');
            }
        }
        $items = [];
        $seen = [];
        foreach ($rows as $sourceRow) {
            $values = $sourceRow['columns'];
            $phone = $this->normalizePhone((string) ($values[$template['phone_column']] ?? ''));
            $fields = [];
            $replacements = [];
            $invalid = false;
            foreach ($template['mappings'] as $variable => $mappedColumns) {
                $parts = array_map(
                    fn (string $column): string => trim($this->normalizeText((string) ($values[$column] ?? ''))),
                    $mappedColumns
                );
                if (in_array('', $parts, true)) {
                    $invalid = true;
                }
                $value = implode(' ', $parts);
                if ($value === '' || mb_strlen($value, 'UTF-8') > 500) {
                    $invalid = true;
                }
                $fields[$variable] = $value;
                $replacements['{{' . $variable . '}}'] = $value;
            }
            $status = 'ready';
            $error = '';
            if ($invalid || $phone === null) {
                $status = 'skipped';
                $error = 'شماره یا اطلاعات موردنیاز قالب ناقص یا نامعتبر است';
            } elseif (isset($seen[$phone])) {
                $status = 'skipped';
                $error = 'شماره تکراری';
            } else {
                $seen[$phone] = true;
            }
            $items[] = [
                'row' => (int) $sourceRow['row'],
                'fields' => $fields,
                'phone' => $phone ?? '',
                'status' => $status,
                'error' => $error,
                'message_id' => '',
                'http_code' => '',
                'attempted_at' => '',
                'preview' => strtr($template['body'], $replacements),
            ];
        }
        $campaign = [
            'actor_user_id' => $userId,
            'reference' => bin2hex(random_bytes(12)),
            'template_code' => $templateCode,
            'template_title' => $template['title'],
            'template_variables' => array_keys($template['mappings']),
            'template_hash' => hash('sha256', json_encode($template, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'created_at' => gmdate('c'),
            'rows' => $items,
            'test_status' => '',
            'test_message_id' => '',
            'last_error' => '',
        ];
        Session::put(self::SESSION_KEY, $campaign);
        return $campaign;
    }

    public function reset(int $userId): void
    {
        $this->campaign($userId);
        Session::forget(self::SESSION_KEY);
    }

    public function test(int $userId): string
    {
        $campaign = $this->requireCampaign($userId);
        $this->assertSendEnabled();
        if (in_array((string) ($campaign['test_status'] ?? ''), ['unknown', 'sent'], true)) {
            throw new RuntimeException('safir_test_already_attempted');
        }
        $allow = $this->normalizePhone((string) Env::get('BALE_SAFIR_TEST_PHONE', ''));
        if ($allow === null) {
            throw new RuntimeException('safir_test_allowlist_missing');
        }
        $first = null;
        foreach ($campaign['rows'] as $row) {
            if ($row['status'] === 'ready') {
                $first = $row;
                break;
            }
        }
        if ($first === null) {
            throw new RuntimeException('safir_no_ready_rows');
        }
        // Mark unknown before network I/O; no silent duplicate retries.
        $campaign['test_status'] = 'unknown';
        $campaign['test_message_id'] = '';
        Session::put(self::SESSION_KEY, $campaign);
        $result = $this->sendOne($allow, $first, 'test-' . $campaign['reference']);
        $campaign['test_status'] = $result['status'];
        $campaign['test_message_id'] = $result['message_id'];
        $campaign['last_error'] = $result['error'];
        Session::put(self::SESSION_KEY, $campaign);
        return $result['status'];
    }

    /**
     * Manual one-recipient test for an already saved, externally approved template.
     * This is separate from the bulk-send enablement and always restricted to
     * BALE_SAFIR_TEST_PHONE. The default configuration never sends.
     */
    public function manualTest(
        int $userId,
        string $templateCode,
        array $inputFields,
        string $nonce
    ): array {
        $testAllowed = filter_var(
            Env::get('BALE_SAFIR_TEST_SEND_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        );
        $production = strtolower((string) Env::get('APP_ENV', 'production')) === 'production';
        $approved = filter_var(
            Env::get('BALE_SAFIR_TEST_PRODUCTION_APPROVED', false),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$testAllowed || ($production && !$approved)) {
            throw new RuntimeException('safir_manual_test_disabled');
        }
        if (trim((string) Env::get('BALE_SAFIR_API_ACCESS_KEY', '')) === '') {
            throw new RuntimeException('safir_key_missing');
        }
        if (!preg_match('/^[a-f0-9]{32}$/D', $nonce)) {
            throw new RuntimeException('safir_test_nonce_invalid');
        }
        // Server-owned recipient: the browser cannot select or override the
        // destination of a real manual test, including through crafted POSTs.
        $allowedPhone = $this->normalizePhone((string) Env::get('BALE_SAFIR_TEST_PHONE', ''));
        if ($allowedPhone === null) {
            throw new RuntimeException('safir_test_phone_not_configured');
        }
        $template = (new BaleSafirTemplateCatalogService())->get($templateCode);
        if (!is_array($template) || empty($template['active']) || empty($template['provider_approved'])) {
            throw new RuntimeException('safir_template_unavailable');
        }
        $fields = $this->validateManualFields($template, $inputFields);

        // Fail closed unless a persistent, private audit directory exists.
        // No directory creation or configuration changes are performed here.
        $root = trim((string) Env::get('BALE_SAFIR_TEST_ATTEMPT_DIR', ''));
        $catalogPath = trim((string) Env::get('BALE_SAFIR_CATALOG_PATH', ''));
        $parent = $catalogPath !== '' ? realpath(dirname($catalogPath)) : false;
        $realRoot = $root !== '' && !is_link($root) ? realpath($root) : false;
        if ($parent === false || $realRoot === false
            || !str_starts_with($realRoot . '/', $parent . '/')
            || !is_dir($realRoot) || !is_writable($realRoot)) {
            throw new RuntimeException('safir_test_audit_unavailable');
        }
        $attemptFile = $realRoot . '/' . hash('sha256', $nonce) . '.json';
        $handle = @fopen($attemptFile, 'x');
        if ($handle === false) {
            throw new RuntimeException('safir_test_already_attempted');
        }
        chmod($attemptFile, 0600);
        $record = [
            'actor' => $userId,
            'template_code' => $templateCode,
            'template_hash' => hash('sha256', json_encode($template, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'fields_hash' => hash('sha256', json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'recipient_masked' => $this->maskPhone($allowedPhone),
            'attempted_at' => gmdate('c'),
            'request_id' => 'manual-test-' . $nonce,
            'status' => 'unknown',
            'http_code' => 0,
            'message_id' => '',
            'error_code' => 0,
            'error_description' => '',
        ];
        try {
            $this->writeTestAudit($handle, $record);
            // Once the unknown record is durable, an HTTP retry never
            // automatically repeats the send, even after a worker crash.
            $row = ['fields' => $fields];
            $result = $this->sendOne($allowedPhone, $row, 'manual-test-' . $nonce, $template);
            $record['status'] = $result['status'];
            $record['http_code'] = $result['http_code'];
            $record['message_id'] = $result['message_id'];
            $record['error_code'] = (int) ($result['error_code'] ?? 0);
            $record['error_description'] = (string) ($result['error_description'] ?? '');
            $record['finished_at'] = gmdate('c');
            $this->writeTestAudit($handle, $record);
            return $result;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Normalize exactly the saved template's declared keys; no extra values.
     */
    private function validateManualFields(array $template, array $inputFields): array
    {
        $expected = array_keys((array) ($template['mappings'] ?? []));
        if ($expected === [] || count($expected) > 100 || count($inputFields) !== count($expected)) {
            throw new RuntimeException('safir_template_variables_invalid');
        }
        $fields = [];
        foreach ($expected as $key) {
            if (!array_key_exists($key, $inputFields) || !is_string($inputFields[$key])) {
                throw new RuntimeException('safir_test_fields_invalid');
            }
            $value = trim($this->normalizeText($inputFields[$key]));
            if ($value === '' || mb_strlen($value, 'UTF-8') > 500) {
                throw new RuntimeException('safir_test_fields_invalid');
            }
            $fields[$key] = $value;
        }
        return $fields;
    }

    private function writeTestAudit($handle, array $record): void
    {
        $serialized = json_encode(
            $record,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        rewind($handle);
        if (!ftruncate($handle, 0)
            || fwrite($handle, $serialized . "\n") !== strlen($serialized . "\n")
            || !fflush($handle)
            || (function_exists('fsync') && !fsync($handle))) {
            throw new RuntimeException('safir_test_audit_unavailable');
        }
    }

    public function dispatch(int $userId, string $confirmation, string $reference): array
    {
        // Session-based dispatch is for the existing two-phone pilot ONLY.
        // Durable bulk must go through a migrated DB worker and idempotency ledger.
        if (!filter_var(Env::get('BALE_SAFIR_PILOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('safir_bulk_requires_durable_worker');
        }
        $this->assertSendEnabled();
        $campaign = $this->requireCampaign($userId);
        // Pilot mode takes precedence even if bulk flags were toggled by mistake.
        // Verify the WHOLE imported campaign before any provider call.
        if (filter_var(Env::get('BALE_SAFIR_PILOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->assertPilotCampaign($campaign);
        }
        if (!hash_equals((string) $campaign['reference'], $reference)
            || !hash_equals('SEND ' . $reference, $confirmation)
            || ($campaign['test_status'] ?? '') !== 'sent'
        ) {
            throw new RuntimeException('safir_confirmation_required');
        }
        $sent = 0;
        foreach ($campaign['rows'] as $index => $row) {
            if ($row['status'] !== 'ready') {
                continue;
            }
            if ($sent >= 3) {
                break;
            }
            $sent++;
            // Claim each row *before* network I/O; session lock serializes requests.
            $campaign['rows'][$index]['status'] = 'unknown';
            $campaign['rows'][$index]['attempted_at'] = gmdate('c');
            Session::put(self::SESSION_KEY, $campaign);
            try {
                $result = $this->sendOne($row['phone'], $row, 'campaign-' . $campaign['reference'] . '-' . $index);
                $campaign['rows'][$index]['status'] = $result['status'];
                $campaign['rows'][$index]['message_id'] = $result['message_id'];
                $campaign['rows'][$index]['http_code'] = (string) $result['http_code'];
                $campaign['rows'][$index]['error'] = $result['error'];
            } catch (\Throwable) {
                $campaign['rows'][$index]['status'] = 'unknown';
                $campaign['rows'][$index]['error'] = 'وضعیت ارسال نامشخص؛ نیازمند بررسی';
                Session::put(self::SESSION_KEY, $campaign);
                break;
            }
            Session::put(self::SESSION_KEY, $campaign);
            if ($result['status'] === 'sent' && $sent < 3) {
                $delayMs = max(500, min(10000, (int) Env::get('BALE_SAFIR_INTERVAL_MS', 1500)));
                usleep($delayMs * 1000);
            }
            if ($result['status'] !== 'sent') {
                // Stop on provider errors or uncertain delivery; don't automatically retry.
                break;
            }
        }
        return $this->page($userId)['counts'];
    }

    public function export(int $userId): string
    {
        $campaign = $this->campaign($userId);
        if (!is_array($campaign)) {
            throw new RuntimeException('safir_campaign_missing');
        }
        $variables = $campaign['template_variables'] ?? [];
        $stream = fopen('php://temp', 'w+');
        if (!$stream) {
            throw new RuntimeException('safir_export_failed');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_merge(['ردیف', 'شناسه قالب', 'شماره همراه'],
            $variables, ['وضعیت', 'خطا', 'شناسه پیام', 'کد HTTP', 'زمان']));
        foreach ($campaign['rows'] as $row) {
            $values = [$row['row'], $campaign['template_code'], $row['phone']];
            foreach ($variables as $variable) {
                $values[] = (string) ($row['fields'][$variable] ?? '');
            }
            $values = array_merge($values, [$row['status'], $row['error'],
                $row['message_id'], $row['http_code'], $row['attempted_at']]);
            fputcsv($stream, array_map(
                static function ($value): string {
                    $value = (string) $value;
                    return preg_match('/^[=\+\-@\t\r]/u', $value) ? "'" . $value : $value;
                },
                $values
            ));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return is_string($csv) ? $csv : '';
    }

    private function campaign(int $userId): ?array
    {
        $campaign = Session::get(self::SESSION_KEY);
        if (!is_array($campaign) || (int) ($campaign['actor_user_id'] ?? 0) !== $userId) {
            return null;
        }
        return $campaign;
    }

    private function requireCampaign(int $userId): array
    {
        $campaign = $this->campaign($userId);
        if ($campaign === null) {
            throw new RuntimeException('safir_campaign_missing');
        }
        $template = (new BaleSafirTemplateCatalogService())->get((string) $campaign['template_code']);
        if (!is_array($template) || empty($template['active']) || empty($template['provider_approved'])) {
            throw new RuntimeException('safir_template_unavailable');
        }
        $hash = hash('sha256', json_encode($template, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (!hash_equals($hash, (string) $campaign['template_hash'])) {
            throw new RuntimeException('safir_template_changed');
        }
        return $campaign;
    }

    /**
     * A production pilot never enables the existing unrestricted bulk flags.
     * Configuration must contain exactly 2 or 3 DISTINCT canonical recipients.
     */
    private function pilotPhones(): array
    {
        $raw = trim((string) Env::get('BALE_SAFIR_PILOT_PHONES', ''));
        $phones = $raw === '' ? [] : explode(',', $raw);
        if (count($phones) < 2 || count($phones) > 3) {
            return [];
        }
        foreach ($phones as $phone) {
            if (!preg_match('/^989[0-9]{9}$/D', $phone)) {
                return [];
            }
        }
        return count(array_unique($phones)) === count($phones) ? $phones : [];
    }

    private function pilotEnabled(): bool
    {
        return filter_var(Env::get('BALE_SAFIR_PILOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
            && filter_var(Env::get('BALE_SAFIR_PILOT_PRODUCTION_APPROVED', false), FILTER_VALIDATE_BOOLEAN)
            && count($this->pilotPhones()) >= 2;
    }

    private function assertPilotCampaign(array $campaign): void
    {
        $rows = $campaign['rows'] ?? null;
        $phones = $this->pilotPhones();
        if (!is_array($rows) || count($rows) < 2 || count($rows) > 3
            || count($phones) < 2 || count($phones) > 3) {
            throw new RuntimeException('safir_pilot_list_not_allowed');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['phone'] ?? null)
                || !in_array((string) ($row['status'] ?? ''),
                    ['ready', 'sent', 'failed', 'unknown'], true)
                || !in_array($row['phone'], $phones, true)
                || isset($seen[$row['phone']])
                || $this->normalizePhone($row['phone']) !== $row['phone']) {
                throw new RuntimeException('safir_pilot_list_not_allowed');
            }
            $seen[$row['phone']] = true;
        }
    }

    private function sendEnabled(): bool
    {
        $enabled = filter_var(Env::get('BALE_SAFIR_SEND_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $production = strtolower((string) Env::get('APP_ENV', 'production')) === 'production';
        // A requested pilot overrides any bulk configuration, including
        // accidental activation of unrestricted production flags.
        if (filter_var(Env::get('BALE_SAFIR_PILOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return $this->pilotEnabled();
        }
        return $enabled && (!$production ||
            filter_var(Env::get('BALE_SAFIR_PRODUCTION_APPROVED', false), FILTER_VALIDATE_BOOLEAN));
    }

    private function assertSendEnabled(): void
    {
        if (!$this->sendEnabled()) {
            throw new RuntimeException('safir_send_disabled');
        }
        if (trim((string) Env::get('BALE_SAFIR_API_ACCESS_KEY', '')) === '') {
            throw new RuntimeException('safir_key_missing');
        }
    }

    private function payload(array $template, array $row, string $phone, string $requestId): array
    {
        // The Safir API contract is template-driven. Field names are exactly
        // those declared by the approved provider template; none are hardcoded.
        $expectedFields = array_keys($template['mappings'] ?? []);
        $fields = $row['fields'] ?? [];
        if (!is_array($fields) || array_keys($fields) !== $expectedFields) {
            throw new RuntimeException('safir_template_variables_invalid');
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{8,128}$/D', $requestId)) {
            throw new RuntimeException('safir_request_id_invalid');
        }
        return [
            'request_id' => $requestId,
            'bot_id' => (int) $template['bot_id'],
            'phone_number' => $phone,
            'message_data' => ['template_message' => [
                'template_id' => $template['template_id'],
                'text_fields' => (object) $fields,
            ]],
        ];
    }

    private function sendOne(string $phone, array $row, string $requestId, ?array $manualTemplate = null): array
    {
        $campaign = $manualTemplate === null ? Session::get(self::SESSION_KEY) : null;
        $template = $manualTemplate ?? (new BaleSafirTemplateCatalogService())->get((string) ($campaign['template_code'] ?? ''));
        if (!is_array($template) || empty($template['active']) || empty($template['provider_approved'])) {
            throw new RuntimeException('safir_template_unavailable');
        }
        $key = trim((string) Env::get('BALE_SAFIR_API_ACCESS_KEY', ''));
        if (!function_exists('curl_init')) {
            throw new RuntimeException('safir_curl_missing');
        }
        $payload = json_encode(
            $this->payload($template, $row, $phone, $requestId),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $curl = curl_init('https://safir.bale.ai/api/v3/send_message');
        if (!$curl) {
            throw new RuntimeException('safir_curl_missing');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'api-access-key: ' . $key,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }
        try {
            $raw = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($curl);
        }
        if (!is_string($raw)) {
            return ['status' => 'unknown', 'http_code' => $httpCode, 'message_id' => '',
                'error' => 'قطع ارتباط؛ وضعیت ارسال مشخص نیست'];
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['status' => 'unknown', 'http_code' => $httpCode, 'message_id' => '',
                'error' => 'پاسخ نامعتبر؛ وضعیت ارسال نامشخص'];
        }
        $errorData = $json['error_data'] ?? null;
        $firstError = is_array($errorData) ? ($errorData[0] ?? $errorData) : [];
        $errorCode = is_array($firstError) ? (int) ($firstError['code'] ?? 0) : 0;
        $errorDescription = is_array($firstError)
            ? mb_substr((string) ($firstError['description'] ?? ''), 0, 180, 'UTF-8') : '';
        $messageId = trim((string) ($json['message_id'] ?? ($json['data']['message_id'] ?? '')));
        $ok = $httpCode >= 200 && $httpCode < 300 && $messageId !== ''
            && ($errorData === null || $errorData === [] || $errorData === '');
        $error = $ok ? '' : (
            $errorData !== null && $errorData !== [] && $errorData !== ''
                ? mb_substr((string) json_encode($errorData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 0, 180, 'UTF-8')
                : 'پاسخ سرویس ارسال تأیید نشده است'
        );
        return [
            'status' => $ok ? 'sent' : ($httpCode >= 400 && $httpCode < 500 ? 'failed' : 'unknown'),
            'http_code' => $httpCode,
            'message_id' => $messageId,
            'error' => $error,
            'error_code' => $errorCode,
            'error_description' => $errorDescription,
        ];
    }

    private function parseWorkbook(string $path): array
    {
        if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
            throw new RuntimeException('safir_xlsx_extension_missing');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('safir_xlsx_invalid');
        }
        try {
            $maxRows = max(500, min(50000, (int) Env::get('BALE_SAFIR_MAX_IMPORT_ROWS', self::MAX_ROWS_DEFAULT)));
            if ($zip->numFiles > 150) {
                throw new RuntimeException('safir_xlsx_too_large');
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $total += (int) ($entry['size'] ?? 0);
                if ($total > 12 * 1024 * 1024) {
                    throw new RuntimeException('safir_xlsx_too_large');
                }
            }
            $read = static function (ZipArchive $zip, string $name): ?string {
                $stat = $zip->statName($name);
                if (!is_array($stat)) {
                    return null;
                }
                if (($stat['size'] ?? 0) > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('safir_xlsx_too_large');
                }
                $raw = $zip->getFromName($name);
                return is_string($raw) ? $raw : null;
            };
            $sharedXml = $read($zip, 'xl/sharedStrings.xml');
            $sheetXml = $read($zip, 'xl/worksheets/sheet1.xml');
            if ($sheetXml === null) {
                throw new RuntimeException('safir_xlsx_sheet_missing');
            }
            $shared = [];
            if ($sharedXml !== null) {
                $xml = @simplexml_load_string($sharedXml, \SimpleXMLElement::class, LIBXML_NONET);
                if (!$xml) {
                    throw new RuntimeException('safir_xlsx_invalid');
                }
                foreach ($xml->si as $si) {
                    $parts = $si->xpath('.//*[local-name()="t"]') ?: [];
                    $shared[] = implode('', array_map('strval', $parts));
                }
            }
            $xml = @simplexml_load_string($sheetXml, \SimpleXMLElement::class, LIBXML_NONET);
            if (!$xml) {
                throw new RuntimeException('safir_xlsx_invalid');
            }
            $table = [];
            foreach ($xml->sheetData->row as $row) {
                $number = (int) $row['r'];
                if ($number <= 0 || $number > $maxRows + 1) {
                    throw new RuntimeException('safir_xlsx_too_many_rows');
                }
                $cells = [];
                foreach ($row->c as $cell) {
                    if (isset($cell->f)) {
                        throw new RuntimeException('safir_xlsx_formulas_forbidden');
                    }
                    if (!preg_match('/^([A-Z]+)[0-9]+$/D', (string) $cell['r'], $matches)) {
                        continue;
                    }
                    $column = 0;
                    foreach (str_split($matches[1]) as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    if ($column > 256) {
                        throw new RuntimeException('safir_xlsx_too_many_columns');
                    }
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 's') {
                        $value = $shared[(int) $value] ?? '';
                    } elseif ((string) $cell['t'] === 'inlineStr') {
                        $parts = $cell->is->xpath('.//*[local-name()="t"]') ?: [];
                        $value = implode('', array_map('strval', $parts));
                    }
                    $cells[$column - 1] = $value;
                }
                $table[$number] = $cells;
            }
            if (!isset($table[1])) {
                throw new RuntimeException('safir_xlsx_header_missing');
            }
            $headers = [];
            foreach ($table[1] as $index => $label) {
                $normalized = trim(preg_replace(
                    '/\s+/u',
                    ' ',
                    $this->normalizeText((string) $label)
                ) ?? '');
                if ($normalized === '') {
                    continue;
                }
                if (in_array($normalized, $headers, true)) {
                    throw new RuntimeException('safir_xlsx_duplicate_header');
                }
                $headers[$index] = $normalized;
            }
            if ($headers === []) {
                throw new RuntimeException('safir_xlsx_header_missing');
            }
            $output = [];
            foreach ($table as $number => $cells) {
                if ($number === 1) {
                    continue;
                }
                $values = [];
                foreach ($headers as $index => $name) {
                    $values[$name] = trim((string) ($cells[$index] ?? ''));
                }
                if (implode('', $values) !== '') {
                    $output[] = ['row' => (int) $number, 'columns' => $values];
                }
            }
            if ($output === []) {
                throw new RuntimeException('safir_xlsx_empty');
            }
            return $output;
        } finally {
            $zip->close();
        }
    }

    private function normalizeText(string $text): string
    {
        return strtr($text, ['ي' => 'ی', 'ك' => 'ک', '‌' => ' ', "\u{FEFF}" => '']);
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = strtr($phone, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5',
            '۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2',
            '٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $phone = preg_replace('/[\s()+-]/u', '', trim($phone)) ?? '';
        if (preg_match('/^09[0-9]{9}$/D', $phone)) {
            return '98' . substr($phone, 1);
        }
        if (preg_match('/^989[0-9]{9}$/D', $phone)) {
            return $phone;
        }
        // Excel may store 0912... as a number and drop the leading zero.
        if (preg_match('/^9[0-9]{9}$/D', $phone)) {
            return '98' . $phone;
        }
        if (preg_match('/^00989[0-9]{9}$/D', $phone)) {
            return substr($phone, 2);
        }
        return null;
    }

    private function maskPhone(string $phone): string
    {
        $value = $this->normalizePhone($phone);
        return $value ? '98*****' . substr($value, -4) : 'تنظیم نشده';
    }
}
