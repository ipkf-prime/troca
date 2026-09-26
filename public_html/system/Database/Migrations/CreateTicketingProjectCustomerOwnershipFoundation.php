<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/** Explicit project customer identity. No inferred/backfilled ownership. */
final class CreateTicketingProjectCustomerOwnershipFoundation extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ticketing_project_customer_ownership (
                project_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                customer_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                assigned_by_user_reference VARCHAR(100) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX ticketing_project_customer_ownership_customer_index (customer_reference),
                CONSTRAINT ticketing_project_customer_ownership_project_fk
                    FOREIGN KEY (project_id) REFERENCES ticketing_support_projects(id)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        // Preserve customer assignment/audit trail.
    }
}
