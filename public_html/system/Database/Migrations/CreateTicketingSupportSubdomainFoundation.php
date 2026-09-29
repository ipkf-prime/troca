<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class CreateTicketingSupportSubdomainFoundation extends Migration
{
    public function up(): void
    {
        $this->createSubdomains();
        $this->extendServices();
    }

    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Subdomain becomes part of the support taxonomy identity.
         * Automatic rollback must not remove a populated taxonomy
         * table, service relation, or supporting constraints.
         */
    }

    private function createSubdomains(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_support_subdomains
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference VARCHAR(40)
                    NOT NULL,

                project_id BIGINT UNSIGNED
                    NOT NULL,

                code VARCHAR(80)
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT
                    NULL,

                sort_order INT
                    NOT NULL
                    DEFAULT 0,

                is_default TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                is_active TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                created_at TIMESTAMP
                    NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP
                    NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_support_subdomains_reference_unique
                    (
                        public_reference
                    ),

                UNIQUE KEY
                    ticketing_support_subdomains_project_code_unique
                    (
                        project_id,
                        code
                    ),

                UNIQUE KEY
                    ticketing_support_subdomains_project_id_unique
                    (
                        project_id,
                        id
                    ),

                KEY
                    ticketing_support_subdomains_active_sort_index
                    (
                        project_id,
                        is_active,
                        sort_order,
                        id
                    ),

                CONSTRAINT
                    ticketing_support_subdomains_project_fk
                FOREIGN KEY
                    (
                        project_id
                    )
                REFERENCES
                    ticketing_support_projects(id)
                ON UPDATE RESTRICT
                ON DELETE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function extendServices(): void
    {
        /*
         * Compatibility window:
         *
         * Existing service/subsystem identities remain unchanged.
         * No customer-specific subdomain row is seeded by source.
         * NULL means that an existing service has not yet been assigned
         * to a concrete subdomain by controlled configuration/data.
         */
        if (
            !$this->columnExists(
                'ticketing_support_services',
                'subdomain_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_services
                ADD COLUMN
                    subdomain_id BIGINT UNSIGNED
                    NULL
                AFTER
                    project_id
            ");
        }

        /*
         * Preserve the existing project/service composite identity key.
         * It is already part of the current ticket referential-integrity
         * contract and must not be dropped or reinterpreted here.
         */

        if (
            !$this->indexExists(
                'ticketing_support_services',
                'ticketing_support_services_project_subdomain_sort_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_services
                ADD KEY
                    ticketing_support_services_project_subdomain_sort_index
                    (
                        project_id,
                        subdomain_id,
                        is_active,
                        sort_order,
                        id
                    )
            ");
        }

        /*
         * Composite FK enforces same-project ownership:
         * a service may only bind to a subdomain from its own project.
         * Existing NULL subdomain_id values remain valid.
         */
        if (
            !$this->constraintExists(
                'ticketing_support_services',
                'ticketing_support_services_subdomain_fk'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_services
                ADD CONSTRAINT
                    ticketing_support_services_subdomain_fk
                FOREIGN KEY
                    (
                        project_id,
                        subdomain_id
                    )
                REFERENCES
                    ticketing_support_subdomains
                    (
                        project_id,
                        id
                    )
                ON UPDATE RESTRICT
                ON DELETE RESTRICT
            ");
        }
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
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
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND INDEX_NAME = ?
            ");

        $statement->execute([
            $table,
            $index,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }

    private function constraintExists(
        string $table,
        string $constraint
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
            ");

        $statement->execute([
            $table,
            $constraint,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
