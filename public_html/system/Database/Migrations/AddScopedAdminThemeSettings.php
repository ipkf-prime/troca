<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;
use PDO;

class AddScopedAdminThemeSettings extends Migration
{
    public function up(): void
    {
        if (!Database::tableExists('app_settings')) {
            return;
        }

        if (!Database::columnExists('app_settings', 'user_id')) {
            $this->db->exec('ALTER TABLE app_settings ADD COLUMN user_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER id');
            $this->db->exec('UPDATE app_settings SET user_id = 0 WHERE user_id IS NULL');
        }

        if ($this->indexExists('app_settings', 'app_settings_namespace_key_unique')) {
            $this->db->exec('ALTER TABLE app_settings DROP INDEX app_settings_namespace_key_unique');
        }

        if (!$this->indexExists('app_settings', 'app_settings_user_namespace_key_unique')) {
            $this->db->exec('ALTER TABLE app_settings ADD UNIQUE KEY app_settings_user_namespace_key_unique (user_id, namespace, setting_key)');
        }

        if (!$this->indexExists('app_settings', 'app_settings_user_namespace_index')) {
            $this->db->exec('ALTER TABLE app_settings ADD INDEX app_settings_user_namespace_index (user_id, namespace)');
        }
    }

    public function down(): void
    {
    }

    private function indexExists(string $table, string $index): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
        ");
        $statement->execute([$table, $index]);

        return (int) $statement->fetchColumn() > 0;
    }
}
