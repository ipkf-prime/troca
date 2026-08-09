<?php

namespace IPKF\Database\Migrations;

class CreateEnterpriseAutomationSecretariatFoundation extends Migration
{
    public function up(): void
    {
        $this->createSecretariatDesks();
        $this->createSecretariatDeskOrganizations();
        $this->createSecretariatDeskAppointments();
        $this->createRegistryPeriods();
        $this->createRegistryNumberSequences();

        $this->upgradeRegistryBooks();
        $this->createRegistryNumberReservations();

        $this->upgradeCorrespondences();
        $this->upgradeRegistrations();
        $this->upgradeReferrals();
        $this->upgradeEvents();

        $this->createCorrespondenceExchanges();
    }

    public function down(): void
    {
    }

    private function createSecretariatDesks(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS secretariat_desks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                root_organization_id BIGINT UNSIGNED NOT NULL,
                managing_organization_id BIGINT UNSIGNED NOT NULL,
                org_unit_id BIGINT UNSIGNED NULL,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title_fa VARCHAR(255) NOT NULL,
                title_en VARCHAR(255) NULL,

                desk_kind_code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'organization',

                supports_incoming TINYINT(1) NOT NULL DEFAULT 1,
                supports_outgoing TINYINT(1) NOT NULL DEFAULT 1,
                supports_internal TINYINT(1) NOT NULL DEFAULT 1,

                allow_cross_organization TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'active',

                created_by_user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY secretariat_desks_reference_unique (
                    public_reference
                ),

                UNIQUE KEY secretariat_desks_scope_code_unique (
                    root_organization_id,
                    managing_organization_id,
                    code
                ),

                INDEX secretariat_desks_root_index (
                    root_organization_id
                ),

                INDEX secretariat_desks_managing_org_index (
                    managing_organization_id
                ),

                INDEX secretariat_desks_unit_index (
                    org_unit_id
                ),

                INDEX secretariat_desks_kind_index (
                    desk_kind_code
                ),

                INDEX secretariat_desks_status_index (
                    status
                )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createSecretariatDeskOrganizations(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS secretariat_desk_organizations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                secretariat_desk_id BIGINT UNSIGNED NOT NULL,
                root_organization_id BIGINT UNSIGNED NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,

                organization_public_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                relation_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'service',

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                can_register_incoming TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                can_register_outgoing TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                can_register_internal TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'active',

                valid_from TIMESTAMP NULL,
                valid_until TIMESTAMP NULL,

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY secretariat_desk_org_unique (
                    secretariat_desk_id,
                    organization_id
                ),

                INDEX secretariat_desk_org_root_index (
                    root_organization_id
                ),

                INDEX secretariat_desk_org_org_index (
                    organization_id
                ),

                INDEX secretariat_desk_org_status_index (
                    status
                ),

                CONSTRAINT secretariat_desk_org_desk_fk
                    FOREIGN KEY (secretariat_desk_id)
                    REFERENCES secretariat_desks (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createSecretariatDeskAppointments(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS secretariat_desk_appointments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                secretariat_desk_id BIGINT UNSIGNED NOT NULL,
                root_organization_id BIGINT UNSIGNED NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,

                appointment_id BIGINT UNSIGNED NOT NULL,

                appointment_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                membership_role_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'operator',

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'active',

                valid_from TIMESTAMP NULL,
                valid_until TIMESTAMP NULL,

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY secretariat_desk_appointment_unique (
                    secretariat_desk_id,
                    appointment_id
                ),

                INDEX secretariat_desk_appointment_root_index (
                    root_organization_id
                ),

                INDEX secretariat_desk_appointment_org_index (
                    organization_id
                ),

                INDEX secretariat_desk_appointment_reference_index (
                    appointment_reference
                ),

                INDEX secretariat_desk_appointment_role_index (
                    membership_role_code
                ),

                INDEX secretariat_desk_appointment_status_index (
                    status
                ),

                CONSTRAINT secretariat_desk_appointment_desk_fk
                    FOREIGN KEY (secretariat_desk_id)
                    REFERENCES secretariat_desks (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createRegistryPeriods(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS registry_periods (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                root_organization_id BIGINT UNSIGNED NOT NULL,
                organization_id BIGINT UNSIGNED NULL,

                organization_scope_key BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        COALESCE(organization_id, 0)
                    ) PERSISTENT,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255) NOT NULL,

                starts_on DATE NOT NULL,
                ends_on DATE NOT NULL,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'active',

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY registry_periods_reference_unique (
                    public_reference
                ),

                UNIQUE KEY registry_periods_scope_code_unique (
                    root_organization_id,
                    organization_scope_key,
                    code
                ),

                INDEX registry_periods_root_index (
                    root_organization_id
                ),

                INDEX registry_periods_org_index (
                    organization_id
                ),

                INDEX registry_periods_dates_index (
                    starts_on,
                    ends_on
                ),

                INDEX registry_periods_status_index (
                    status
                )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createRegistryNumberSequences(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS registry_number_sequences (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                root_organization_id BIGINT UNSIGNED NOT NULL,
                organization_id BIGINT UNSIGNED NULL,

                organization_scope_key BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        COALESCE(organization_id, 0)
                    ) PERSISTENT,

                secretariat_desk_id BIGINT UNSIGNED NOT NULL,
                registry_period_id BIGINT UNSIGNED NOT NULL,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255) NOT NULL,

                prefix VARCHAR(50) NULL,
                suffix VARCHAR(50) NULL,

                format_pattern VARCHAR(255)
                    NOT NULL
                    DEFAULT '{prefix}{sequence}{suffix}',

                number_padding TINYINT UNSIGNED
                    NOT NULL
                    DEFAULT 5,

                next_sequence_number BIGINT UNSIGNED
                    NOT NULL
                    DEFAULT 1,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'active',

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY registry_sequences_reference_unique (
                    public_reference
                ),

                UNIQUE KEY registry_sequences_scope_code_unique (
                    root_organization_id,
                    organization_scope_key,
                    secretariat_desk_id,
                    registry_period_id,
                    code
                ),

                INDEX registry_sequences_root_index (
                    root_organization_id
                ),

                INDEX registry_sequences_org_index (
                    organization_id
                ),

                INDEX registry_sequences_desk_index (
                    secretariat_desk_id
                ),

                INDEX registry_sequences_period_index (
                    registry_period_id
                ),

                INDEX registry_sequences_status_index (
                    status
                ),

                CONSTRAINT registry_sequences_desk_fk
                    FOREIGN KEY (secretariat_desk_id)
                    REFERENCES secretariat_desks (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT registry_sequences_period_fk
                    FOREIGN KEY (registry_period_id)
                    REFERENCES registry_periods (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function upgradeRegistryBooks(): void
    {
        if (!$this->tableExists('registry_books')) {
            return;
        }

        $this->addColumnIfMissing(
            'registry_books',
            'root_organization_id',
            'BIGINT UNSIGNED NULL AFTER id'
        );

        $this->addColumnIfMissing(
            'registry_books',
            'organization_public_reference',
            'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER organization_id'
        );

        $this->addColumnIfMissing(
            'registry_books',
            'secretariat_desk_id',
            'BIGINT UNSIGNED NULL AFTER org_unit_id'
        );

        $this->addColumnIfMissing(
            'registry_books',
            'registry_period_id',
            'BIGINT UNSIGNED NULL AFTER secretariat_desk_id'
        );

        $this->addColumnIfMissing(
            'registry_books',
            'number_sequence_id',
            'BIGINT UNSIGNED NULL AFTER registry_period_id'
        );

        $this->addColumnIfMissing(
            'registry_books',
            'numbering_strategy_code',
            "VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'dedicated' AFTER number_sequence_id"
        );

        $this->addColumnIfMissing(
            'registry_books',
            'root_organization_scope_key',
            'BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(root_organization_id, 0)) PERSISTENT'
        );

        $this->addColumnIfMissing(
            'registry_books',
            'secretariat_scope_key',
            'BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(secretariat_desk_id, 0)) PERSISTENT'
        );

        $this->addColumnIfMissing(
            'registry_books',
            'registry_period_scope_key',
            'BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(registry_period_id, 0)) PERSISTENT'
        );

        $this->dropIndexIfExists(
            'registry_books',
            'registry_books_scope_code_unique'
        );

        $this->addIndexIfMissing(
            'registry_books',
            'registry_books_enterprise_scope_unique',
            "
                UNIQUE KEY registry_books_enterprise_scope_unique (
                    root_organization_scope_key,
                    organization_id,
                    secretariat_scope_key,
                    registry_period_scope_key,
                    code
                )
            "
        );

        $this->addIndexIfMissing(
            'registry_books',
            'registry_books_root_index',
            'INDEX registry_books_root_index (root_organization_id)'
        );

        $this->addIndexIfMissing(
            'registry_books',
            'registry_books_secretariat_index',
            'INDEX registry_books_secretariat_index (secretariat_desk_id)'
        );

        $this->addIndexIfMissing(
            'registry_books',
            'registry_books_registry_period_index',
            'INDEX registry_books_registry_period_index (registry_period_id)'
        );

        $this->addIndexIfMissing(
            'registry_books',
            'registry_books_number_sequence_index',
            'INDEX registry_books_number_sequence_index (number_sequence_id)'
        );

        $this->addForeignKeyIfMissing(
            'registry_books',
            'registry_books_secretariat_fk',
            'secretariat_desk_id',
            'secretariat_desks',
            'id',
            'RESTRICT'
        );

        $this->addForeignKeyIfMissing(
            'registry_books',
            'registry_books_registry_period_fk',
            'registry_period_id',
            'registry_periods',
            'id',
            'RESTRICT'
        );

        $this->addForeignKeyIfMissing(
            'registry_books',
            'registry_books_number_sequence_fk',
            'number_sequence_id',
            'registry_number_sequences',
            'id',
            'RESTRICT'
        );
    }

    private function createRegistryNumberReservations(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS registry_number_reservations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                root_organization_id BIGINT UNSIGNED NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,

                secretariat_desk_id BIGINT UNSIGNED NOT NULL,
                registry_book_id BIGINT UNSIGNED NOT NULL,
                number_sequence_id BIGINT UNSIGNED NOT NULL,

                correspondence_id BIGINT UNSIGNED NULL,

                sequential_number BIGINT UNSIGNED NOT NULL,

                formatted_number VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                reservation_status_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'reserved',

                reserved_by_user_id BIGINT UNSIGNED NOT NULL,

                reserved_appointment_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                actor_context_snapshot_json LONGTEXT NULL,

                reserved_at TIMESTAMP NOT NULL,
                expires_at TIMESTAMP NULL,
                consumed_at TIMESTAMP NULL,
                released_at TIMESTAMP NULL,

                release_reason TEXT NULL,

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY registry_number_res_reference_unique (
                    public_reference
                ),

                UNIQUE KEY registry_number_res_sequence_unique (
                    number_sequence_id,
                    sequential_number
                ),

                UNIQUE KEY registry_number_res_formatted_unique (
                    number_sequence_id,
                    formatted_number
                ),

                INDEX registry_number_res_root_index (
                    root_organization_id
                ),

                INDEX registry_number_res_org_index (
                    organization_id
                ),

                INDEX registry_number_res_desk_index (
                    secretariat_desk_id
                ),

                INDEX registry_number_res_book_index (
                    registry_book_id
                ),

                INDEX registry_number_res_corr_index (
                    correspondence_id
                ),

                INDEX registry_number_res_status_index (
                    reservation_status_code
                ),

                INDEX registry_number_res_expiry_index (
                    expires_at
                ),

                CONSTRAINT registry_number_res_desk_fk
                    FOREIGN KEY (secretariat_desk_id)
                    REFERENCES secretariat_desks (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT registry_number_res_book_fk
                    FOREIGN KEY (registry_book_id)
                    REFERENCES registry_books (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT registry_number_res_sequence_fk
                    FOREIGN KEY (number_sequence_id)
                    REFERENCES registry_number_sequences (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT registry_number_res_corr_fk
                    FOREIGN KEY (correspondence_id)
                    REFERENCES correspondences (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function upgradeCorrespondences(): void
    {
        if (!$this->tableExists('correspondences')) {
            return;
        }

        $this->addColumnIfMissing(
            'correspondences',
            'root_organization_id',
            'BIGINT UNSIGNED NULL AFTER public_reference'
        );

        $this->addColumnIfMissing(
            'correspondences',
            'root_organization_public_reference',
            'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER root_organization_id'
        );

        $this->addColumnIfMissing(
            'correspondences',
            'organization_public_reference',
            'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER organization_id'
        );

        $this->addColumnIfMissing(
            'correspondences',
            'org_unit_public_reference',
            'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER org_unit_id'
        );

        $this->addColumnIfMissing(
            'correspondences',
            'secretariat_desk_id',
            'BIGINT UNSIGNED NULL AFTER org_unit_public_reference'
        );

        $this->addColumnIfMissing(
            'correspondences',
            'creating_appointment_reference',
            'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER created_by_user_id'
        );

        $this->addColumnIfMissing(
            'correspondences',
            'organizational_context_snapshot_json',
            'LONGTEXT NULL AFTER creating_appointment_reference'
        );

        $this->addIndexIfMissing(
            'correspondences',
            'correspondences_root_status_index',
            'INDEX correspondences_root_status_index (root_organization_id, status_code)'
        );

        $this->addIndexIfMissing(
            'correspondences',
            'correspondences_org_status_enterprise_index',
            'INDEX correspondences_org_status_enterprise_index (organization_id, status_code, updated_at)'
        );

        $this->addIndexIfMissing(
            'correspondences',
            'correspondences_secretariat_status_index',
            'INDEX correspondences_secretariat_status_index (secretariat_desk_id, status_code, updated_at)'
        );

        $this->addForeignKeyIfMissing(
            'correspondences',
            'correspondences_secretariat_fk',
            'secretariat_desk_id',
            'secretariat_desks',
            'id',
            'SET NULL'
        );
    }

    private function upgradeRegistrations(): void
    {
        if (!$this->tableExists('correspondence_registrations')) {
            return;
        }

        $this->addColumnIfMissing(
            'correspondence_registrations',
            'root_organization_id',
            'BIGINT UNSIGNED NULL AFTER id'
        );

        $this->addColumnIfMissing(
            'correspondence_registrations',
            'organization_id',
            'BIGINT UNSIGNED NULL AFTER root_organization_id'
        );

        $this->addColumnIfMissing(
            'correspondence_registrations',
            'secretariat_desk_id',
            'BIGINT UNSIGNED NULL AFTER organization_id'
        );

        $this->addColumnIfMissing(
            'correspondence_registrations',
            'registry_period_id',
            'BIGINT UNSIGNED NULL AFTER secretariat_desk_id'
        );

        $this->addColumnIfMissing(
            'correspondence_registrations',
            'number_sequence_id',
            'BIGINT UNSIGNED NULL AFTER registry_period_id'
        );

        $this->addColumnIfMissing(
            'correspondence_registrations',
            'number_reservation_id',
            'BIGINT UNSIGNED NULL AFTER number_sequence_id'
        );

        $this->addColumnIfMissing(
            'correspondence_registrations',
            'registered_appointment_reference',
            'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER registered_by_user_id'
        );

        $this->addColumnIfMissing(
            'correspondence_registrations',
            'actor_context_snapshot_json',
            'LONGTEXT NULL AFTER registered_appointment_reference'
        );

        $this->addIndexIfMissing(
            'correspondence_registrations',
            'corr_reg_root_index',
            'INDEX corr_reg_root_index (root_organization_id)'
        );

        $this->addIndexIfMissing(
            'correspondence_registrations',
            'corr_reg_org_index',
            'INDEX corr_reg_org_index (organization_id)'
        );

        $this->addIndexIfMissing(
            'correspondence_registrations',
            'corr_reg_secretariat_index',
            'INDEX corr_reg_secretariat_index (secretariat_desk_id)'
        );

        $this->addIndexIfMissing(
            'correspondence_registrations',
            'corr_reg_period_index',
            'INDEX corr_reg_period_index (registry_period_id)'
        );

        $this->addIndexIfMissing(
            'correspondence_registrations',
            'corr_reg_sequence_index',
            'INDEX corr_reg_sequence_index (number_sequence_id)'
        );

        $this->addIndexIfMissing(
            'correspondence_registrations',
            'corr_reg_reservation_unique',
            'UNIQUE KEY corr_reg_reservation_unique (number_reservation_id)'
        );

        $this->addForeignKeyIfMissing(
            'correspondence_registrations',
            'corr_reg_secretariat_fk',
            'secretariat_desk_id',
            'secretariat_desks',
            'id',
            'RESTRICT'
        );

        $this->addForeignKeyIfMissing(
            'correspondence_registrations',
            'corr_reg_period_fk',
            'registry_period_id',
            'registry_periods',
            'id',
            'RESTRICT'
        );

        $this->addForeignKeyIfMissing(
            'correspondence_registrations',
            'corr_reg_sequence_fk',
            'number_sequence_id',
            'registry_number_sequences',
            'id',
            'RESTRICT'
        );

        $this->addForeignKeyIfMissing(
            'correspondence_registrations',
            'corr_reg_reservation_fk',
            'number_reservation_id',
            'registry_number_reservations',
            'id',
            'RESTRICT'
        );
    }

    private function upgradeReferrals(): void
    {
        if (!$this->tableExists('correspondence_referrals')) {
            return;
        }

        $this->addColumnIfMissing(
            'correspondence_referrals',
            'root_organization_id',
            'BIGINT UNSIGNED NULL AFTER correspondence_id'
        );

        $this->addColumnIfMissing(
            'correspondence_referrals',
            'source_organization_id',
            'BIGINT UNSIGNED NULL AFTER referred_by_user_id'
        );

        $this->addColumnIfMissing(
            'correspondence_referrals',
            'source_secretariat_desk_id',
            'BIGINT UNSIGNED NULL AFTER source_organization_id'
        );

        $this->addColumnIfMissing(
            'correspondence_referrals',
            'source_appointment_reference',
            'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER source_secretariat_desk_id'
        );

        $this->addColumnIfMissing(
            'correspondence_referrals',
            'target_organization_id',
            'BIGINT UNSIGNED NULL AFTER source_org_unit_id'
        );

        $this->addColumnIfMissing(
            'correspondence_referrals',
            'target_appointment_id',
            'BIGINT UNSIGNED NULL AFTER target_position_id'
        );

        $this->addColumnIfMissing(
            'correspondence_referrals',
            'target_appointment_reference',
            'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER target_appointment_id'
        );

        $this->addColumnIfMissing(
            'correspondence_referrals',
            'organizational_context_snapshot_json',
            'LONGTEXT NULL AFTER target_appointment_reference'
        );

        $this->addIndexIfMissing(
            'correspondence_referrals',
            'corr_referrals_root_status_index',
            'INDEX corr_referrals_root_status_index (root_organization_id, status_code)'
        );

        $this->addIndexIfMissing(
            'correspondence_referrals',
            'corr_referrals_target_org_status_index',
            'INDEX corr_referrals_target_org_status_index (target_organization_id, status_code, referred_at)'
        );

        $this->addIndexIfMissing(
            'correspondence_referrals',
            'corr_referrals_target_appointment_index',
            'INDEX corr_referrals_target_appointment_index (target_appointment_id, status_code, referred_at)'
        );

        $this->addForeignKeyIfMissing(
            'correspondence_referrals',
            'corr_referrals_source_secretariat_fk',
            'source_secretariat_desk_id',
            'secretariat_desks',
            'id',
            'SET NULL'
        );
    }

    private function upgradeEvents(): void
    {
        if (!$this->tableExists('correspondence_events')) {
            return;
        }

        $this->addColumnIfMissing(
            'correspondence_events',
            'root_organization_id',
            'BIGINT UNSIGNED NULL AFTER correspondence_id'
        );

        $this->addColumnIfMissing(
            'correspondence_events',
            'organization_id',
            'BIGINT UNSIGNED NULL AFTER root_organization_id'
        );

        $this->addColumnIfMissing(
            'correspondence_events',
            'secretariat_desk_id',
            'BIGINT UNSIGNED NULL AFTER organization_id'
        );

        $this->addColumnIfMissing(
            'correspondence_events',
            'actor_appointment_reference',
            'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER actor_user_id'
        );

        $this->addColumnIfMissing(
            'correspondence_events',
            'actor_context_snapshot_json',
            'LONGTEXT NULL AFTER actor_appointment_reference'
        );

        $this->addIndexIfMissing(
            'correspondence_events',
            'corr_events_root_time_index',
            'INDEX corr_events_root_time_index (root_organization_id, occurred_at)'
        );

        $this->addIndexIfMissing(
            'correspondence_events',
            'corr_events_org_time_index',
            'INDEX corr_events_org_time_index (organization_id, occurred_at)'
        );

        $this->addIndexIfMissing(
            'correspondence_events',
            'corr_events_secretariat_time_index',
            'INDEX corr_events_secretariat_time_index (secretariat_desk_id, occurred_at)'
        );

        $this->addForeignKeyIfMissing(
            'correspondence_events',
            'corr_events_secretariat_fk',
            'secretariat_desk_id',
            'secretariat_desks',
            'id',
            'SET NULL'
        );
    }

    private function createCorrespondenceExchanges(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_exchanges (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                root_organization_id BIGINT UNSIGNED NOT NULL,

                source_correspondence_id BIGINT UNSIGNED NOT NULL,
                target_correspondence_id BIGINT UNSIGNED NULL,

                source_organization_id BIGINT UNSIGNED NOT NULL,
                target_organization_id BIGINT UNSIGNED NOT NULL,

                source_secretariat_desk_id BIGINT UNSIGNED NULL,
                target_secretariat_desk_id BIGINT UNSIGNED NULL,

                source_registration_id BIGINT UNSIGNED NULL,
                target_registration_id BIGINT UNSIGNED NULL,

                exchange_mode_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'intra_group',

                status_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'pending',

                source_context_snapshot_json LONGTEXT NULL,
                target_context_snapshot_json LONGTEXT NULL,

                created_by_user_id BIGINT UNSIGNED NOT NULL,

                dispatched_at TIMESTAMP NULL,
                received_at TIMESTAMP NULL,
                rejected_at TIMESTAMP NULL,

                rejection_reason TEXT NULL,

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY correspondence_exchanges_reference_unique (
                    public_reference
                ),

                INDEX correspondence_exchanges_root_index (
                    root_organization_id
                ),

                INDEX correspondence_exchanges_source_corr_index (
                    source_correspondence_id
                ),

                INDEX correspondence_exchanges_target_corr_index (
                    target_correspondence_id
                ),

                INDEX correspondence_exchanges_source_org_index (
                    source_organization_id
                ),

                INDEX correspondence_exchanges_target_org_index (
                    target_organization_id
                ),

                INDEX correspondence_exchanges_status_index (
                    status_code
                ),

                CONSTRAINT correspondence_exchanges_source_corr_fk
                    FOREIGN KEY (source_correspondence_id)
                    REFERENCES correspondences (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT correspondence_exchanges_target_corr_fk
                    FOREIGN KEY (target_correspondence_id)
                    REFERENCES correspondences (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT correspondence_exchanges_source_desk_fk
                    FOREIGN KEY (source_secretariat_desk_id)
                    REFERENCES secretariat_desks (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT correspondence_exchanges_target_desk_fk
                    FOREIGN KEY (target_secretariat_desk_id)
                    REFERENCES secretariat_desks (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT correspondence_exchanges_source_reg_fk
                    FOREIGN KEY (source_registration_id)
                    REFERENCES correspondence_registrations (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT correspondence_exchanges_target_reg_fk
                    FOREIGN KEY (target_registration_id)
                    REFERENCES correspondence_registrations (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");

        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement = $this->db->prepare("
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

        return (int) $statement->fetchColumn() > 0;
    }

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $statement = $this->db->prepare("
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

        return (int) $statement->fetchColumn() > 0;
    }

    private function foreignKeyExists(
        string $table,
        string $constraint
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type =
                  'FOREIGN KEY'
        ");

        $statement->execute([
            $table,
            $constraint,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function addColumnIfMissing(
        string $table,
        string $column,
        string $definition
    ): void {
        if (
            !$this->tableExists($table)
            || $this->columnExists(
                $table,
                $column
            )
        ) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE `{$table}`
             ADD COLUMN `{$column}`
             {$definition}"
        );
    }

    private function addIndexIfMissing(
        string $table,
        string $index,
        string $definition
    ): void {
        if (
            !$this->tableExists($table)
            || $this->indexExists(
                $table,
                $index
            )
        ) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE `{$table}`
             ADD {$definition}"
        );
    }

    private function dropIndexIfExists(
        string $table,
        string $index
    ): void {
        if (
            !$this->tableExists($table)
            || !$this->indexExists(
                $table,
                $index
            )
        ) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE `{$table}`
             DROP INDEX `{$index}`"
        );
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete
    ): void {
        if (
            !$this->tableExists($table)
            || !$this->tableExists(
                $referenceTable
            )
            || !$this->columnExists(
                $table,
                $column
            )
            || !$this->columnExists(
                $referenceTable,
                $referenceColumn
            )
            || $this->foreignKeyExists(
                $table,
                $constraint
            )
        ) {
            return;
        }

        $allowedDeleteRules = [
            'RESTRICT',
            'CASCADE',
            'SET NULL',
        ];

        if (
            !in_array(
                $onDelete,
                $allowedDeleteRules,
                true
            )
        ) {
            return;
        }

        $this->db->exec("
            ALTER TABLE `{$table}`
            ADD CONSTRAINT `{$constraint}`
            FOREIGN KEY (`{$column}`)
            REFERENCES `{$referenceTable}`
                (`{$referenceColumn}`)
            ON UPDATE RESTRICT
            ON DELETE {$onDelete}
        ");
    }
}
