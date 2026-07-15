<?php

namespace IPKF\Database\Migrations;

class CreateAutomationCorrespondenceFoundationTables extends Migration
{
    public function up(): void
    {
        $this->createLookupTables();
        $this->createCorrespondencesTable();
        $this->createCorrespondenceVersionsTable();
        $this->createCorrespondencePartiesTable();
        $this->createRegistryBooksTable();
        $this->createCorrespondenceRegistrationsTable();
        $this->createCorrespondenceRelationsTable();
        $this->createCorrespondenceReferralsTable();
        $this->createCorrespondenceEventsTable();
        $this->createPrivateFilesTable();
        $this->createCorrespondenceAttachmentsTable();
        $this->addForeignKeys();
    }

    public function down(): void
    {
    }

    private function createLookupTables(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS lookup_domains (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 1,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY lookup_domains_code_unique (code),
                INDEX lookup_domains_status_index (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS lookup_values (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                domain_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY lookup_values_domain_code_unique (domain_id, code),
                INDEX lookup_values_domain_index (domain_id),
                INDEX lookup_values_status_index (status),
                INDEX lookup_values_sort_index (domain_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createCorrespondencesTable(): void
    {
        $organizationType = $this->referenceColumnType('organizations', 'id', 'BIGINT UNSIGNED');
        $orgUnitType = $this->referenceColumnType('org_units', 'id', 'BIGINT UNSIGNED');
        $fiscalYearType = $this->referenceColumnType('fiscal_years', 'id', 'BIGINT');
        $userType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondences (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                organization_id {$organizationType} NOT NULL,
                org_unit_id {$orgUnitType} NULL,
                fiscal_year_id {$fiscalYearType} NULL,
                direction_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
                subject VARCHAR(500) NOT NULL,
                summary TEXT NULL,
                current_version_id BIGINT UNSIGNED NULL,
                current_version_number INT UNSIGNED NOT NULL DEFAULT 0,
                priority_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'normal',
                confidentiality_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'normal',
                channel_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'manual',
                external_number VARCHAR(190) NULL,
                external_date DATE NULL,
                received_at TIMESTAMP NULL,
                dispatched_at TIMESTAMP NULL,
                registered_at TIMESTAMP NULL,
                created_by_user_id {$userType} NOT NULL,
                updated_by_user_id {$userType} NULL,
                lock_version INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY correspondences_public_reference_unique (public_reference),
                INDEX correspondences_organization_index (organization_id),
                INDEX correspondences_org_unit_index (org_unit_id),
                INDEX correspondences_fiscal_year_index (fiscal_year_id),
                INDEX correspondences_direction_index (direction_code),
                INDEX correspondences_status_index (status_code),
                INDEX correspondences_priority_index (priority_code),
                INDEX correspondences_confidentiality_index (confidentiality_code),
                INDEX correspondences_channel_index (channel_code),
                INDEX correspondences_registered_at_index (registered_at),
                INDEX correspondences_created_by_index (created_by_user_id),
                INDEX correspondences_current_version_index (id, current_version_id, current_version_number),
                INDEX correspondences_org_status_index (organization_id, status_code),
                CONSTRAINT correspondences_current_version_check CHECK (
                    (current_version_id IS NULL AND current_version_number = 0)
                    OR (current_version_id IS NOT NULL AND current_version_number > 0)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createCorrespondenceVersionsTable(): void
    {
        $userType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_versions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id BIGINT UNSIGNED NOT NULL,
                version_number INT UNSIGNED NOT NULL,
                subject_snapshot VARCHAR(500) NOT NULL,
                content_snapshot LONGTEXT NOT NULL,
                summary_snapshot TEXT NULL,
                change_note TEXT NULL,
                content_checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                created_by_user_id {$userType} NOT NULL,
                created_at TIMESTAMP NULL,
                UNIQUE KEY corr_versions_number_unique (correspondence_id, version_number),
                UNIQUE KEY corr_versions_corr_id_unique (correspondence_id, id),
                UNIQUE KEY corr_versions_current_selection_unique (correspondence_id, id, version_number),
                INDEX corr_versions_corr_index (correspondence_id),
                INDEX corr_versions_created_by_index (created_by_user_id),
                INDEX corr_versions_checksum_index (content_checksum),
                CONSTRAINT corr_versions_number_check CHECK (version_number > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createCorrespondencePartiesTable(): void
    {
        $personType = $this->referenceColumnType('persons', 'id', 'BIGINT UNSIGNED');
        $organizationType = $this->referenceColumnType('organizations', 'id', 'BIGINT UNSIGNED');
        $orgUnitType = $this->referenceColumnType('org_units', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_parties (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id BIGINT UNSIGNED NOT NULL,
                party_role_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                target_kind_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                person_id {$personType} NULL,
                organization_id {$organizationType} NULL,
                org_unit_id {$orgUnitType} NULL,
                external_display_name VARCHAR(255) NULL,
                external_organization_name VARCHAR(255) NULL,
                external_contact_or_address TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                INDEX corr_parties_corr_index (correspondence_id),
                INDEX corr_parties_role_index (party_role_code),
                INDEX corr_parties_kind_index (target_kind_code),
                INDEX corr_parties_person_index (person_id),
                INDEX corr_parties_organization_index (organization_id),
                INDEX corr_parties_org_unit_index (org_unit_id),
                CONSTRAINT corr_parties_target_check CHECK (
                    (target_kind_code = 'person'
                        AND person_id IS NOT NULL
                        AND organization_id IS NULL
                        AND org_unit_id IS NULL
                        AND external_display_name IS NULL
                        AND external_organization_name IS NULL
                        AND external_contact_or_address IS NULL)
                    OR (target_kind_code = 'organization'
                        AND person_id IS NULL
                        AND organization_id IS NOT NULL
                        AND org_unit_id IS NULL
                        AND external_display_name IS NULL
                        AND external_organization_name IS NULL
                        AND external_contact_or_address IS NULL)
                    OR (target_kind_code = 'org_unit'
                        AND person_id IS NULL
                        AND organization_id IS NULL
                        AND org_unit_id IS NOT NULL
                        AND external_display_name IS NULL
                        AND external_organization_name IS NULL
                        AND external_contact_or_address IS NULL)
                    OR (target_kind_code = 'external'
                        AND person_id IS NULL
                        AND organization_id IS NULL
                        AND org_unit_id IS NULL
                        AND external_display_name IS NOT NULL
                        AND CHAR_LENGTH(TRIM(external_display_name)) > 0)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createRegistryBooksTable(): void
    {
        $organizationType = $this->referenceColumnType('organizations', 'id', 'BIGINT UNSIGNED');
        $fiscalYearType = $this->referenceColumnType('fiscal_years', 'id', 'BIGINT');
        $orgUnitType = $this->referenceColumnType('org_units', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS registry_books (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id {$organizationType} NOT NULL,
                fiscal_year_id {$fiscalYearType} NULL,
                org_unit_id {$orgUnitType} NULL,
                fiscal_year_scope_key {$fiscalYearType} GENERATED ALWAYS AS (
                    COALESCE(fiscal_year_id, 0)
                ) PERSISTENT,
                org_unit_scope_key {$orgUnitType} GENERATED ALWAYS AS (
                    COALESCE(org_unit_id, 0)
                ) PERSISTENT,
                scope_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                prefix VARCHAR(50) NULL,
                suffix VARCHAR(50) NULL,
                next_sequence_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
                number_padding TINYINT UNSIGNED NOT NULL DEFAULT 5,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY registry_books_scope_code_unique (
                    organization_id,
                    fiscal_year_scope_key,
                    org_unit_scope_key,
                    code
                ),
                INDEX registry_books_organization_index (organization_id),
                INDEX registry_books_fiscal_year_index (fiscal_year_id),
                INDEX registry_books_org_unit_index (org_unit_id),
                INDEX registry_books_scope_index (scope_code),
                INDEX registry_books_status_index (status),
                CONSTRAINT registry_books_sequence_check CHECK (next_sequence_number > 0),
                CONSTRAINT registry_books_padding_check CHECK (number_padding > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createCorrespondenceRegistrationsTable(): void
    {
        $userType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_registrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id BIGINT UNSIGNED NOT NULL,
                registry_book_id BIGINT UNSIGNED NOT NULL,
                registration_role_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                sequential_number BIGINT UNSIGNED NOT NULL,
                formatted_number VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                registered_at TIMESTAMP NOT NULL,
                registered_by_user_id {$userType} NOT NULL,
                status_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                cancellation_reason TEXT NULL,
                cancelled_at TIMESTAMP NULL,
                cancelled_by_user_id {$userType} NULL,
                active_registration_slot TINYINT GENERATED ALWAYS AS (
                    CASE WHEN cancelled_at IS NULL THEN 1 ELSE NULL END
                ) PERSISTENT,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY corr_reg_book_sequence_unique (registry_book_id, sequential_number),
                UNIQUE KEY corr_reg_book_formatted_unique (registry_book_id, formatted_number),
                UNIQUE KEY corr_reg_active_role_unique (correspondence_id, registration_role_code, active_registration_slot),
                INDEX corr_reg_corr_index (correspondence_id),
                INDEX corr_reg_book_index (registry_book_id),
                INDEX corr_reg_role_index (registration_role_code),
                INDEX corr_reg_status_index (status_code),
                INDEX corr_reg_registered_at_index (registered_at),
                CONSTRAINT corr_reg_sequence_check CHECK (sequential_number > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createCorrespondenceRelationsTable(): void
    {
        $userType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_relations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_correspondence_id BIGINT UNSIGNED NOT NULL,
                target_correspondence_id BIGINT UNSIGNED NOT NULL,
                relation_type_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                note TEXT NULL,
                created_by_user_id {$userType} NOT NULL,
                created_at TIMESTAMP NULL,
                UNIQUE KEY corr_relations_exact_unique (source_correspondence_id, target_correspondence_id, relation_type_code),
                INDEX corr_relations_source_index (source_correspondence_id),
                INDEX corr_relations_target_index (target_correspondence_id),
                INDEX corr_relations_type_index (relation_type_code),
                CONSTRAINT corr_relations_no_self_check CHECK (source_correspondence_id <> target_correspondence_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createCorrespondenceReferralsTable(): void
    {
        $userType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');
        $orgUnitType = $this->referenceColumnType('org_units', 'id', 'BIGINT UNSIGNED');
        $positionType = $this->referenceColumnType('positions', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_referrals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id BIGINT UNSIGNED NOT NULL,
                parent_referral_id BIGINT UNSIGNED NULL,
                referred_by_user_id {$userType} NOT NULL,
                source_org_unit_id {$orgUnitType} NULL,
                target_user_id {$userType} NULL,
                target_org_unit_id {$orgUnitType} NULL,
                target_position_id {$positionType} NULL,
                instruction TEXT NULL,
                requested_action_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'review',
                priority_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'normal',
                status_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
                referred_at TIMESTAMP NOT NULL,
                due_at TIMESTAMP NULL,
                seen_at TIMESTAMP NULL,
                claimed_by_user_id {$userType} NULL,
                claimed_at TIMESTAMP NULL,
                completed_by_user_id {$userType} NULL,
                completed_at TIMESTAMP NULL,
                result_note TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY corr_referrals_corr_id_unique (correspondence_id, id),
                INDEX corr_referrals_corr_index (correspondence_id),
                INDEX corr_referrals_corr_parent_index (correspondence_id, parent_referral_id),
                INDEX corr_referrals_referred_by_index (referred_by_user_id),
                INDEX corr_referrals_source_unit_index (source_org_unit_id),
                INDEX corr_referrals_target_user_index (target_user_id),
                INDEX corr_referrals_target_unit_index (target_org_unit_id),
                INDEX corr_referrals_target_position_index (target_position_id),
                INDEX corr_referrals_status_index (status_code),
                INDEX corr_referrals_user_cartable_index (target_user_id, status_code, referred_at),
                INDEX corr_referrals_unit_cartable_index (target_org_unit_id, status_code, referred_at),
                INDEX corr_referrals_due_at_index (due_at),
                CONSTRAINT corr_referrals_one_target_check CHECK (
                    (target_user_id IS NOT NULL) +
                    (target_org_unit_id IS NOT NULL) +
                    (target_position_id IS NOT NULL) = 1
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createCorrespondenceEventsTable(): void
    {
        $userType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');
        $orgUnitType = $this->referenceColumnType('org_units', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id BIGINT UNSIGNED NOT NULL,
                referral_id BIGINT UNSIGNED NULL,
                event_type_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                actor_user_id {$userType} NULL,
                actor_org_unit_id {$orgUnitType} NULL,
                occurred_at TIMESTAMP NOT NULL,
                previous_status_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                resulting_status_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                safe_metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                INDEX corr_events_corr_index (correspondence_id),
                INDEX corr_events_referral_index (referral_id),
                INDEX corr_events_corr_referral_index (correspondence_id, referral_id),
                INDEX corr_events_type_index (event_type_code),
                INDEX corr_events_actor_user_index (actor_user_id),
                INDEX corr_events_actor_unit_index (actor_org_unit_id),
                INDEX corr_events_occurred_at_index (occurred_at),
                INDEX corr_events_corr_time_index (correspondence_id, occurred_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createPrivateFilesTable(): void
    {
        $userType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS private_files (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                storage_provider_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                storage_key VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                original_filename VARCHAR(500) NOT NULL,
                mime_type VARCHAR(190) NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL,
                sha256_checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                uploaded_by_user_id {$userType} NOT NULL,
                uploaded_at TIMESTAMP NOT NULL,
                scan_status_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY private_files_public_reference_unique (public_reference),
                INDEX private_files_checksum_index (sha256_checksum),
                INDEX private_files_uploaded_by_index (uploaded_by_user_id),
                INDEX private_files_uploaded_at_index (uploaded_at),
                INDEX private_files_scan_status_index (scan_status_code),
                INDEX private_files_status_index (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createCorrespondenceAttachmentsTable(): void
    {
        $userType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_attachments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                correspondence_id BIGINT UNSIGNED NOT NULL,
                correspondence_version_id BIGINT UNSIGNED NULL,
                file_id BIGINT UNSIGNED NOT NULL,
                attachment_role_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'enclosure',
                title VARCHAR(255) NULL,
                description TEXT NULL,
                display_order INT NOT NULL DEFAULT 0,
                linked_by_user_id {$userType} NOT NULL,
                linked_at TIMESTAMP NOT NULL,
                INDEX corr_attachments_corr_index (correspondence_id),
                INDEX corr_attachments_version_index (correspondence_version_id),
                INDEX corr_attachments_corr_version_index (correspondence_id, correspondence_version_id),
                INDEX corr_attachments_file_index (file_id),
                INDEX corr_attachments_role_index (attachment_role_code),
                INDEX corr_attachments_order_index (correspondence_id, display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function addForeignKeys(): void
    {
        $this->addForeignKeyIfPossible('lookup_values', 'lookup_values_domain_fk', 'domain_id', 'lookup_domains', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('correspondences', 'corr_organization_fk', 'organization_id', 'organizations', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondences', 'corr_org_unit_fk', 'org_unit_id', 'org_units', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondences', 'corr_fiscal_year_fk', 'fiscal_year_id', 'fiscal_years', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondences', 'corr_created_by_fk', 'created_by_user_id', 'users', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondences', 'corr_updated_by_fk', 'updated_by_user_id', 'users', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('correspondence_versions', 'corr_versions_corr_fk', 'correspondence_id', 'correspondences', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_versions', 'corr_versions_user_fk', 'created_by_user_id', 'users', 'id', 'RESTRICT');
        $this->addCompositeForeignKeyIfPossible(
            'correspondences',
            'corr_current_version_fk',
            ['id', 'current_version_id', 'current_version_number'],
            'correspondence_versions',
            ['correspondence_id', 'id', 'version_number'],
            'RESTRICT'
        );

        $this->addForeignKeyIfPossible('correspondence_parties', 'corr_parties_corr_fk', 'correspondence_id', 'correspondences', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_parties', 'corr_parties_person_fk', 'person_id', 'persons', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_parties', 'corr_parties_org_fk', 'organization_id', 'organizations', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_parties', 'corr_parties_unit_fk', 'org_unit_id', 'org_units', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('registry_books', 'registry_books_org_fk', 'organization_id', 'organizations', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('registry_books', 'registry_books_fiscal_fk', 'fiscal_year_id', 'fiscal_years', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('registry_books', 'registry_books_unit_fk', 'org_unit_id', 'org_units', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('correspondence_registrations', 'corr_reg_corr_fk', 'correspondence_id', 'correspondences', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_registrations', 'corr_reg_book_fk', 'registry_book_id', 'registry_books', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_registrations', 'corr_reg_user_fk', 'registered_by_user_id', 'users', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_registrations', 'corr_reg_cancel_user_fk', 'cancelled_by_user_id', 'users', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('correspondence_relations', 'corr_rel_source_fk', 'source_correspondence_id', 'correspondences', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_relations', 'corr_rel_target_fk', 'target_correspondence_id', 'correspondences', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_relations', 'corr_rel_user_fk', 'created_by_user_id', 'users', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('correspondence_referrals', 'corr_ref_corr_fk', 'correspondence_id', 'correspondences', 'id', 'RESTRICT');
        $this->addCompositeForeignKeyIfPossible(
            'correspondence_referrals',
            'corr_ref_parent_fk',
            ['correspondence_id', 'parent_referral_id'],
            'correspondence_referrals',
            ['correspondence_id', 'id'],
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible('correspondence_referrals', 'corr_ref_by_user_fk', 'referred_by_user_id', 'users', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_referrals', 'corr_ref_source_unit_fk', 'source_org_unit_id', 'org_units', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_referrals', 'corr_ref_target_user_fk', 'target_user_id', 'users', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_referrals', 'corr_ref_target_unit_fk', 'target_org_unit_id', 'org_units', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_referrals', 'corr_ref_target_pos_fk', 'target_position_id', 'positions', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_referrals', 'corr_ref_claimed_user_fk', 'claimed_by_user_id', 'users', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_referrals', 'corr_ref_completed_user_fk', 'completed_by_user_id', 'users', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('correspondence_events', 'corr_events_corr_fk', 'correspondence_id', 'correspondences', 'id', 'RESTRICT');
        $this->addCompositeForeignKeyIfPossible(
            'correspondence_events',
            'corr_events_referral_fk',
            ['correspondence_id', 'referral_id'],
            'correspondence_referrals',
            ['correspondence_id', 'id'],
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible('correspondence_events', 'corr_events_user_fk', 'actor_user_id', 'users', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_events', 'corr_events_unit_fk', 'actor_org_unit_id', 'org_units', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('private_files', 'private_files_user_fk', 'uploaded_by_user_id', 'users', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('correspondence_attachments', 'corr_attach_corr_fk', 'correspondence_id', 'correspondences', 'id', 'RESTRICT');
        $this->addCompositeForeignKeyIfPossible(
            'correspondence_attachments',
            'corr_attach_version_fk',
            ['correspondence_id', 'correspondence_version_id'],
            'correspondence_versions',
            ['correspondence_id', 'id'],
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible('correspondence_attachments', 'corr_attach_file_fk', 'file_id', 'private_files', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('correspondence_attachments', 'corr_attach_user_fk', 'linked_by_user_id', 'users', 'id', 'RESTRICT');
    }

    private function referenceColumnType(string $table, string $column, string $default): string
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return $default;
        }

        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);
        $type = strtoupper((string) $statement->fetchColumn());

        return preg_match('/^(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)(\(\d+\))?( UNSIGNED)?$/', $type) === 1
            ? $type
            : $default;
    }

    private function addForeignKeyIfPossible(
        string $table,
        string $constraint,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete
    ): void {
        if (!$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || !$this->columnExists($table, $column)
            || !$this->columnExists($referenceTable, $referenceColumn)
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys($referenceTable)
            || $this->columnType($table, $column) !== $this->columnType($referenceTable, $referenceColumn)
        ) {
            return;
        }

        if (!$this->reconcileForeignKeyRules($table, $constraint, $onDelete)) {
            return;
        }

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column}) REFERENCES {$referenceTable} ({$referenceColumn})
            ON UPDATE RESTRICT ON DELETE {$onDelete}
        ");
    }

    private function addCompositeForeignKeyIfPossible(
        string $table,
        string $constraint,
        array $columns,
        string $referenceTable,
        array $referenceColumns,
        string $onDelete
    ): void {
        if (count($columns) === 0
            || count($columns) !== count($referenceColumns)
            || !$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys($referenceTable)
        ) {
            return;
        }

        foreach ($columns as $index => $column) {
            $referenceColumn = $referenceColumns[$index];

            if (!$this->columnExists($table, $column)
                || !$this->columnExists($referenceTable, $referenceColumn)
                || $this->columnType($table, $column) !== $this->columnType($referenceTable, $referenceColumn)
            ) {
                return;
            }
        }

        $columnList = implode(', ', $columns);
        $referenceColumnList = implode(', ', $referenceColumns);

        if (!$this->reconcileForeignKeyRules($table, $constraint, $onDelete)) {
            return;
        }

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$columnList}) REFERENCES {$referenceTable} ({$referenceColumnList})
            ON UPDATE RESTRICT ON DELETE {$onDelete}
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

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnType(string $table, string $column): string
    {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);

        return strtolower((string) $statement->fetchColumn());
    }

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower((string) $statement->fetchColumn()) === 'innodb';
    }

    private function reconcileForeignKeyRules(string $table, string $constraint, string $onDelete): bool
    {
        $expectedDeleteRule = strtoupper(trim($onDelete));
        $rules = $this->foreignKeyRules($table, $constraint);

        if ($rules === null) {
            return true;
        }

        if ($this->foreignKeyRulesMatch($rules, 'RESTRICT', $expectedDeleteRule)) {
            return false;
        }

        $this->db->exec("
            ALTER TABLE {$table}
            DROP FOREIGN KEY {$constraint}
        ");

        return true;
    }

    private function foreignKeyRules(string $table, string $constraint): ?array
    {
        $statement = $this->db->prepare("
            SELECT UPPER(UPDATE_RULE), UPPER(DELETE_RULE)
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $constraint]);
        $rules = $statement->fetch(PDO::FETCH_NUM);

        if (!is_array($rules)) {
            return null;
        }

        return [
            'update_rule' => (string) ($rules[0] ?? ''),
            'delete_rule' => (string) ($rules[1] ?? ''),
        ];
    }

    private function foreignKeyRulesMatch(array $rules, string $onUpdate, string $onDelete): bool
    {
        return ($rules['update_rule'] ?? '') === strtoupper($onUpdate)
            && ($rules['delete_rule'] ?? '') === strtoupper($onDelete);
    }
}
