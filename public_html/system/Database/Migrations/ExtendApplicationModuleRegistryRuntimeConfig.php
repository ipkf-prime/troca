<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;
use IPKF\Support\Env;

class ExtendApplicationModuleRegistryRuntimeConfig extends Migration
{
    public function up(): void
    {
        $columns = [
            'database_username' => "VARCHAR(190) NULL AFTER database_name",
            'database_charset' => "VARCHAR(40) NOT NULL DEFAULT 'utf8mb4' AFTER database_username",
            'database_ssl_mode' => "VARCHAR(40) NULL AFTER database_charset",
            'connection_timeout' => "SMALLINT UNSIGNED NOT NULL DEFAULT 5 AFTER database_ssl_mode",
            'runtime_mode' => "VARCHAR(30) NOT NULL DEFAULT 'fallback' AFTER connection_timeout",
        ];

        foreach ($columns as $column => $definition) {
            if (!Database::columnExists('application_modules', $column)) {
                $this->db->exec("ALTER TABLE application_modules ADD COLUMN {$column} {$definition}");
            }
        }

        $mode = strtolower(trim((string) Env::get('AUTOMATION_DB_MODE', 'fallback')));
        if (!in_array($mode, ['fallback', 'provisioning', 'dedicated'], true)) {
            $mode = 'fallback';
        }
        $statement = $this->db->prepare("
            UPDATE application_modules SET
                database_connection_name = 'automation.primary',
                database_host = :host,
                database_port = :port,
                database_name = :database_name,
                database_username = :username,
                database_charset = :charset,
                database_ssl_mode = :ssl_mode,
                connection_timeout = :connection_timeout,
                runtime_mode = :runtime_mode,
                secret_reference = 'AUTOMATION_DB_PASSWORD'
            WHERE module_key = 'automation'
        ");
        $statement->execute([
            'host' => (string) Env::get('AUTOMATION_DB_HOST', 'localhost'),
            'port' => (int) Env::get('AUTOMATION_DB_PORT', 3306),
            'database_name' => (string) Env::get('AUTOMATION_DB_DATABASE', ''),
            'username' => (string) Env::get('AUTOMATION_DB_USERNAME', ''),
            'charset' => (string) Env::get('AUTOMATION_DB_CHARSET', 'utf8mb4'),
            'ssl_mode' => (string) Env::get('AUTOMATION_DB_SSL_MODE', '') ?: null,
            'connection_timeout' => max(1, min(60, (int) Env::get('AUTOMATION_DB_CONNECTION_TIMEOUT', 5))),
            'runtime_mode' => $mode,
        ]);
    }

    public function down(): void
    {
    }
}
