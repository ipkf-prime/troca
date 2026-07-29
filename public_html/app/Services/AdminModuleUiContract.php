<?php

namespace App\Services;

class AdminModuleUiContract
{
    public const SHARED_LAYOUT = '/resources/views/admin/layout.php';

    private const SHARED_ASSETS = [
        'css' => [
            '/assets/admin/css/admin.css',
            '/assets/admin/css/icons.css',
        ],
        'js' => [
            '/assets/admin/js/admin.js',
        ],
    ];

    public static function shell(
        string $key,
        string $title,
        string $subtitle,
        string $homeUrl,
        string $coreUrl,
        array $assets = []
    ): array {
        return [
            'key' => self::safeKey($key),
            'title' => $title,
            'subtitle' => $subtitle,
            'home_url' => self::safeAdminPath($homeUrl),
            'core_url' => $coreUrl,
            'assets' => self::safeAssets($assets),
            'shared_layout' => self::SHARED_LAYOUT,
        ];
    }

    public static function safeAssets(array $assets): array
    {
        $safe = ['css' => [], 'js' => []];

        foreach (($assets['css'] ?? []) as $asset) {
            $asset = trim((string) $asset);
            if (self::isSafeAdminAsset($asset, 'css')) {
                $safe['css'][] = $asset;
            }
        }

        foreach (($assets['js'] ?? []) as $asset) {
            $asset = trim((string) $asset);
            if (self::isSafeAdminAsset($asset, 'js')) {
                $safe['js'][] = $asset;
            }
        }

        return [
            'css' => array_values(array_unique($safe['css'])),
            'js' => array_values(array_unique($safe['js'])),
        ];
    }

    public static function safeAdminPath(string $path): string
    {
        $path = trim($path);

        if ($path !== '/admin' && !str_starts_with($path, '/admin/')) {
            return '/admin/dashboard';
        }

        if ($path === ''
            || str_contains($path, '://')
            || str_starts_with($path, '//')
            || str_contains($path, '..')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F\s]/', $path) === 1
        ) {
            return '/admin/dashboard';
        }

        return $path;
    }

    private static function safeKey(string $key): string
    {
        return preg_match('/^[a-z][a-z0-9_\-]{1,40}$/', $key) === 1 ? $key : 'module';
    }

    private static function isSafeAdminAsset(string $asset, string $type): bool
    {
        if ($asset === '' || str_contains($asset, '://') || str_starts_with($asset, '//') || str_contains($asset, '..')) {
            return false;
        }

        $extension = $type === 'js' ? 'js' : 'css';

        if (preg_match('#^/assets/admin/[A-Za-z0-9_\-/]+\.' . $extension . '$#', $asset) !== 1) {
            return false;
        }

        return !in_array($asset, self::SHARED_ASSETS[$type] ?? [], true);
    }
}