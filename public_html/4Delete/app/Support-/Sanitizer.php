<?php

namespace App\Support;

class Sanitizer
{
    public static function clean(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    public static function array(array $data): array
    {
        return array_map(fn($v) => self::clean($v), $data);
    }
}