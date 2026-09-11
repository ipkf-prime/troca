<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use DateTimeImmutable;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

/**
 * G4-C1-A2.1-S2
 *
 * View-facing adapter for managed static guides.
 *
 * HTML consumers call titleHtml/bodyHtml.
 * PHP data consumers call titleText/bodyText and keep their existing
 * escaping at the render boundary.
 */
final class UiContentInlineGuide
{
    private PDO $db;
    private UiContentRepository $repository;
    private UiContentResolver $resolver;
    private array $cache = [];
    private static ?self $shared = null;
    private static ?array $fallbackCatalog = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new ConnectionResolver())->resolve('core.primary');
        $this->repository = new UiContentRepository($this->db);
        $this->resolver = new UiContentResolver($this->repository);
    }

    public static function titleText(string $contentKey, string $moduleKey, string $surface): string
    {
        $payload = self::sharedPayload($contentKey, $moduleKey, $surface);
        return (string) ($payload['title'] ?? '');
    }

    public static function bodyText(string $contentKey, string $moduleKey, string $surface): string
    {
        $payload = self::sharedPayload($contentKey, $moduleKey, $surface);
        return (string) ($payload['body'] ?? '');
    }

    public static function titleHtml(string $contentKey, string $moduleKey, string $surface): string
    {
        return self::escape(
            self::titleText($contentKey, $moduleKey, $surface)
        );
    }

    public static function bodyHtml(string $contentKey, string $moduleKey, string $surface): string
    {
        return self::escape(
            self::bodyText($contentKey, $moduleKey, $surface)
        );
    }

    public function resolveGuide(string $contentKey, string $moduleKey, string $surface): array
    {
        $contentKey = strtolower(trim($contentKey));
        $moduleKey = strtolower(trim($moduleKey));
        $surface = strtolower(trim($surface));

        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,189}$/D', $contentKey) !== 1) {
            throw new RuntimeException('ui_content_inline_guide_key_invalid');
        }

        if (preg_match('/^[a-z][a-z0-9_-]{1,99}$/D', $moduleKey) !== 1) {
            throw new RuntimeException('ui_content_inline_guide_module_invalid');
        }

        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,119}$/D', $surface) !== 1) {
            throw new RuntimeException('ui_content_inline_guide_surface_invalid');
        }

        $cacheKey = $contentKey . '|' . $moduleKey . '|' . $surface;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $fallback = self::fallbackGuide($contentKey);

        try {
            if (!$this->repository->available()) {
                return $this->cache[$cacheKey] = $fallback;
            }

            $definition = $this->definitionState($contentKey);

            if (!is_array($definition)) {
                return $this->cache[$cacheKey] = $fallback;
            }

            if ((string) ($definition['content_type'] ?? '') !== 'guide') {
                return $this->cache[$cacheKey] = $fallback;
            }

            if ((int) ($definition['is_active'] ?? 0) !== 1) {
                return $this->cache[$cacheKey] = self::hidden();
            }

            $scopeKey = 'scope:' . $moduleKey . ':surface:' . $surface;
            $surfaceOverride = $this->surfaceOverrideState((int) $definition['id'], $scopeKey);

            if (!is_array($surfaceOverride)) {
                return $this->cache[$cacheKey] = $fallback;
            }

            if (!$this->overrideEligible($surfaceOverride, new DateTimeImmutable())) {
                return $this->cache[$cacheKey] = self::hidden();
            }

            $context = new UiContentContext(
                $moduleKey,
                'fa',
                [[
                    'type' => 'surface',
                    'reference' => $surface,
                ]]
            );

            $resolved = $this->resolver->resolve($contentKey, $context);

            if (!($resolved['available'] ?? false)) {
                return $this->cache[$cacheKey] = $fallback;
            }

            if (($resolved['visible'] ?? true) !== true) {
                return $this->cache[$cacheKey] = self::hidden();
            }

            return $this->cache[$cacheKey] = [
                'available' => true,
                'visible' => true,
                'title' => array_key_exists('title', $resolved) && $resolved['title'] !== null
                    ? (string) $resolved['title']
                    : (string) ($fallback['title'] ?? ''),
                'body' => array_key_exists('body', $resolved) && $resolved['body'] !== null
                    ? (string) $resolved['body']
                    : (string) ($fallback['body'] ?? ''),
                'source' => 'managed',
            ];
        } catch (Throwable) {
            return $this->cache[$cacheKey] = $fallback;
        }
    }

    private static function sharedPayload(string $contentKey, string $moduleKey, string $surface): array
    {
        try {
            self::$shared ??= new self();
            return self::$shared->resolveGuide($contentKey, $moduleKey, $surface);
        } catch (Throwable) {
            return self::fallbackGuide(strtolower(trim($contentKey)));
        }
    }

    private function definitionState(string $contentKey): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, content_type, is_active FROM ui_content_definitions WHERE content_key = ? LIMIT 1'
        );
        $statement->execute([$contentKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function surfaceOverrideState(int $definitionId, string $scopeKey): ?array
    {
        $statement = $this->db->prepare(
            "SELECT visibility_mode, starts_at, ends_at, is_active
             FROM ui_content_overrides
             WHERE definition_id = ? AND scope_key = ? AND locale = 'fa'
             LIMIT 1"
        );
        $statement->execute([$definitionId, $scopeKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function overrideEligible(array $row, DateTimeImmutable $now): bool
    {
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            return false;
        }

        $timestamp = $now->format('Y-m-d H:i:s');
        $startsAt = trim((string) ($row['starts_at'] ?? ''));
        $endsAt = trim((string) ($row['ends_at'] ?? ''));

        if ($startsAt !== '' && $startsAt > $timestamp) {
            return false;
        }

        if ($endsAt !== '' && $endsAt < $timestamp) {
            return false;
        }

        return true;
    }

    private static function fallbackGuide(string $contentKey): array
    {
        $item = self::fallbackCatalog()[$contentKey] ?? null;

        if (!is_array($item)) {
            return self::hidden();
        }

        $body = (string) ($item['body'] ?? '');
        $title = trim((string) ($item['title'] ?? ''));

        if ($title === '') {
            $title = self::shortTitle($body);
        }

        return [
            'available' => false,
            'visible' => true,
            'title' => $title,
            'body' => $body,
            'source' => 'catalog_fallback',
        ];
    }

    private static function fallbackCatalog(): array
    {
        if (self::$fallbackCatalog !== null) {
            return self::$fallbackCatalog;
        }

        self::$fallbackCatalog = [];

        $path = dirname(__DIR__, 3) . '/resources/ui-content/platform-guides.json';

        if (!is_readable($path)) {
            return self::$fallbackCatalog;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            return self::$fallbackCatalog;
        }

        foreach ($decoded as $item) {
            if (!is_array($item) || ($item['content_type'] ?? null) !== 'guide') {
                continue;
            }

            $key = strtolower(trim((string) ($item['key'] ?? '')));

            if ($key !== '') {
                self::$fallbackCatalog[$key] = $item;
            }
        }

        return self::$fallbackCatalog;
    }

    private static function hidden(): array
    {
        return [
            'available' => true,
            'visible' => false,
            'title' => '',
            'body' => '',
            'source' => 'hidden',
        ];
    }

    private static function shortTitle(string $body): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
        $characters = preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($characters) || count($characters) <= 72) {
            return $body;
        }

        return implode('', array_slice($characters, 0, 72)) . '…';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
