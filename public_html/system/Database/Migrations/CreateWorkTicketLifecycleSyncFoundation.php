<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * Dynamic Work -> Ticket lifecycle synchronization foundation.
 *
 * This migration deliberately seeds NO mapping rows.
 * Therefore the default behavior after migration is still NO synchronization.
 *
 * A rule maps a resulting Work status to a canonical Ticket lifecycle action.
 * The mapping may be global or restricted to one Work project and/or one
 * source-link relation type.
 *
 * The attempt table is both audit history and retry/idempotency foundation.
 */
final class CreateWorkTicketLifecycleSyncFoundation extends Migration
{
    public function up(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS work_ticket_lifecycle_sync_rules (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_reference VARCHAR(64) NOT NULL,

                work_project_id BIGINT UNSIGNED NULL,
                work_status_id BIGINT UNSIGNED NOT NULL,
                relation_type_code VARCHAR(64) NULL,

                ticket_action_code VARCHAR(32) NOT NULL,

                rule_priority INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,

                created_by_user_reference VARCHAR(128) NULL,
                updated_by_user_reference VARCHAR(128) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),

                UNIQUE KEY work_ticket_lifecycle_sync_rules_reference_unique (
                    public_reference
                ),

                INDEX work_ticket_lifecycle_sync_rules_status_index (
                    work_status_id,
                    is_active,
                    rule_priority
                ),

                INDEX work_ticket_lifecycle_sync_rules_project_index (
                    work_project_id,
                    is_active,
                    rule_priority
                ),

                CONSTRAINT work_ticket_lifecycle_sync_rules_project_fk
                    FOREIGN KEY (work_project_id)
                    REFERENCES work_projects (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT work_ticket_lifecycle_sync_rules_status_fk
                    FOREIGN KEY (work_status_id)
                    REFERENCES work_statuses (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS work_ticket_lifecycle_sync_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_reference VARCHAR(64) NOT NULL,

                idempotency_key VARCHAR(190) NOT NULL,
                correlation_reference VARCHAR(100) NOT NULL,

                rule_id BIGINT UNSIGNED NOT NULL,
                work_item_id BIGINT UNSIGNED NOT NULL,
                work_activity_event_id BIGINT UNSIGNED NOT NULL,

                source_link_id BIGINT UNSIGNED NOT NULL,
                source_link_reference VARCHAR(64) NOT NULL,
                ticket_reference VARCHAR(191) NOT NULL,

                previous_work_status_code VARCHAR(64) NOT NULL,
                resulting_work_status_code VARCHAR(64) NOT NULL,
                ticket_action_code VARCHAR(32) NOT NULL,

                result_code VARCHAR(32) NOT NULL,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,

                actor_user_reference VARCHAR(128) NOT NULL,

                ticket_previous_status_code VARCHAR(64) NULL,
                ticket_resulting_status_code VARCHAR(64) NULL,
                ticket_event_reference VARCHAR(64) NULL,

                error_code VARCHAR(100) NULL,
                metadata_json LONGTEXT NULL,

                first_attempted_at DATETIME NULL,
                last_attempted_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),

                UNIQUE KEY work_ticket_lifecycle_sync_attempts_reference_unique (
                    public_reference
                ),

                UNIQUE KEY work_ticket_lifecycle_sync_attempts_idempotency_unique (
                    idempotency_key
                ),

                INDEX work_ticket_lifecycle_sync_attempts_correlation_index (
                    correlation_reference
                ),

                INDEX work_ticket_lifecycle_sync_attempts_rule_index (
                    rule_id,
                    result_code
                ),

                INDEX work_ticket_lifecycle_sync_attempts_item_index (
                    work_item_id,
                    work_activity_event_id
                ),

                INDEX work_ticket_lifecycle_sync_attempts_ticket_index (
                    ticket_reference,
                    result_code
                ),

                CONSTRAINT work_ticket_lifecycle_sync_attempts_rule_fk
                    FOREIGN KEY (rule_id)
                    REFERENCES work_ticket_lifecycle_sync_rules (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT work_ticket_lifecycle_sync_attempts_item_fk
                    FOREIGN KEY (work_item_id)
                    REFERENCES work_items (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT work_ticket_lifecycle_sync_attempts_event_fk
                    FOREIGN KEY (work_activity_event_id)
                    REFERENCES work_activity_events (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT work_ticket_lifecycle_sync_attempts_source_link_fk
                    FOREIGN KEY (source_link_id)
                    REFERENCES work_item_source_links (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(): void
    {
        $this->db->exec(
            'DROP TABLE IF EXISTS work_ticket_lifecycle_sync_attempts'
        );

        $this->db->exec(
            'DROP TABLE IF EXISTS work_ticket_lifecycle_sync_rules'
        );
    }
}
