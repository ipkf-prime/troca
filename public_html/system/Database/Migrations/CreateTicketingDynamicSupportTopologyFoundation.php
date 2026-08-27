<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class CreateTicketingDynamicSupportTopologyFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createLayers();
        $this->createNodes();
        $this->createNodeRelations();
        $this->createTeams();
        $this->createTeamNodes();
        $this->createQueues();
        $this->createTeamQueues();
        $this->createTeamMembers();

        $this->extendTickets();
        $this->extendAssignments();
    }


    public function down(): void
    {
        /*
         * Non-destructive.
         *
         * Topology and assignment references form part of
         * the operational and audit history of tickets.
         */
    }


    private function createLayers(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_layers
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

                rank_order INT UNSIGNED
                    NOT NULL,

                can_observe_descendants TINYINT(1)
                    NOT NULL DEFAULT 0,

                can_assist_descendants TINYINT(1)
                    NOT NULL DEFAULT 0,

                can_takeover_descendants TINYINT(1)
                    NOT NULL DEFAULT 0,

                can_transfer_downward TINYINT(1)
                    NOT NULL DEFAULT 0,

                is_entry_layer TINYINT(1)
                    NOT NULL DEFAULT 0,

                is_terminal_layer TINYINT(1)
                    NOT NULL DEFAULT 0,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                sort_order INT
                    NOT NULL DEFAULT 0,

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_layers_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_layers_project_code_unique
                    (
                        project_id,
                        code
                    ),

                UNIQUE KEY
                    ticketing_layers_project_rank_unique
                    (
                        project_id,
                        rank_order
                    ),

                INDEX
                    ticketing_layers_project_status_index
                    (
                        project_id,
                        status,
                        rank_order
                    ),

                CONSTRAINT
                    ticketing_layers_project_fk
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


    private function createNodes(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_nodes
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

                layer_id BIGINT UNSIGNED
                    NOT NULL,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT NULL,

                node_kind_code VARCHAR(50)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'support',

                core_organization_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                scope_type_code VARCHAR(50)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                scope_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                is_intake_node TINYINT(1)
                    NOT NULL DEFAULT 0,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                sort_order INT
                    NOT NULL DEFAULT 0,

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_nodes_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_nodes_project_code_unique
                    (
                        project_id,
                        code
                    ),

                INDEX
                    ticketing_nodes_project_layer_index
                    (
                        project_id,
                        layer_id,
                        status,
                        sort_order
                    ),

                INDEX
                    ticketing_nodes_organization_index
                    (
                        core_organization_reference,
                        status
                    ),

                INDEX
                    ticketing_nodes_scope_index
                    (
                        scope_type_code,
                        scope_reference,
                        status
                    ),

                CONSTRAINT
                    ticketing_nodes_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_nodes_layer_fk
                    FOREIGN KEY (layer_id)
                    REFERENCES ticketing_support_layers(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createNodeRelations(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_node_relations
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

                parent_node_id BIGINT UNSIGNED
                    NOT NULL,

                child_node_id BIGINT UNSIGNED
                    NOT NULL,

                relation_type_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'hierarchy',

                is_primary_path TINYINT(1)
                    NOT NULL DEFAULT 1,

                allow_escalation TINYINT(1)
                    NOT NULL DEFAULT 1,

                allow_downward_transfer TINYINT(1)
                    NOT NULL DEFAULT 1,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                sort_order INT
                    NOT NULL DEFAULT 0,

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_node_rel_ref_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_node_rel_unique
                    (
                        project_id,
                        parent_node_id,
                        child_node_id,
                        relation_type_code
                    ),

                INDEX
                    ticketing_node_rel_parent_index
                    (
                        parent_node_id,
                        status
                    ),

                INDEX
                    ticketing_node_rel_child_index
                    (
                        child_node_id,
                        status
                    ),

                CONSTRAINT
                    ticketing_node_rel_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_node_rel_parent_fk
                    FOREIGN KEY (parent_node_id)
                    REFERENCES ticketing_support_nodes(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_node_rel_child_fk
                    FOREIGN KEY (child_node_id)
                    REFERENCES ticketing_support_nodes(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createTeams(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_teams
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

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT NULL,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                sort_order INT
                    NOT NULL DEFAULT 0,

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_teams_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_teams_project_code_unique
                    (
                        project_id,
                        code
                    ),

                INDEX
                    ticketing_teams_project_status_index
                    (
                        project_id,
                        status,
                        sort_order
                    ),

                CONSTRAINT
                    ticketing_teams_project_fk
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


    private function createTeamNodes(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_team_nodes
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                team_id BIGINT UNSIGNED
                    NOT NULL,

                node_id BIGINT UNSIGNED
                    NOT NULL,

                service_role_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'primary',

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_team_nodes_unique
                    (
                        team_id,
                        node_id,
                        service_role_code
                    ),

                INDEX
                    ticketing_team_nodes_node_index
                    (
                        node_id,
                        status
                    ),

                CONSTRAINT
                    ticketing_team_nodes_team_fk
                    FOREIGN KEY (team_id)
                    REFERENCES ticketing_support_teams(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_team_nodes_node_fk
                    FOREIGN KEY (node_id)
                    REFERENCES ticketing_support_nodes(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createQueues(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_queues
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

                node_id BIGINT UNSIGNED
                    NOT NULL,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT NULL,

                queue_type_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'work',

                assignment_mode_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'manual',

                max_open_per_agent INT UNSIGNED
                    NULL,

                is_default TINYINT(1)
                    NOT NULL DEFAULT 0,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                sort_order INT
                    NOT NULL DEFAULT 0,

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_queues_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_queues_project_code_unique
                    (
                        project_id,
                        code
                    ),

                INDEX
                    ticketing_queues_node_status_index
                    (
                        node_id,
                        status,
                        sort_order
                    ),

                INDEX
                    ticketing_queues_assignment_mode_index
                    (
                        assignment_mode_code,
                        status
                    ),

                CONSTRAINT
                    ticketing_queues_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_queues_node_fk
                    FOREIGN KEY (node_id)
                    REFERENCES ticketing_support_nodes(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createTeamQueues(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_team_queues
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                team_id BIGINT UNSIGNED
                    NOT NULL,

                queue_id BIGINT UNSIGNED
                    NOT NULL,

                service_role_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'owner',

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_team_queues_unique
                    (
                        team_id,
                        queue_id,
                        service_role_code
                    ),

                INDEX
                    ticketing_team_queues_queue_index
                    (
                        queue_id,
                        status
                    ),

                CONSTRAINT
                    ticketing_team_queues_team_fk
                    FOREIGN KEY (team_id)
                    REFERENCES ticketing_support_teams(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_team_queues_queue_fk
                    FOREIGN KEY (queue_id)
                    REFERENCES ticketing_support_queues(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createTeamMembers(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_team_members
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                team_id BIGINT UNSIGNED
                    NOT NULL,

                project_member_id BIGINT UNSIGNED
                    NOT NULL,

                staff_role_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'agent',

                workload_weight DECIMAL(8,4)
                    NOT NULL DEFAULT 1.0000,

                can_assign TINYINT(1)
                    NOT NULL DEFAULT 0,

                can_observe TINYINT(1)
                    NOT NULL DEFAULT 1,

                can_assist TINYINT(1)
                    NOT NULL DEFAULT 1,

                can_takeover TINYINT(1)
                    NOT NULL DEFAULT 0,

                can_transfer TINYINT(1)
                    NOT NULL DEFAULT 0,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                joined_at DATETIME
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                left_at DATETIME NULL,

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_team_members_unique
                    (
                        team_id,
                        project_member_id
                    ),

                INDEX
                    ticketing_team_members_member_index
                    (
                        project_member_id,
                        status,
                        left_at
                    ),

                INDEX
                    ticketing_team_members_role_index
                    (
                        team_id,
                        staff_role_code,
                        status
                    ),

                CONSTRAINT
                    ticketing_team_members_team_fk
                    FOREIGN KEY (team_id)
                    REFERENCES ticketing_support_teams(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_team_members_member_fk
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


    private function extendTickets(): void
    {
        $columns = [
            'current_support_layer_id' =>
                'BIGINT UNSIGNED NULL',

            'current_support_node_id' =>
                'BIGINT UNSIGNED NULL',

            'current_support_queue_id' =>
                'BIGINT UNSIGNED NULL',

            'current_support_team_id' =>
                'BIGINT UNSIGNED NULL',

            'current_assignee_project_member_id' =>
                'BIGINT UNSIGNED NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists(
                'ticketing_tickets',
                $column
            )) {
                $this->db->exec("
                    ALTER TABLE ticketing_tickets
                    ADD COLUMN {$column}
                        {$definition}
                ");
            }
        }

        $indexes = [
            'ticketing_tickets_current_layer_index' =>
                'current_support_layer_id, status_code',

            'ticketing_tickets_current_node_index' =>
                'current_support_node_id, status_code',

            'ticketing_tickets_current_queue_index' =>
                'current_support_queue_id, status_code',

            'ticketing_tickets_current_team_index' =>
                'current_support_team_id, status_code',

            'ticketing_tickets_current_assignee_index' =>
                'current_assignee_project_member_id, status_code',
        ];

        foreach ($indexes as $name => $columns) {
            if (!$this->indexExists(
                'ticketing_tickets',
                $name
            )) {
                $this->db->exec("
                    ALTER TABLE ticketing_tickets
                    ADD INDEX {$name}
                        ({$columns})
                ");
            }
        }

        $constraints = [
            'ticketing_tickets_current_layer_fk' => [
                'current_support_layer_id',
                'ticketing_support_layers',
            ],

            'ticketing_tickets_current_node_fk' => [
                'current_support_node_id',
                'ticketing_support_nodes',
            ],

            'ticketing_tickets_current_queue_fk' => [
                'current_support_queue_id',
                'ticketing_support_queues',
            ],

            'ticketing_tickets_current_team_fk' => [
                'current_support_team_id',
                'ticketing_support_teams',
            ],

            'ticketing_tickets_current_assignee_member_fk' => [
                'current_assignee_project_member_id',
                'ticketing_support_project_members',
            ],
        ];

        foreach (
            $constraints
            as $name => [$column, $table]
        ) {
            if (!$this->constraintExists(
                'ticketing_tickets',
                $name
            )) {
                $this->db->exec("
                    ALTER TABLE ticketing_tickets
                    ADD CONSTRAINT {$name}
                    FOREIGN KEY ({$column})
                    REFERENCES {$table}(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
                ");
            }
        }
    }


    private function extendAssignments(): void
    {
        $columns = [
            'project_member_id' =>
                'BIGINT UNSIGNED NULL',

            'support_node_id' =>
                'BIGINT UNSIGNED NULL',

            'support_queue_id' =>
                'BIGINT UNSIGNED NULL',

            'support_team_id' =>
                'BIGINT UNSIGNED NULL',

            'assignment_mode_code' =>
                "VARCHAR(40)
                 CHARACTER SET ascii
                 COLLATE ascii_bin
                 NULL",

            'assignment_reason' =>
                'TEXT NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists(
                'ticketing_assignments',
                $column
            )) {
                $this->db->exec("
                    ALTER TABLE ticketing_assignments
                    ADD COLUMN {$column}
                        {$definition}
                ");
            }
        }

        $indexes = [
            'ticketing_assignments_project_member_index' =>
                'project_member_id, unassigned_at',

            'ticketing_assignments_node_index' =>
                'support_node_id, unassigned_at',

            'ticketing_assignments_queue_index' =>
                'support_queue_id, unassigned_at',

            'ticketing_assignments_team_index' =>
                'support_team_id, unassigned_at',
        ];

        foreach ($indexes as $name => $columns) {
            if (!$this->indexExists(
                'ticketing_assignments',
                $name
            )) {
                $this->db->exec("
                    ALTER TABLE ticketing_assignments
                    ADD INDEX {$name}
                        ({$columns})
                ");
            }
        }

        $constraints = [
            'ticketing_assignments_project_member_fk' => [
                'project_member_id',
                'ticketing_support_project_members',
            ],

            'ticketing_assignments_node_fk' => [
                'support_node_id',
                'ticketing_support_nodes',
            ],

            'ticketing_assignments_queue_fk' => [
                'support_queue_id',
                'ticketing_support_queues',
            ],

            'ticketing_assignments_team_fk' => [
                'support_team_id',
                'ticketing_support_teams',
            ],
        ];

        foreach (
            $constraints
            as $name => [$column, $table]
        ) {
            if (!$this->constraintExists(
                'ticketing_assignments',
                $name
            )) {
                $this->db->exec("
                    ALTER TABLE ticketing_assignments
                    ADD CONSTRAINT {$name}
                    FOREIGN KEY ({$column})
                    REFERENCES {$table}(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
                ");
            }
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
