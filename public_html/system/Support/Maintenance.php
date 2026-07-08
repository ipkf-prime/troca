<?php

namespace IPKF\Support;

class Maintenance
{
    public static function keyIsValid(?string $providedKey): bool
    {
        $expectedKey = Env::get('DEV_MAINTENANCE_KEY', '');

        if (!is_string($providedKey) || $providedKey === '') {
            return false;
        }

        if (!is_string($expectedKey) || $expectedKey === '' || $expectedKey === 'change-me') {
            return false;
        }

        return hash_equals($expectedKey, $providedKey);
    }

    public static function deny(string $path): void
    {
        http_response_code(404);
        echo "404 - Route not found: {$path}";
        exit;
    }
}
