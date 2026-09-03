<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * ROLE_KIND_SORT_ORDER_V1
 *
 * Adds persisted governance ordering to role kinds.
 */
class AddRoleKindSortOrder extends Migration
{
    public function up(): void
    {
        if (!$this->columnExists('role_kinds', 'sort_order')) {
            $this->db->exec("
                ALTER TABLE role_kinds
                ADD COLUMN sort_order INT NOT NULL DEFAULT 0
                AFTER description
            ");
        }

        $orders = [
            'system_admin'   => 10,
            'central_admin'  => 20,
            'province_admin' => 30,
            'county_admin'   => 40,
            'manager'        => 50,
            'expert'         => 60,
            'auditor'        => 70,
            'inspector'      => 80,
            'support'        => 90,
            'operator'       => 100,
            'supplier'       => 110,
            'customer'       => 120,
        ];

        $statement = $this->db->prepare("
            UPDATE role_kinds
            SET sort_order = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = ?
        ");

        foreach ($orders as $code => $sortOrder) {
            $statement->execute([$sortOrder, $code]);
        }
    }

    public function down(): void
    {
        if ($this->columnExists('role_kinds', 'sort_order')) {
            $this->db->exec("
                ALTER TABLE role_kinds
                DROP COLUMN sort_order
            ");
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }
}
