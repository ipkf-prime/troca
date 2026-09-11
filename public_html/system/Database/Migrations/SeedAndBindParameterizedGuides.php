<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

/**
 * G4-C1-A2.3-S1
 *
 * Ensures the two parameterized guides exist in databases that were
 * already seeded before these catalog rows were introduced.
 *
 * Fresh installations may already have them through SeedPlatformGuideCatalog;
 * in that case this migration validates ownership and leaves content intact.
 */
final class SeedAndBindParameterizedGuides extends Migration
{
    private const SEED = 'g4-c1-a1';

    public function up(): void
    {
        $items = $this->items();
        if (count($items) !== 2) throw new RuntimeException('Parameterized guide count invalid.');

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                $definitionId = $this->ensureDefinition($item);
                $this->ensureOverride($definitionId, $item);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive for normal migration rollback.
         * These rows become administrator-managed immediately after seeding.
         * The controlled deployment harness has a baseline-aware rollback
         * that removes them only when this run created them from an absent baseline.
         */
    }

    private function items(): array
    {
        $path = dirname(__DIR__, 3) . '/resources/ui-content/platform-guides.json';
        $catalog = json_decode((string) file_get_contents($path), true);
        if (!is_array($catalog)) throw new RuntimeException('Catalog invalid.');

        $items = array_values(array_filter(
            $catalog,
            static fn (mixed $item): bool =>
                is_array($item)
                && ($item['content_type'] ?? null) === 'guide'
                && ($item['parameterized'] ?? false) === true
        ));

        foreach ($items as $item) $this->validateItem($item);
        return $items;
    }

    private function validateItem(array $item): void
    {
        $key = (string) ($item['key'] ?? '');
        $parameters = $item['template_parameters'] ?? null;

        if (!in_array($key, ['core.register.guide.02', 'core.register-verify.guide.03'], true)) {
            throw new RuntimeException('Unexpected parameterized guide: ' . $key);
        }

        if (($item['consumer_bound'] ?? false) !== true) {
            throw new RuntimeException('Parameterized guide not consumer-bound: ' . $key);
        }

        if (!is_array($parameters) || count($parameters) !== 1) {
            throw new RuntimeException('Template parameter contract invalid: ' . $key);
        }

        $expected = $key === 'core.register.guide.02' ? 'mobile' : 'seconds';
        if ((string) $parameters[0] !== $expected) {
            throw new RuntimeException('Template parameter mismatch: ' . $key);
        }

        $body = (string) ($item['body'] ?? '');
        if (substr_count($body, '{{' . $expected . '}}') !== 1) {
            throw new RuntimeException('Template placeholder mismatch: ' . $key);
        }
    }

    private function ensureDefinition(array $item): int
    {
        $key = (string) $item['key'];
        $query = $this->db->prepare(
            'SELECT id, content_type, description, metadata_json FROM ui_content_definitions WHERE content_key = ? LIMIT 1 FOR UPDATE'
        );
        $query->execute([$key]);
        $row = $query->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $metadata = $this->metadata($row['metadata_json'] ?? null);
            if ((string) ($row['content_type'] ?? '') !== 'guide') throw new RuntimeException('Definition type mismatch: ' . $key);
            if (($metadata['seed'] ?? null) !== self::SEED) throw new RuntimeException('Definition ownership mismatch: ' . $key);
            if (($metadata['parameterized'] ?? false) !== true) throw new RuntimeException('Definition parameterized metadata missing: ' . $key);
            return (int) $row['id'];
        }

        $reference = 'UICD-' . strtoupper(substr(hash('sha256', 'definition|' . $key), 0, 24));
        $metadata = $this->metadataFor($item, true);
        $title = $this->itemTitle($item);

        $insert = $this->db->prepare(
            "INSERT INTO ui_content_definitions
             (public_reference, content_key, content_type, default_locale, http_status, description, metadata_json, is_active)
             VALUES (?, ?, 'guide', 'fa', NULL, ?, ?, 1)"
        );
        $insert->execute([
            $reference,
            $key,
            $title,
            json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $id = (int) $this->db->lastInsertId();
        if ($id < 1) {
            $query->execute([$key]);
            $id = (int) $query->fetchColumn();
        }
        if ($id < 1) throw new RuntimeException('Unable to create definition: ' . $key);
        return $id;
    }

    private function ensureOverride(int $definitionId, array $item): void
    {
        $key = (string) $item['key'];
        $module = (string) $item['module'];
        $surface = (string) $item['surface'];
        $scopeKey = 'scope:' . $module . ':surface:' . $surface;

        $query = $this->db->prepare(
            "SELECT id, metadata_json FROM ui_content_overrides
             WHERE definition_id = ? AND scope_key = ? AND locale = 'fa'
             LIMIT 1 FOR UPDATE"
        );
        $query->execute([$definitionId, $scopeKey]);
        $row = $query->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $metadata = $this->metadata($row['metadata_json'] ?? null);
            if (($metadata['seed'] ?? null) !== self::SEED) throw new RuntimeException('Override ownership mismatch: ' . $key);
            if (($metadata['parameterized'] ?? false) !== true) throw new RuntimeException('Override parameterized metadata missing: ' . $key);
            return;
        }

        $reference = 'UICO-' . strtoupper(substr(hash('sha256', 'override|' . $key . '|' . $scopeKey . '|fa'), 0, 24));
        $scopePath = [['type' => 'surface', 'reference' => $surface]];
        $metadata = $this->metadataFor($item, false);

        $insert = $this->db->prepare(
            "INSERT INTO ui_content_overrides
             (public_reference, definition_id, scope_type, scope_key, module_key, scope_reference, scope_path_json,
              locale, title, body, icon_code, severity_code, layout_variant, visibility_mode,
              primary_action_code, primary_action_label, secondary_action_code, secondary_action_label,
              metadata_json, is_active)
             VALUES (?, ?, 'fine', ?, ?, ?, ?, 'fa', ?, ?, NULL, NULL, NULL, 'show', NULL, NULL, NULL, NULL, ?, 1)"
        );

        $insert->execute([
            $reference,
            $definitionId,
            $scopeKey,
            $module,
            $surface,
            json_encode($scopePath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $this->itemTitle($item),
            (string) $item['body'],
            json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function metadataFor(array $item, bool $definition): array
    {
        $metadata = [
            'seed' => self::SEED,
            'source_file' => (string) ($item['source_file'] ?? ''),
            'source_line' => (int) ($item['source_line'] ?? 0),
            'fingerprint' => (string) ($item['fingerprint'] ?? ''),
            'render_mode' => (string) ($item['render_mode'] ?? 'body'),
            'consumer_bound' => true,
            'parameterized' => true,
            'template_parameters' => array_values($item['template_parameters'] ?? []),
        ];

        if ($definition) {
            $metadata['module'] = (string) ($item['module'] ?? '');
            $metadata['surface'] = (string) ($item['surface'] ?? '');
        }

        return $metadata;
    }

    private function itemTitle(array $item): string
    {
        $title = trim((string) ($item['title'] ?? ''));
        if ($title !== '') return $title;

        $body = trim((string) ($item['body'] ?? ''));
        $characters = preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters) || count($characters) <= 72) return $body;
        return implode('', array_slice($characters, 0, 72)) . '…';
    }

    private function metadata(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) throw new RuntimeException('Metadata invalid.');
        return $decoded;
    }
}
