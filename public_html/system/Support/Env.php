<?php

namespace IPKF\Support;

class Env
{
    protected static bool $loaded = false;
    protected static array $loadedPaths = [];

    public static function load(string $path, bool $overwrite = true): void
    {
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath) || isset(self::$loadedPaths[$realPath])) {
            return;
        }

        $lines = file($realPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {

            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));

            if ($key === '') {
                continue;
            }

                $value = self::decodeValue($value);
            if (!$overwrite && (array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER))) {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        self::$loadedPaths[$realPath] = true;
        self::$loaded = true;
    }

    public static function loadLayered(string $localPath): void
    {
        self::load($localPath);

        $sharedPath = trim((string) self::get('IPKF_SHARED_ENV', ''));
        if ($sharedPath === '') {
            self::deriveModuleValues();
            return;
        }

        if (!str_starts_with($sharedPath, DIRECTORY_SEPARATOR)) {
            $sharedPath = dirname($localPath) . DIRECTORY_SEPARATOR . $sharedPath;
        }

        // The local descriptor wins only for module identity; shared settings
        // remain the single source of truth for secrets, sessions and URLs.
        self::load($sharedPath);
        self::loadModuleDescriptor($localPath);
        self::deriveModuleValues();
    }

    private static function loadModuleDescriptor(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (in_array($key, ['IPKF_SHARED_ENV', 'IPKF_MODULE'], true)) {
                $_ENV[$key] = $_SERVER[$key] = self::decodeValue($value);
            }
        }
    }

    private static function decodeValue(string $value): string
    {
            $value = trim($value);
            if ($value === '') {
                return '';
            }

            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $decoded = json_decode($value, true);
                if (is_string($decoded)) {
                    return $decoded;
                }
            }

            if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                return substr($value, 1, -1);
            }

            return $value;
    }

    private static function deriveModuleValues(): void
    {
        $module = strtolower(trim((string) self::get('IPKF_MODULE', '')));
        if ($module === '') {
            return;
        }
        if (!preg_match('/^[a-z][a-z0-9_-]{1,99}$/', $module)) {
            return;
        }

        $urlKey = strtoupper(str_replace('-', '_', $module)) . '_APP_URL';
        $moduleUrl = trim((string) self::get($urlKey, ''));
        if ($moduleUrl !== '') {
            $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $moduleUrl;
        }
    }

    public static function get(string $key, $default = null)
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    public static function loaded(): bool
    {
        return self::$loaded;
    }

    public static function isDebug(): bool
    {
        return filter_var(self::get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }
}
