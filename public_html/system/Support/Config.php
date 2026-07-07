<?php

namespace IPKF\Support;

class Config
{
    protected static array $config = [];

    public static function load(): void
    {
        $files = glob(BASE_PATH . '/config/*.php');

        foreach ($files as $file) {

            $name = basename($file, '.php');

            self::$config[$name] = require $file;
        }
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
}