<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/*
 * TICKETING_AUTO_CLOSE_POLICY_FOUNDATION_V1
 *
 * Business enablement is separate from Scheduler state.
 *
 * Safe defaults:
 * - disabled by default;
 * - no implicit delay;
 * - no implicit eligibility boundary;
 * - an enabled policy is invalid until both delay_hours
 *   and eligible_resolved_from are explicitly defined.
 */
final class CreateTicketingAutoClosePolicyFoundation
    extends Migration
{
    public function up(): void
    {
        $options =
            'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci';

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_auto_close_policies
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT PRIMARY KEY,

                support_project_id
                    BIGINT UNSIGNED NOT NULL,

                is_enabled
                    TINYINT(1) NOT NULL
                    DEFAULT 0,

                delay_hours
                    SMALLINT UNSIGNED NULL,

                eligible_resolved_from
                    DATETIME NULL,

                enabled_at
                    DATETIME NULL,

                updated_by_user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_auto_close_policy_project_unique
                    (support_project_id),

                INDEX
                    ticketing_auto_close_policy_enabled_index
                    (
                        is_enabled,
                        support_project_id
                    ),

                CONSTRAINT
                    ticketing_auto_close_policy_project_fk
                    FOREIGN KEY
                    (support_project_id)
                    REFERENCES
                    ticketing_support_projects(id)
                    ON DELETE CASCADE
            ) {$options}
        ");
    }


    public function down(): void
    {
    }
}
