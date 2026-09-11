<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class MarkStaticGuideConsumersBound extends Migration
{
    private const SEED = 'g4-c1-a1';

    public function up(): void { $this->apply(true); }
    public function down(): void { $this->apply(false); }

    private function apply(bool $bound): void
    {
        $guides = $this->guides();
        if (count($guides) !== 47) throw new RuntimeException('Static guide binding count invalid.');
        $this->db->beginTransaction();
        try {
            foreach ($guides as $item) $this->updateItem($item, $bound);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function updateItem(array $item, bool $bound): void
    {
        $key = (string) $item['key'];
        $mode = (string) $item['render_mode'];

        $definitionQuery = $this->db->prepare('SELECT id, metadata_json FROM ui_content_definitions WHERE content_key = ? LIMIT 1 FOR UPDATE');
        $definitionQuery->execute([$key]);
        $definition = $definitionQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($definition)) throw new RuntimeException('Definition missing: ' . $key);
        $metadata = $this->metadata($definition['metadata_json'] ?? null);
        $this->assertOwnership($key, $mode, $metadata);
        $metadata['consumer_bound'] = $bound;
        $update = $this->db->prepare('UPDATE ui_content_definitions SET metadata_json = ? WHERE id = ?');
        $update->execute([json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $definition['id']]);

        $scopeKey = 'scope:' . (string) $item['module'] . ':surface:' . (string) $item['surface'];
        $overrideQuery = $this->db->prepare("SELECT id, metadata_json FROM ui_content_overrides WHERE definition_id = ? AND scope_key = ? AND locale = 'fa' LIMIT 1 FOR UPDATE");
        $overrideQuery->execute([(int) $definition['id'], $scopeKey]);
        $override = $overrideQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($override)) throw new RuntimeException('Override missing: ' . $key);
        $overrideMetadata = $this->metadata($override['metadata_json'] ?? null);
        $this->assertOwnership($key, $mode, $overrideMetadata);
        $overrideMetadata['consumer_bound'] = $bound;
        $updateOverride = $this->db->prepare('UPDATE ui_content_overrides SET metadata_json = ? WHERE id = ?');
        $updateOverride->execute([json_encode($overrideMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $override['id']]);
    }

    private function guides(): array
    {
        $path = dirname(__DIR__, 3) . '/resources/ui-content/platform-guides.json';
        $catalog = json_decode((string) file_get_contents($path), true);
        if (!is_array($catalog)) throw new RuntimeException('Catalog invalid.');
        $guides = array_values(array_filter($catalog, static fn(mixed $item): bool => is_array($item) && ($item['content_type'] ?? null) === 'guide'));
        foreach ($guides as $item) {
            if (($item['consumer_bound'] ?? false) !== true) throw new RuntimeException('Catalog guide not bound: ' . ($item['key'] ?? ''));
        }
        return $guides;
    }

    private function assertOwnership(string $key, string $mode, array $metadata): void
    {
        if (($metadata['seed'] ?? null) !== self::SEED) throw new RuntimeException('Ownership mismatch: ' . $key);
        if (($metadata['render_mode'] ?? null) !== $mode) throw new RuntimeException('Render mode mismatch: ' . $key);
    }

    private function metadata(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) throw new RuntimeException('Metadata invalid.');
        return $decoded;
    }
}
