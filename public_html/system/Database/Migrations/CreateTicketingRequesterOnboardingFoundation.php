<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class CreateTicketingRequesterOnboardingFoundation
    extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE
                ticketing_support_project_requester_access
            (
                project_id BIGINT UNSIGNED NOT NULL,
                self_join_enabled TINYINT(1) NOT NULL DEFAULT 0,
                invite_join_enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_by_user_reference VARCHAR(100) NULL,
                updated_by_user_reference VARCHAR(100) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (project_id),

                CONSTRAINT ticketing_requester_access_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects(id)
                    ON DELETE CASCADE
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE
                ticketing_support_project_invites
            (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_reference VARCHAR(40) NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                code_hash CHAR(64) NOT NULL,
                code_preview VARCHAR(40) NOT NULL,
                status_code VARCHAR(30) NOT NULL DEFAULT 'active',
                max_uses INT UNSIGNED NULL,
                use_count INT UNSIGNED NOT NULL DEFAULT 0,
                valid_from DATETIME NULL,
                valid_until DATETIME NULL,
                created_by_user_reference VARCHAR(100) NULL,
                revoked_by_user_reference VARCHAR(100) NULL,
                revoked_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),

                UNIQUE KEY ticketing_project_invites_reference_unique
                    (public_reference),

                UNIQUE KEY ticketing_project_invites_hash_unique
                    (code_hash),

                KEY ticketing_project_invites_project_status_index
                    (project_id, status_code, valid_until),

                CONSTRAINT ticketing_project_invites_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects(id)
                    ON DELETE CASCADE
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE
                ticketing_support_project_invite_uses
            (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                invite_id BIGINT UNSIGNED NOT NULL,
                project_member_id BIGINT UNSIGNED NULL,
                user_reference VARCHAR(100) NOT NULL,
                used_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (id),

                UNIQUE KEY ticketing_invite_use_user_unique
                    (invite_id, user_reference),

                CONSTRAINT ticketing_invite_use_invite_fk
                    FOREIGN KEY (invite_id)
                    REFERENCES ticketing_support_project_invites(id)
                    ON DELETE CASCADE,

                CONSTRAINT ticketing_invite_use_member_fk
                    FOREIGN KEY (project_member_id)
                    REFERENCES ticketing_support_project_members(id)
                    ON DELETE SET NULL
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            INSERT INTO ticketing_support_project_requester_access
            (
                project_id,
                self_join_enabled,
                invite_join_enabled,
                created_by_user_reference,
                updated_by_user_reference,
                created_at,
                updated_at
            )
            SELECT
                id,
                1,
                1,
                'system:requester-onboarding',
                'system:requester-onboarding',
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            FROM ticketing_support_projects
            WHERE is_active = 1
              AND archived_at IS NULL
        ");
    }


    public function down(): void
    {
        $this->db->exec("
            DROP TABLE IF EXISTS
                ticketing_support_project_invite_uses
        ");

        $this->db->exec("
            DROP TABLE IF EXISTS
                ticketing_support_project_invites
        ");

        $this->db->exec("
            DROP TABLE IF EXISTS
                ticketing_support_project_requester_access
        ");
    }
}
