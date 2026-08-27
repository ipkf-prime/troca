<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class CreateTicketingDynamicTopicRoutingFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createTopics();
        $this->createRoutingRules();
        $this->extendTickets();
    }


    public function down(): void
    {
        /*
         * Non-destructive.
         *
         * Topic and routing snapshots form part of
         * operational ticket history.
         */
    }


    private function createTopics(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_topics
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

                service_id BIGINT UNSIGNED
                    NULL,

                parent_topic_id BIGINT UNSIGNED
                    NULL,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT NULL,

                is_selectable TINYINT(1)
                    NOT NULL DEFAULT 1,

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
                    ticketing_topics_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_topics_project_code_unique
                    (
                        project_id,
                        code
                    ),

                INDEX
                    ticketing_topics_project_service_index
                    (
                        project_id,
                        service_id,
                        status,
                        sort_order
                    ),

                INDEX
                    ticketing_topics_parent_index
                    (
                        parent_topic_id,
                        status
                    ),

                CONSTRAINT
                    ticketing_topics_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_topics_service_fk
                    FOREIGN KEY (service_id)
                    REFERENCES ticketing_support_services(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_topics_parent_fk
                    FOREIGN KEY (parent_topic_id)
                    REFERENCES ticketing_support_topics(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createRoutingRules(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_support_routing_rules
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

                service_id BIGINT UNSIGNED
                    NULL,

                topic_id BIGINT UNSIGNED
                    NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT NULL,

                scope_type_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'all',

                scope_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                target_layer_id BIGINT UNSIGNED
                    NOT NULL,

                target_node_id BIGINT UNSIGNED
                    NOT NULL,

                target_queue_id BIGINT UNSIGNED
                    NOT NULL,

                target_team_id BIGINT UNSIGNED
                    NOT NULL,

                fixed_project_member_id BIGINT UNSIGNED
                    NULL,

                assignment_mode_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'inherit',

                priority INT
                    NOT NULL DEFAULT 100,

                stop_processing TINYINT(1)
                    NOT NULL DEFAULT 1,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                sort_order INT
                    NOT NULL DEFAULT 0,

                matcher_json LONGTEXT NULL,

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_routing_rules_reference_unique
                    (public_reference),

                INDEX
                    ticketing_routing_rules_match_index
                    (
                        project_id,
                        service_id,
                        topic_id,
                        scope_type_code,
                        status,
                        priority
                    ),

                INDEX
                    ticketing_routing_rules_target_index
                    (
                        target_layer_id,
                        target_node_id,
                        target_queue_id,
                        target_team_id
                    ),

                CONSTRAINT
                    ticketing_routing_rules_project_fk
                    FOREIGN KEY (project_id)
                    REFERENCES ticketing_support_projects(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_routing_rules_service_fk
                    FOREIGN KEY (service_id)
                    REFERENCES ticketing_support_services(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_routing_rules_topic_fk
                    FOREIGN KEY (topic_id)
                    REFERENCES ticketing_support_topics(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_routing_rules_layer_fk
                    FOREIGN KEY (target_layer_id)
                    REFERENCES ticketing_support_layers(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_routing_rules_node_fk
                    FOREIGN KEY (target_node_id)
                    REFERENCES ticketing_support_nodes(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_routing_rules_queue_fk
                    FOREIGN KEY (target_queue_id)
                    REFERENCES ticketing_support_queues(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_routing_rules_team_fk
                    FOREIGN KEY (target_team_id)
                    REFERENCES ticketing_support_teams(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_routing_rules_fixed_member_fk
                    FOREIGN KEY (fixed_project_member_id)
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
            'support_topic_id' =>
                'BIGINT UNSIGNED NULL',

            'support_topic_title_snapshot' =>
                'VARCHAR(255) NULL',

            'matched_routing_rule_id' =>
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


        if (!$this->indexExists(
            'ticketing_tickets',
            'ticketing_tickets_topic_index'
        )) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD INDEX
                    ticketing_tickets_topic_index
                    (
                        support_topic_id,
                        status_code
                    )
            ");
        }


        if (!$this->indexExists(
            'ticketing_tickets',
            'ticketing_tickets_routing_rule_index'
        )) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD INDEX
                    ticketing_tickets_routing_rule_index
                    (
                        matched_routing_rule_id,
                        status_code
                    )
            ");
        }


        if (!$this->constraintExists(
            'ticketing_tickets',
            'ticketing_tickets_topic_fk'
        )) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets

                ADD CONSTRAINT
                    ticketing_tickets_topic_fk

                FOREIGN KEY (support_topic_id)
                REFERENCES ticketing_support_topics(id)

                ON DELETE RESTRICT
                ON UPDATE RESTRICT
            ");
        }


        if (!$this->constraintExists(
            'ticketing_tickets',
            'ticketing_tickets_routing_rule_fk'
        )) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets

                ADD CONSTRAINT
                    ticketing_tickets_routing_rule_fk

                FOREIGN KEY (matched_routing_rule_id)
                REFERENCES ticketing_support_routing_rules(id)

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
