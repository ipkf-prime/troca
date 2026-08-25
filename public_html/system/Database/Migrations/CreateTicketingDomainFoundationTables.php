<?php

namespace IPKF\Database\Migrations;

class CreateTicketingDomainFoundationTables extends Migration
{
    public function up(): void
    {
        foreach ($this->statements() as $statement) {
            $this->db->exec($statement);
        }
    }

    public function down(): void
    {
    }

    private function statements(): array
    {
        $options =
            'ENGINE=InnoDB '
            . 'DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci';

        return [

            /*
             * Ticket lifecycle statuses.
             */
            "CREATE TABLE IF NOT EXISTS ticketing_statuses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(190) NOT NULL,

                category VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'open',

                color CHAR(7)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                sort_order INT NOT NULL DEFAULT 0,

                is_closed TINYINT(1)
                    NOT NULL DEFAULT 0,

                is_system TINYINT(1)
                    NOT NULL DEFAULT 0,

                is_active TINYINT(1)
                    NOT NULL DEFAULT 1,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_statuses_code_unique
                    (code),

                INDEX
                    ticketing_statuses_active_sort_index
                    (is_active, sort_order)
            ) {$options}",


            /*
             * Priority reference data.
             */
            "CREATE TABLE IF NOT EXISTS ticketing_priorities (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(190) NOT NULL,

                severity SMALLINT UNSIGNED
                    NOT NULL DEFAULT 20,

                color CHAR(7)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                sort_order INT NOT NULL DEFAULT 0,

                is_system TINYINT(1)
                    NOT NULL DEFAULT 0,

                is_active TINYINT(1)
                    NOT NULL DEFAULT 1,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_priorities_code_unique
                    (code),

                INDEX
                    ticketing_priorities_active_sort_index
                    (is_active, sort_order)
            ) {$options}",


            /*
             * Local Ticketing category hierarchy.
             */
            "CREATE TABLE IF NOT EXISTS ticketing_categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                parent_id BIGINT UNSIGNED NULL,

                code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255) NOT NULL,

                description TEXT NULL,

                sort_order INT NOT NULL DEFAULT 0,

                is_active TINYINT(1)
                    NOT NULL DEFAULT 1,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_categories_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_categories_code_unique
                    (code),

                INDEX
                    ticketing_categories_parent_sort_index
                    (parent_id, sort_order),

                CONSTRAINT
                    ticketing_categories_parent_fk
                    FOREIGN KEY (parent_id)
                    REFERENCES ticketing_categories (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            ) {$options}",


            /*
             * Root Ticket aggregate.
             *
             * Core identities are referenced only through
             * stable public references + snapshots.
             * No cross-database foreign key is permitted.
             */
            "CREATE TABLE IF NOT EXISTS ticketing_tickets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                status_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'new',

                priority_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'normal',

                category_id BIGINT UNSIGNED NULL,

                subject VARCHAR(500) NOT NULL,

                requester_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                requester_person_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                requester_display_name_snapshot
                    VARCHAR(255)
                    NOT NULL,

                requester_email_snapshot
                    VARCHAR(255)
                    NULL,

                requester_mobile_snapshot VARCHAR(50)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                requester_organization_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                requester_organization_snapshot
                    VARCHAR(255)
                    NULL,

                source_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'portal',

                source_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                updated_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                last_activity_at DATETIME
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                first_response_at DATETIME NULL,

                resolved_at DATETIME NULL,

                closed_at DATETIME NULL,

                archived_at DATETIME NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_tickets_reference_unique
                    (public_reference),

                INDEX
                    ticketing_tickets_status_activity_index
                    (status_code, last_activity_at),

                INDEX
                    ticketing_tickets_priority_activity_index
                    (priority_code, last_activity_at),

                INDEX
                    ticketing_tickets_category_status_index
                    (category_id, status_code),

                INDEX
                    ticketing_tickets_requester_index
                    (
                        requester_user_reference,
                        status_code
                    ),

                INDEX
                    ticketing_tickets_source_index
                    (
                        source_code,
                        source_reference
                    ),

                CONSTRAINT
                    ticketing_tickets_status_fk
                    FOREIGN KEY (status_code)
                    REFERENCES ticketing_statuses (code)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_tickets_priority_fk
                    FOREIGN KEY (priority_code)
                    REFERENCES ticketing_priorities (code)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_tickets_category_fk
                    FOREIGN KEY (category_id)
                    REFERENCES ticketing_categories (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            ) {$options}",


            /*
             * Assignment history.
             */
            "CREATE TABLE IF NOT EXISTS ticketing_assignments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                ticket_id BIGINT UNSIGNED NOT NULL,

                assignee_kind VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'user',

                assignee_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                assignee_display_name_snapshot
                    VARCHAR(255)
                    NOT NULL,

                assignment_role VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'owner',

                assigned_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                assigned_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                unassigned_at TIMESTAMP NULL,

                INDEX
                    ticketing_assignments_ticket_active_index
                    (
                        ticket_id,
                        unassigned_at
                    ),

                INDEX
                    ticketing_assignments_assignee_index
                    (
                        assignee_kind,
                        assignee_reference,
                        unassigned_at
                    ),

                CONSTRAINT
                    ticketing_assignments_ticket_fk
                    FOREIGN KEY (ticket_id)
                    REFERENCES ticketing_tickets (id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            ) {$options}",


            /*
             * Ticket conversation stream.
             *
             * public   = requester visible
             * internal = private support note
             * system   = generated system message
             */
            "CREATE TABLE IF NOT EXISTS ticketing_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                ticket_id BIGINT UNSIGNED NOT NULL,

                message_kind VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'reply',

                visibility_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'public',

                author_kind VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'requester',

                author_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                author_display_name_snapshot
                    VARCHAR(255)
                    NOT NULL,

                body LONGTEXT NOT NULL,

                source_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'portal',

                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_messages_reference_unique
                    (public_reference),

                INDEX
                    ticketing_messages_ticket_time_index
                    (
                        ticket_id,
                        created_at
                    ),

                INDEX
                    ticketing_messages_visibility_index
                    (
                        ticket_id,
                        visibility_code,
                        created_at
                    ),

                CONSTRAINT
                    ticketing_messages_ticket_fk
                    FOREIGN KEY (ticket_id)
                    REFERENCES ticketing_tickets (id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            ) {$options}",


            /*
             * Attachment metadata only.
             * Binary content remains outside DB.
             */
            "CREATE TABLE IF NOT EXISTS ticketing_attachments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                ticket_id BIGINT UNSIGNED NOT NULL,

                message_id BIGINT UNSIGNED NULL,

                storage_disk VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                storage_key VARCHAR(1000)
                    NOT NULL,

                original_name VARCHAR(500)
                    NOT NULL,

                mime_type VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                size_bytes BIGINT UNSIGNED
                    NOT NULL,

                checksum_sha256 CHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                scan_status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'pending',

                uploaded_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                deleted_at TIMESTAMP NULL,

                created_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_attachments_reference_unique
                    (public_reference),

                INDEX
                    ticketing_attachments_ticket_index
                    (
                        ticket_id,
                        created_at
                    ),

                INDEX
                    ticketing_attachments_message_index
                    (
                        message_id,
                        created_at
                    ),

                INDEX
                    ticketing_attachments_scan_index
                    (
                        scan_status_code,
                        created_at
                    ),

                CONSTRAINT
                    ticketing_attachments_ticket_fk
                    FOREIGN KEY (ticket_id)
                    REFERENCES ticketing_tickets (id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_attachments_message_fk
                    FOREIGN KEY (message_id)
                    REFERENCES ticketing_messages (id)
                    ON DELETE SET NULL
                    ON UPDATE RESTRICT
            ) {$options}",


            /*
             * Immutable Ticket aggregate event stream.
             *
             * No updated_at column by design.
             */
            "CREATE TABLE IF NOT EXISTS ticketing_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                ticket_id BIGINT UNSIGNED NOT NULL,

                event_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                actor_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                actor_display_name_snapshot
                    VARCHAR(255)
                    NULL,

                previous_status_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                resulting_status_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                payload_json LONGTEXT NULL,

                occurred_at TIMESTAMP
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_events_reference_unique
                    (public_reference),

                INDEX
                    ticketing_events_ticket_time_index
                    (
                        ticket_id,
                        occurred_at
                    ),

                INDEX
                    ticketing_events_code_time_index
                    (
                        event_code,
                        occurred_at
                    ),

                CONSTRAINT
                    ticketing_events_ticket_fk
                    FOREIGN KEY (ticket_id)
                    REFERENCES ticketing_tickets (id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            ) {$options}",
        ];
    }
}
