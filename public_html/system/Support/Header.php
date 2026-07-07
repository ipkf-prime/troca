<?php

namespace IPKF\Support;

class Header
{
    public static function set(string $key, string $value): void
    {
        header("$key: $value");
    }

    public static function json(): void
    {
        header("Content-Type: application/json");
    }

    public static function noCache(): void
    {
        header("Cache-Control: no-store, no-cache, must-revalidate");
    }
}