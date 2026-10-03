<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

class CreateWorkTicketPolicyFoundation extends Migration
{
    public function up(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS work_ticket_destination_rules (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_reference VARCHAR(64) NOT NULL,

                ticketing_project_reference VARCHAR(191) NOT NULL,
                ticketing_subdomain_reference VARCHAR(191) NULL,
                ticketing_service_reference VARCHAR(191) NULL,
                ticketing_topic_reference VARCHAR(191) NULL,
                ticketing_layer_reference VARCHAR(191) NULL,
                ticketing_node_reference VARCHAR(191) NULL,
                ticketing_queue_reference VARCHAR(191) NULL,
                ticketing_team_reference VARCHAR(191) NULL,

                work_project_id BIGINT UNSIGNED NOT NULL,

                default_item_type VARCHAR(30) NULL,
                default_status_id BIGINT UNSIGNED NULL,
                default_priority_code VARCHAR(20) NULL,
                default_assignee_reference VARCHAR(100) NULL,

                rule_priority INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,

                created_by_user_reference VARCHAR(128) NULL,
                updated_by_user_reference VARCHAR(128) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),
                UNIQUE KEY work_ticket_destination_rules_reference_unique
                    (public_reference),

                INDEX work_ticket_destination_rules_match_index (
                    ticketing_project_reference,
                    is_active,
                    rule_priority
                ),

                INDEX work_ticket_destination_rules_work_project_index (
                    work_project_id,
                    is_active
                ),

                CONSTRAINT work_ticket_destination_rules_project_fk
                    FOREIGN KEY (work_project_id)
                    REFERENCES work_projects (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT work_ticket_destination_rules_status_fk
                    FOREIGN KEY (default_status_id)
                    REFERENCES work_statuses (id)
                    ON DELETE SET NULL
                    ON UPDATE RESTRICT
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS work_ticket_access_rules (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_reference VARCHAR(64) NOT NULL,

                ticketing_project_reference VARCHAR(191) NOT NULL,
                ticketing_subdomain_reference VARCHAR(191) NULL,
                ticketing_service_reference VARCHAR(191) NULL,
                ticketing_topic_reference VARCHAR(191) NULL,
                ticketing_layer_reference VARCHAR(191) NULL,
                ticketing_node_reference VARCHAR(191) NULL,
                ticketing_queue_reference VARCHAR(191) NULL,
                ticketing_team_reference VARCHAR(191) NULL,

                work_project_id BIGINT UNSIGNED NULL,

                principal_type_code VARCHAR(40) NOT NULL,
                principal_reference VARCHAR(191) NULL,

                allow_tab_view TINYINT(1) NULL,
                allow_links_view TINYINT(1) NULL,
                allow_item_open TINYINT(1) NULL,
                allow_item_create_from_ticket TINYINT(1) NULL,
                allow_project_select TINYINT(1) NULL,

                rule_priority INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,

                created_by_user_reference VARCHAR(128) NULL,
                updated_by_user_reference VARCHAR(128) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),
                UNIQUE KEY work_ticket_access_rules_reference_unique
                    (public_reference),

                INDEX work_ticket_access_rules_match_index (
                    ticketing_project_reference,
                    is_active,
                    rule_priority
                ),

                INDEX work_ticket_access_rules_principal_index (
                    principal_type_code,
                    principal_reference,
                    is_active
                ),

                INDEX work_ticket_access_rules_work_project_index (
                    work_project_id,
                    is_active
                ),

                CONSTRAINT work_ticket_access_rules_project_fk
                    FOREIGN KEY (work_project_id)
                    REFERENCES work_projects (id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(): void
    {
        $this->db->exec(
            'DROP TABLE IF EXISTS work_ticket_access_rules'
        );

        $this->db->exec(
            'DROP TABLE IF EXISTS work_ticket_destination_rules'
        );
    }
}
