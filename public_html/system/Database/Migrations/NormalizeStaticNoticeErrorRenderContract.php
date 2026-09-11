<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class NormalizeStaticNoticeErrorRenderContract extends Migration
{
    private const SEED = 'g4-c1-a1';

    public function up(): void { $this->apply(true); }
    public function down(): void { $this->apply(false); }

    private function apply(bool $forward): void
    {
        $items = $this->items();
        if (count($items) !== 6) throw new RuntimeException('Notice/error render-contract count invalid.');

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) $this->applyItem($item, $forward);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function applyItem(array $item, bool $forward): void
    {
        $key = (string) $item['key'];
        $mode = (string) $item['render_mode'];
        $newBody = trim((string) $item['body']);
        $legacyBody = trim((string) ($item['legacy_body'] ?? $newBody));
        $newTitle = trim((string) ($item['title'] ?? ''));
        if ($newTitle === '') $newTitle = $this->shortTitle($newBody);
        $legacyTitle = $this->shortTitle($legacyBody);

        $definitionQuery = $this->db->prepare(
            'SELECT id, content_type, description, metadata_json FROM ui_content_definitions WHERE content_key = ? LIMIT 1 FOR UPDATE'
        );
        $definitionQuery->execute([$key]);
        $definition = $definitionQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($definition)) throw new RuntimeException('Definition missing: ' . $key);
        if ((string) $definition['content_type'] !== (string) $item['content_type']) throw new RuntimeException('Definition type mismatch: ' . $key);

        $definitionMetadata = $this->metadata($definition['metadata_json'] ?? null);
        $this->assertSeedBaseline($key, $definitionMetadata, $forward);

        $scopeKey = 'scope:' . (string) $item['module'] . ':surface:' . (string) $item['surface'];
        $overrideQuery = $this->db->prepare(
            "SELECT id, title, body, metadata_json FROM ui_content_overrides WHERE definition_id = ? AND scope_key = ? AND locale = 'fa' LIMIT 1 FOR UPDATE"
        );
        $overrideQuery->execute([(int) $definition['id'], $scopeKey]);
        $override = $overrideQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($override)) throw new RuntimeException('Override missing: ' . $key);

        $overrideMetadata = $this->metadata($override['metadata_json'] ?? null);
        $this->assertSeedBaseline($key, $overrideMetadata, $forward);

        if ($forward) {
            if ((string) $definition['description'] !== $legacyTitle) throw new RuntimeException('Definition baseline changed: ' . $key);
            if ((string) $override['title'] !== $legacyTitle || (string) $override['body'] !== $legacyBody) {
                throw new RuntimeException('Override baseline changed: ' . $key);
            }

            $targetDefinitionTitle = $newTitle;
            $targetOverrideTitle = $newTitle;
            $targetBody = $newBody;
            $definitionMetadata['render_mode'] = $mode;
            $overrideMetadata['render_mode'] = $mode;
        } else {
            if ((string) $definition['description'] !== $newTitle) throw new RuntimeException('Definition normalized value changed: ' . $key);
            if ((string) $override['title'] !== $newTitle || (string) $override['body'] !== $newBody) {
                throw new RuntimeException('Override normalized value changed: ' . $key);
            }

            $targetDefinitionTitle = $legacyTitle;
            $targetOverrideTitle = $legacyTitle;
            $targetBody = $legacyBody;
            unset($definitionMetadata['render_mode'], $overrideMetadata['render_mode']);
        }

        $updateDefinition = $this->db->prepare('UPDATE ui_content_definitions SET description = ?, metadata_json = ? WHERE id = ?');
        $updateDefinition->execute([
            $targetDefinitionTitle,
            json_encode($definitionMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (int) $definition['id'],
        ]);

        $updateOverride = $this->db->prepare('UPDATE ui_content_overrides SET title = ?, body = ?, metadata_json = ? WHERE id = ?');
        $updateOverride->execute([
            $targetOverrideTitle,
            $targetBody,
            json_encode($overrideMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (int) $override['id'],
        ]);
    }

    private function assertSeedBaseline(string $key, array $metadata, bool $forward): void
    {
        if (($metadata['seed'] ?? null) !== self::SEED) throw new RuntimeException('Ownership mismatch: ' . $key);
        if (($metadata['consumer_bound'] ?? false) !== false) throw new RuntimeException('Consumer binding changed prematurely: ' . $key);

        if ($forward) {
            if (array_key_exists('render_mode', $metadata)) throw new RuntimeException('Render mode already present: ' . $key);
        } else {
            if (!in_array(($metadata['render_mode'] ?? null), ['body', 'title_body'], true)) {
                throw new RuntimeException('Normalized render mode missing: ' . $key);
            }
        }
    }

    private function items(): array
    {
        $path = dirname(__DIR__, 3) . '/resources/ui-content/platform-guides.json';
        $catalog = json_decode((string) file_get_contents($path), true);
        if (!is_array($catalog)) throw new RuntimeException('Catalog invalid.');

        $items = array_values(array_filter(
            $catalog,
            static fn (mixed $item): bool => is_array($item) && in_array(($item['content_type'] ?? null), ['notice', 'error'], true)
        ));

        foreach ($items as $item) {
            if (($item['consumer_bound'] ?? null) !== false) throw new RuntimeException('Notice/error binding must still be false.');
            if (!in_array(($item['render_mode'] ?? null), ['body', 'title_body'], true)) throw new RuntimeException('Render mode missing.');
        }

        return $items;
    }

    private function metadata(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) throw new RuntimeException('Metadata invalid.');
        return $decoded;
    }

    private function shortTitle(string $body): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
        $characters = preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters) || count($characters) <= 72) return $body;
        return implode('', array_slice($characters, 0, 72)) . '…';
    }
}
