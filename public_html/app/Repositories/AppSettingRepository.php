<?php

namespace App\Repositories;

class AppSettingRepository extends BaseRepository
{
    public function get(string $namespace, string $key): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM app_settings
            WHERE namespace = ?
              AND setting_key = ?
            LIMIT 1
        ");
        $statement->execute([$namespace, $key]);
        $setting = $statement->fetch();

        return $setting ?: null;
    }

    public function list(string $namespace): array
    {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM app_settings
            WHERE namespace = ?
            ORDER BY setting_key ASC
        ");
        $statement->execute([$namespace]);

        return $statement->fetchAll();
    }

    public function put(string $namespace, string $key, string $value, string $type = 'string', bool $public = true): void
    {
        $statement = $this->connection()->prepare("
            INSERT INTO app_settings (
                namespace, setting_key, setting_value, value_type, is_public,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                is_public = VALUES(is_public),
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute([$namespace, $key, $value, $type, $public ? 1 : 0]);
    }
}
