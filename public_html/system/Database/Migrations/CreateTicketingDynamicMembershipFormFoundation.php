<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class CreateTicketingDynamicMembershipFormFoundation
    extends Migration
{
    public function up(): void
    {
        $membershipModeAdded =
            !$this->columnExists(
                'membership_mode'
            );

        $approvalModeAdded =
            !$this->columnExists(
                'approval_mode'
            );


        if ($membershipModeAdded) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_requester_access
                ADD COLUMN
                    membership_mode
                    VARCHAR(20)
                    NOT NULL
                    DEFAULT 'public'
            ");
        }


        if ($approvalModeAdded) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_requester_access
                ADD COLUMN
                    approval_mode
                    VARCHAR(20)
                    NOT NULL
                    DEFAULT 'manager'
            ");
        }


        if (
            !$this->columnExists(
                'form_enabled'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_requester_access
                ADD COLUMN
                    form_enabled
                    TINYINT(1)
                    NOT NULL
                    DEFAULT 0
            ");
        }


        /*
         * Generic backward compatibility only.
         * No project/business identifier is involved.
         */
        if ($membershipModeAdded) {
            $this->db->exec("
                UPDATE
                    ticketing_support_project_requester_access
                SET
                    membership_mode =
                        CASE
                            WHEN self_join_enabled = 1
                                THEN 'public'
                            ELSE 'private'
                        END
            ");
        }


        if ($approvalModeAdded) {
            $this->db->exec("
                UPDATE
                    ticketing_support_project_requester_access
                SET
                    approval_mode =
                        CASE
                            WHEN self_join_enabled = 1
                                THEN 'auto'
                            ELSE 'manager'
                        END
            ");
        }


        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_support_project_membership_fields
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(50)
                    NOT NULL,

                project_id BIGINT UNSIGNED
                    NOT NULL,

                field_key VARCHAR(100)
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                field_type VARCHAR(30)
                    NOT NULL,

                data_source_key VARCHAR(190)
                    NULL,

                options_json LONGTEXT
                    NULL,

                dependency_field_key VARCHAR(100)
                    NULL,

                validation_json LONGTEXT
                    NULL,

                is_required TINYINT(1)
                    NOT NULL DEFAULT 0,

                sort_order INT UNSIGNED
                    NOT NULL DEFAULT 0,

                is_active TINYINT(1)
                    NOT NULL DEFAULT 1,

                created_by_user_reference VARCHAR(100)
                    NULL,

                updated_by_user_reference VARCHAR(100)
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_membership_field_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_membership_field_project_key_unique
                    (project_id, field_key),

                KEY
                    ticketing_membership_field_project_sort_index
                    (project_id, is_active, sort_order),

                CONSTRAINT
                    ticketing_membership_field_project_fk
                FOREIGN KEY (project_id)
                    REFERENCES
                        ticketing_support_projects(id)
                    ON DELETE CASCADE
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    public function down(): void
    {
        $this->db->exec("
            DROP TABLE IF EXISTS
                ticketing_support_project_membership_fields
        ");

        foreach ([
            'form_enabled',
            'approval_mode',
            'membership_mode',
        ] as $column) {

            if ($this->columnExists($column)) {
                $this->db->exec(
                    "
                    ALTER TABLE
                        ticketing_support_project_requester_access
                    DROP COLUMN `{$column}`
                    "
                );
            }
        }
    }


    private function columnExists(
        string $column
    ): bool {
        $q = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name =
                  'ticketing_support_project_requester_access'
              AND column_name = ?
        ");

        $q->execute([$column]);

        return
            (int) $q->fetchColumn()
            > 0;
    }
}
