<?php

namespace IPKF\Support;

class Config
{
    protected static array $config = [];

    protected static bool $loaded = false;

    public static function load(): void
    {
        $files = glob(BASE_PATH . '/config/*.php');

        foreach ($files as $file) {

            $name = basename($file, '.php');

            self::$config[$name] = require $file;
        }

        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);

        $value = self::$config;

        foreach ($segments as $segment) {

            if (!isset($value[$segment])) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function has(string $key): bool
    {
        return self::get($key, null) !== null;
    }

    public static function all(): array
    {
        return self::$config;
    }

    public static function loaded(): bool
    {
        return self::$loaded;
    }
}
