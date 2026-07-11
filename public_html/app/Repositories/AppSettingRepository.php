<?php

namespace App\Repositories;

use IPKF\Database\Database;

class AppSettingRepository extends BaseRepository
{
    public function get(string $namespace, string $key, int $userId = 0): ?array
    {
        if (!$this->scoped()) {
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

        $statement = $this->connection()->prepare("
            SELECT *
            FROM app_settings
            WHERE namespace = ?
              AND setting_key = ?
              AND user_id = ?
            LIMIT 1
        ");
        $statement->execute([$namespace, $key, $userId]);
        $setting = $statement->fetch();

        return $setting ?: null;
    }

    public function list(string $namespace, int $userId = 0): array
    {
        if (!$this->scoped()) {
            $statement = $this->connection()->prepare("
                SELECT *
                FROM app_settings
                WHERE namespace = ?
                ORDER BY setting_key ASC
            ");
            $statement->execute([$namespace]);

            return $statement->fetchAll();
        }

        $statement = $this->connection()->prepare("
            SELECT *
            FROM app_settings
            WHERE namespace = ?
              AND user_id = ?
            ORDER BY setting_key ASC
        ");
        $statement->execute([$namespace, $userId]);

        return $statement->fetchAll();
    }

    public function scopedRows(string $namespace, int $userId = 0): array
    {
        if (!$this->scoped()) {
            return $this->list($namespace);
        }

        $statement = $this->connection()->prepare("
            SELECT id, user_id, namespace, setting_key, setting_value, value_type, is_public, updated_at
            FROM app_settings
            WHERE namespace = ?
              AND user_id = ?
            ORDER BY setting_key ASC
        ");
        $statement->execute([$namespace, $userId]);

        return $statement->fetchAll();
    }

    public function otherScopedUserCount(string $namespace, int $currentUserId): int
    {
        if (!$this->scoped()) {
            return 0;
        }

        $statement = $this->connection()->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM app_settings
            WHERE namespace = ?
              AND user_id > 0
              AND user_id <> ?
        ");
        $statement->execute([$namespace, $currentUserId]);

        return (int) $statement->fetchColumn();
    }

    public function tokenOverrideCount(string $namespace, int $userId = 0): int
    {
        if (!$this->scoped()) {
            $statement = $this->connection()->prepare("
                SELECT COUNT(*)
                FROM app_settings
                WHERE namespace = ?
                  AND setting_key LIKE 'token.%'
            ");
            $statement->execute([$namespace]);

            return (int) $statement->fetchColumn();
        }

        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM app_settings
            WHERE namespace = ?
              AND user_id = ?
              AND setting_key LIKE 'token.%'
        ");
        $statement->execute([$namespace, $userId]);

        return (int) $statement->fetchColumn();
    }

    public function put(string $namespace, string $key, string $value, string $type = 'string', bool $public = true, int $userId = 0): void
    {
        if (!$this->scoped()) {
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
            return;
        }

        $statement = $this->connection()->prepare("
            INSERT INTO app_settings (
                user_id, namespace, setting_key, setting_value, value_type, is_public,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                is_public = VALUES(is_public),
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute([$userId, $namespace, $key, $value, $type, $public ? 1 : 0]);
    }

    public function deleteNamespace(string $namespace, int $userId): void
    {
        if (!$this->scoped()) {
            return;
        }

        $statement = $this->connection()->prepare("
            DELETE FROM app_settings
            WHERE namespace = ?
              AND user_id = ?
        ");
        $statement->execute([$namespace, $userId]);
    }

    public function delete(string $namespace, string $key, int $userId = 0): void
    {
        if (!$this->scoped()) {
            $statement = $this->connection()->prepare("
                DELETE FROM app_settings
                WHERE namespace = ?
                  AND setting_key = ?
            ");
            $statement->execute([$namespace, $key]);
            return;
        }

        $statement = $this->connection()->prepare("
            DELETE FROM app_settings
            WHERE namespace = ?
              AND setting_key = ?
              AND user_id = ?
        ");
        $statement->execute([$namespace, $key, $userId]);
    }

    public function scoped(): bool
    {
        return Database::columnExists('app_settings', 'user_id');
    }
}
