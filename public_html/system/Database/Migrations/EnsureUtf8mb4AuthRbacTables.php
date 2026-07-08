<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;

class EnsureUtf8mb4AuthRbacTables extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as $table) {
            if (Database::tableExists($table)) {
                $this->db->exec("ALTER TABLE {$table} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
    }

    public function down(): void
    {
    }

    private function tables(): array
    {
        return [
            'persons',
            'users',
            'role_areas',
            'role_kinds',
            'roles',
            'permissions',
            'role_permissions',
            'user_role_assignments',
            'user_mfa_methods',
            'mfa_challenges',
            'trusted_devices',
            'recovery_codes',
            'organizations',
        ];
    }
}
