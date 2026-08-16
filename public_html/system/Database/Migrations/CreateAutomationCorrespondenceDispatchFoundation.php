<?php

namespace IPKF\Database\Migrations;

class CreateAutomationCorrespondenceDispatchFoundation extends Migration
{
    public function up(): void
    {
        $this->createDispatches();
        $this->createDispatchAttempts();
        $this->createDispatchFollowups();
    }

    public function down(): void
    {
    }

    private function createDispatches(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_dispatches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                correspondence_id
                    BIGINT UNSIGNED NOT NULL,

                correspondence_party_id
                    BIGINT UNSIGNED NULL,

                root_organization_id
                    BIGINT UNSIGNED NULL,

                organization_id
                    BIGINT UNSIGNED NULL,

                secretariat_desk_id
                    BIGINT UNSIGNED NULL,

                target_kind_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'external',

                external_organization_id
                    BIGINT UNSIGNED NULL,

                external_organization_public_reference
                    CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                external_contact_point_id
                    BIGINT UNSIGNED NULL,

                external_contact_point_public_reference
                    CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                channel_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                target_snapshot_json LONGTEXT NOT NULL,
                source_snapshot_json LONGTEXT NOT NULL,
                destination_snapshot_json LONGTEXT NOT NULL,

                status_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'pending',

                tracking_code VARCHAR(190) NULL,
                provider_reference VARCHAR(190) NULL,

                requested_by_user_id
                    BIGINT UNSIGNED NOT NULL,

                requested_appointment_reference
                    CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                actor_context_snapshot_json LONGTEXT NULL,

                requested_at TIMESTAMP NOT NULL,
                dispatched_at TIMESTAMP NULL,
                delivered_at TIMESTAMP NULL,
                failed_at TIMESTAMP NULL,
                cancelled_at TIMESTAMP NULL,

                failure_code VARCHAR(100) NULL,
                failure_message TEXT NULL,

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY corr_dispatch_public_ref_unique (
                    public_reference
                ),

                INDEX corr_dispatch_corr_index (
                    correspondence_id
                ),

                INDEX corr_dispatch_party_index (
                    correspondence_party_id
                ),

                INDEX corr_dispatch_status_index (
                    status_code
                ),

                INDEX corr_dispatch_channel_index (
                    channel_code
                ),

                INDEX corr_dispatch_corr_status_index (
                    correspondence_id,
                    status_code
                ),

                INDEX corr_dispatch_external_org_index (
                    external_organization_id
                ),

                CONSTRAINT corr_dispatch_corr_fk
                    FOREIGN KEY (
                        correspondence_id
                    )
                    REFERENCES correspondences(id)
                    ON DELETE CASCADE,

                CONSTRAINT corr_dispatch_party_fk
                    FOREIGN KEY (
                        correspondence_party_id
                    )
                    REFERENCES correspondence_parties(id)
                    ON DELETE SET NULL
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createDispatchAttempts(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_dispatch_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                dispatch_id
                    BIGINT UNSIGNED NOT NULL,

                attempt_number
                    INT UNSIGNED NOT NULL,

                provider_code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                provider_reference VARCHAR(190) NULL,

                status_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'pending',

                destination_snapshot_json LONGTEXT NOT NULL,
                response_metadata_json LONGTEXT NULL,

                requested_at TIMESTAMP NOT NULL,
                completed_at TIMESTAMP NULL,

                failure_code VARCHAR(100) NULL,
                failure_message TEXT NULL,

                created_at TIMESTAMP NULL,

                UNIQUE KEY corr_dispatch_attempt_unique (
                    dispatch_id,
                    attempt_number
                ),

                INDEX corr_dispatch_attempt_status_index (
                    status_code
                ),

                INDEX corr_dispatch_attempt_provider_index (
                    provider_code
                ),

                CONSTRAINT corr_dispatch_attempt_dispatch_fk
                    FOREIGN KEY (
                        dispatch_id
                    )
                    REFERENCES correspondence_dispatches(id)
                    ON DELETE CASCADE
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createDispatchFollowups(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_dispatch_followups (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                dispatch_id
                    BIGINT UNSIGNED NOT NULL,

                followup_type_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'destination_registration',

                status_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'pending',

                due_at TIMESTAMP NULL,
                contacted_at TIMESTAMP NULL,

                contacted_person_name VARCHAR(255) NULL,
                contacted_person_title VARCHAR(255) NULL,

                contact_snapshot_json LONGTEXT NULL,

                destination_registration_number
                    VARCHAR(190) NULL,

                destination_registration_date
                    DATE NULL,

                delivery_reference VARCHAR(190) NULL,

                result_note TEXT NULL,

                completed_by_user_id
                    BIGINT UNSIGNED NULL,

                completed_at TIMESTAMP NULL,

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                INDEX corr_dispatch_followup_dispatch_index (
                    dispatch_id
                ),

                INDEX corr_dispatch_followup_status_index (
                    status_code
                ),

                INDEX corr_dispatch_followup_due_index (
                    status_code,
                    due_at
                ),

                CONSTRAINT corr_dispatch_followup_dispatch_fk
                    FOREIGN KEY (
                        dispatch_id
                    )
                    REFERENCES correspondence_dispatches(id)
                    ON DELETE CASCADE
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }
}
