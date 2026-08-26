<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;

final class CreateTicketingSupportProjectFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createProjects();
        $this->createProjectMembers();
        $this->createServices();
        $this->extendTickets();
        $this->ensureTicketConstraints();
    }


    public function down(): void
    {
    }


    private function createProjects(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_projects
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT NULL,

                color_code CHAR(7)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                icon_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                sort_order INT
                    NOT NULL DEFAULT 0,

                is_active TINYINT(1)
                    NOT NULL DEFAULT 1,

                archived_at DATETIME NULL,

                created_by_user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_support_projects_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_support_projects_code_unique
                    (code),

                INDEX
                    ticketing_support_projects_active_sort_index
                    (
                        is_active,
                        archived_at,
                        sort_order
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createProjectMembers(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_project_members
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                project_id BIGINT UNSIGNED
                    NOT NULL,

                user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                person_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                display_name_snapshot
                    VARCHAR(255)
                    NOT NULL,

                role_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'requester',

                joined_at DATETIME
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                left_at DATETIME NULL,

                created_by_user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                updated_by_user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_support_project_members_unique
                    (
                        project_id,
                        user_reference
                    ),

                INDEX
                    ticketing_support_project_members_user_active_index
                    (
                        user_reference,
                        left_at,
                        project_id
                    ),

                INDEX
                    ticketing_support_project_members_project_role_index
                    (
                        project_id,
                        role_code,
                        left_at
                    ),

                CONSTRAINT
                    ticketing_support_project_members_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createServices(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_services
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

                code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT NULL,

                sort_order INT
                    NOT NULL DEFAULT 0,

                is_default TINYINT(1)
                    NOT NULL DEFAULT 0,

                is_active TINYINT(1)
                    NOT NULL DEFAULT 1,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_support_services_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_support_services_project_code_unique
                    (
                        project_id,
                        code
                    ),

                UNIQUE KEY
                    ticketing_support_services_project_id_unique
                    (
                        project_id,
                        id
                    ),

                INDEX
                    ticketing_support_services_active_sort_index
                    (
                        project_id,
                        is_active,
                        sort_order
                    ),

                CONSTRAINT
                    ticketing_support_services_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function extendTickets(): void
    {
        if (
            !$this->columnExists(
                'ticketing_tickets',
                'support_project_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD COLUMN support_project_id
                    BIGINT UNSIGNED NULL
                    AFTER public_reference
            ");
        }

        if (
            !$this->columnExists(
                'ticketing_tickets',
                'support_service_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD COLUMN support_service_id
                    BIGINT UNSIGNED NULL
                    AFTER support_project_id
            ");
        }

        if (
            !$this->columnExists(
                'ticketing_tickets',
                'support_project_title_snapshot'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD COLUMN support_project_title_snapshot
                    VARCHAR(255) NULL
                    AFTER support_service_id
            ");
        }

        if (
            !$this->columnExists(
                'ticketing_tickets',
                'support_service_title_snapshot'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD COLUMN support_service_title_snapshot
                    VARCHAR(255) NULL
                    AFTER support_project_title_snapshot
            ");
        }

        if (
            !$this->indexExists(
                'ticketing_tickets',
                'ticketing_tickets_project_service_status_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD INDEX
                    ticketing_tickets_project_service_status_index
                    (
                        support_project_id,
                        support_service_id,
                        status_code,
                        last_activity_at
                    )
            ");
        }
    }


    private function ensureTicketConstraints(): void
    {
        if (
            !$this->constraintExists(
                'ticketing_tickets',
                'ticketing_tickets_support_project_fk'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD CONSTRAINT
                    ticketing_tickets_support_project_fk
                FOREIGN KEY (support_project_id)
                REFERENCES ticketing_support_projects (id)
                ON DELETE RESTRICT
                ON UPDATE RESTRICT
            ");
        }

        if (
            !$this->constraintExists(
                'ticketing_tickets',
                'ticketing_tickets_support_service_fk'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD CONSTRAINT
                    ticketing_tickets_support_service_fk
                FOREIGN KEY
                (
                    support_project_id,
                    support_service_id
                )
                REFERENCES ticketing_support_services
                (
                    project_id,
                    id
                )
                ON DELETE RESTRICT
                ON UPDATE RESTRICT
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


    private function constraintExists(
        string $table,
        string $constraint
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.table_constraints
                WHERE constraint_schema = DATABASE()
                  AND table_name = ?
                  AND constraint_name = ?
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
