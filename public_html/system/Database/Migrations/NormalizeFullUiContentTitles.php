<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

/**
 * G4-C1-A2.3-S2
 *
 * Replaces automatically-derived 72-character titles with the complete,
 * whitespace-normalized catalog body. Explicit catalog titles are preserved.
 */
final class NormalizeFullUiContentTitles extends Migration
{
    private const SEED = 'g4-c1-a1';

    public function up(): void
    {
        $items = $this->items();
        if (count($items) !== 28) throw new RuntimeException('Automatic title count invalid.');

        $long = array_filter(
            $items,
            fn (array $item): bool => mb_strlen($this->fullTitle((string) $item['body']), 'UTF-8') > 72
        );
        if (count($long) !== 16) throw new RuntimeException('Long automatic title count invalid.');

        $definitionQuery = $this->db->prepare(
            'SELECT id, metadata_json FROM ui_content_definitions WHERE content_key = ? LIMIT 1 FOR UPDATE'
        );
        $overrideQuery = $this->db->prepare(
            "SELECT id, metadata_json FROM ui_content_overrides WHERE definition_id = ? AND scope_key = ? AND locale = 'fa' LIMIT 1 FOR UPDATE"
        );
        $updateDefinition = $this->db->prepare(
            'UPDATE ui_content_definitions SET description = ? WHERE id = ?'
        );
        $updateOverride = $this->db->prepare(
            'UPDATE ui_content_overrides SET title = ? WHERE id = ?'
        );

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                $key = (string) $item['key'];
                $definitionQuery->execute([$key]);
                $definition = $definitionQuery->fetch(PDO::FETCH_ASSOC);
                if (!is_array($definition)) throw new RuntimeException('Definition missing: ' . $key);
                $this->assertOwnership($key, $definition['metadata_json'] ?? null, 'definition');

                $scope = 'scope:' . (string) $item['module'] . ':surface:' . (string) $item['surface'];
                $overrideQuery->execute([(int) $definition['id'], $scope]);
                $override = $overrideQuery->fetch(PDO::FETCH_ASSOC);
                if (!is_array($override)) throw new RuntimeException('Override missing: ' . $key);
                $this->assertOwnership($key, $override['metadata_json'] ?? null, 'override');

                $title = $this->fullTitle((string) $item['body']);
                if ($title === '') throw new RuntimeException('Full title empty: ' . $key);
                if (mb_strlen($title, 'UTF-8') > 500) throw new RuntimeException('Full title exceeds storage: ' . $key);

                $updateDefinition->execute([$title, (int) $definition['id']]);
                $updateOverride->execute([$title, (int) $override['id']]);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function down(): void
    {
        /* Intentionally non-destructive: truncated titles must not be restored. */
    }

    private function items(): array
    {
        $path = dirname(__DIR__, 3) . '/resources/ui-content/platform-guides.json';
        $catalog = json_decode((string) file_get_contents($path), true);
        if (!is_array($catalog) || count($catalog) !== 55) throw new RuntimeException('Catalog invalid.');

        return array_values(array_filter(
            $catalog,
            static fn (mixed $item): bool =>
                is_array($item)
                && trim((string) ($item['title'] ?? '')) === ''
        ));
    }

    private function fullTitle(string $body): string
    {
        return trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
    }

    private function assertOwnership(string $key, mixed $value, string $kind): void
    {
        $metadata = json_decode((string) $value, true);
        if (!is_array($metadata) || ($metadata['seed'] ?? null) !== self::SEED) {
            throw new RuntimeException(ucfirst($kind) . ' ownership mismatch: ' . $key);
        }
    }
}