<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class CreateTicketingProjectOrganizationScopeFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createProjectCatalogBindings();
        $this->extendProjectMembers();
        $this->createProjectMemberScopes();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Project scope data can be part of the ticket audit trail.
         */
    }


    private function createProjectCatalogBindings(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_project_catalog_bindings
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                project_id BIGINT UNSIGNED
                    NOT NULL,

                core_catalog_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                catalog_code_snapshot VARCHAR(120)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                catalog_title_snapshot VARCHAR(255)
                    NULL,

                binding_role_code VARCHAR(50)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'organization_context',

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                created_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                updated_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_project_catalog_ref_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_project_catalog_binding_unique
                    (
                        project_id,
                        core_catalog_reference,
                        binding_role_code
                    ),

                INDEX
                    ticketing_project_catalog_status_index
                    (
                        project_id,
                        status
                    ),

                CONSTRAINT
                    ticketing_project_catalog_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function extendProjectMembers(): void
    {
        $columns = [
            'core_organization_membership_reference' =>
                "VARCHAR(100)
                 CHARACTER SET ascii
                 COLLATE ascii_bin
                 NULL",

            'organization_reference' =>
                "VARCHAR(100)
                 CHARACTER SET ascii
                 COLLATE ascii_bin
                 NULL",

            'organization_title_snapshot' =>
                "VARCHAR(255) NULL",

            'organization_role_code_snapshot' =>
                "VARCHAR(80)
                 CHARACTER SET ascii
                 COLLATE ascii_bin
                 NULL",
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists(
                'ticketing_support_project_members',
                $column
            )) {
                $this->db->exec("
                    ALTER TABLE
                        ticketing_support_project_members
                    ADD COLUMN {$column}
                        {$definition}
                ");
            }
        }

        if (!$this->indexExists(
            'ticketing_support_project_members',
            'ticketing_project_members_org_index'
        )) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_members

                ADD INDEX
                    ticketing_project_members_org_index
                    (
                        project_id,
                        organization_reference,
                        left_at
                    )
            ");
        }

        if (!$this->indexExists(
            'ticketing_support_project_members',
            'ticketing_project_members_core_org_membership_index'
        )) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_members

                ADD INDEX
                    ticketing_project_members_core_org_membership_index
                    (
                        core_organization_membership_reference
                    )
            ");
        }
    }


    private function createProjectMemberScopes(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_project_member_scopes
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                project_member_id BIGINT UNSIGNED
                    NOT NULL,

                scope_type_code VARCHAR(50)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                scope_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                access_mode_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'exact',

                capabilities_json LONGTEXT NULL,

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                valid_from DATETIME NULL,
                valid_until DATETIME NULL,

                created_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                updated_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_member_scopes_ref_unique
                    (public_reference),

                INDEX
                    ticketing_member_scopes_member_index
                    (
                        project_member_id,
                        status
                    ),

                INDEX
                    ticketing_member_scopes_lookup_index
                    (
                        scope_type_code,
                        scope_reference,
                        status
                    ),

                INDEX
                    ticketing_member_scopes_access_index
                    (
                        access_mode_code,
                        status
                    ),

                CONSTRAINT
                    ticketing_member_scopes_member_fk
                    FOREIGN KEY (project_member_id)
                    REFERENCES ticketing_support_project_members(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    private function indexExists(
        string $table,
        string $index
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND index_name = ?
            ");

        $statement->execute([
            $table,
            $index,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
