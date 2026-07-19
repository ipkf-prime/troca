<?php

namespace IPKF\Support;

use IPKF\Database\Database;
use Throwable;

class ModuleRuntimeConfig
{
    private static array $cache = [];

    public function active(string $moduleKey): ?array
    {
        $moduleKey = strtolower(trim($moduleKey));
        if ($moduleKey === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{1,99}$/', $moduleKey)) {
            return null;
        }

        if (array_key_exists($moduleKey, self::$cache)) {
            return self::$cache[$moduleKey];
        }

        try {
            if (!Database::tableExists('application_modules')) {
                return self::$cache[$moduleKey] = null;
            }

            $statement = Database::connect()->prepare("
                SELECT *
                FROM application_modules
                WHERE module_key = :module_key
                  AND is_active = 1
                LIMIT 1
            ");
            $statement->execute(['module_key' => $moduleKey]);
            $row = $statement->fetch();

            return self::$cache[$moduleKey] = (is_array($row) ? $row : null);
        } catch (Throwable) {
            return self::$cache[$moduleKey] = null;
        }
    }

    public function secret(array $module, string $fallbackReference): string
    {
        $reference = trim((string) ($module['secret_reference'] ?? ''));
        if (!preg_match('/^[A-Z][A-Z0-9_]{2,127}$/', $reference)) {
            $reference = $fallbackReference;
        }

        return (string) Env::get($reference, Env::get($fallbackReference, ''));
    }
}
