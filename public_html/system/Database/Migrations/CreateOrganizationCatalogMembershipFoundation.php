<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class CreateOrganizationCatalogMembershipFoundation
    extends Migration
{
    /*
     * Compatibility boundary:
     *
     * organizations/persons/users are established Core entities.
     * Their storage characteristics predate this commercial
     * data-pack layer, so references to them are logical,
     * indexed application references rather than hard FKs.
     *
     * Foreign keys are retained between tables owned by this
     * foundation itself.
     */

    public function up(): void
    {
        $this->createCatalogs();
        $this->createCatalogEntries();
        $this->createIdentifierSchemes();
        $this->createExternalIdentifiers();
        $this->createMemberships();
        $this->createMembershipVerifications();
    }


    public function down(): void
    {
        /*
         * Deliberately non-destructive.
         *
         * Catalogs and organization identities can be referenced
         * by multiple commercial modules and historical records.
         */
    }


    private function createCatalogs(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            organization_catalogs
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT NULL,

                scope_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'shared',

                owner_type_code VARCHAR(50)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                owner_reference VARCHAR(150)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                is_detachable TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    org_catalogs_public_ref_unique
                    (public_reference),

                UNIQUE KEY
                    org_catalogs_code_unique
                    (code),

                INDEX
                    org_catalogs_owner_index
                    (
                        owner_type_code,
                        owner_reference
                    ),

                INDEX
                    org_catalogs_status_index
                    (status)
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createCatalogEntries(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            organization_catalog_entries
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                catalog_id BIGINT UNSIGNED
                    NOT NULL,

                organization_id BIGINT UNSIGNED
                    NOT NULL,

                record_mode_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'owned',

                source_record_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    org_catalog_entries_catalog_org_unique
                    (
                        catalog_id,
                        organization_id
                    ),

                INDEX
                    org_catalog_entries_org_index
                    (organization_id),

                INDEX
                    org_catalog_entries_source_index
                    (
                        catalog_id,
                        source_record_reference
                    ),

                INDEX
                    org_catalog_entries_status_index
                    (
                        catalog_id,
                        status
                    ),

                CONSTRAINT
                    org_catalog_entries_catalog_fk
                    FOREIGN KEY (catalog_id)
                    REFERENCES organization_catalogs(id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createIdentifierSchemes(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            organization_identifier_schemes
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                code VARCHAR(120)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT NULL,

                value_kind_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'text',

                uniqueness_scope_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'catalog',

                is_sensitive TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    org_identifier_schemes_code_unique
                    (code),

                INDEX
                    org_identifier_schemes_status_index
                    (status)
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createExternalIdentifiers(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            organization_external_identifiers
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                organization_id BIGINT UNSIGNED
                    NOT NULL,

                scheme_id BIGINT UNSIGNED
                    NOT NULL,

                catalog_id BIGINT UNSIGNED
                    NULL,

                identifier_value VARCHAR(190)
                    NOT NULL,

                normalized_value VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                source_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                verified_at DATETIME NULL,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                INDEX
                    org_external_ids_org_index
                    (
                        organization_id,
                        status
                    ),

                INDEX
                    org_external_ids_scheme_value_index
                    (
                        scheme_id,
                        normalized_value
                    ),

                INDEX
                    org_external_ids_catalog_index
                    (
                        catalog_id,
                        scheme_id,
                        status
                    ),

                UNIQUE KEY
                    org_external_ids_org_scheme_value_unique
                    (
                        organization_id,
                        scheme_id,
                        normalized_value,
                        catalog_id
                    ),

                CONSTRAINT
                    org_external_ids_scheme_fk
                    FOREIGN KEY (scheme_id)
                    REFERENCES organization_identifier_schemes(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    org_external_ids_catalog_fk
                    FOREIGN KEY (catalog_id)
                    REFERENCES organization_catalogs(id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createMemberships(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            organization_memberships
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                organization_id BIGINT UNSIGNED
                    NOT NULL,

                person_id BIGINT UNSIGNED
                    NOT NULL,

                user_id BIGINT UNSIGNED
                    NULL,

                source_catalog_id BIGINT UNSIGNED
                    NULL,

                role_code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'member',

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                verification_state_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'unverified',

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                source_code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                source_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                valid_from DATETIME NULL,
                valid_until DATETIME NULL,

                verified_at DATETIME NULL,

                approved_by_user_id BIGINT UNSIGNED
                    NULL,

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    org_memberships_public_ref_unique
                    (public_reference),

                INDEX
                    org_memberships_org_status_index
                    (
                        organization_id,
                        status
                    ),

                INDEX
                    org_memberships_person_status_index
                    (
                        person_id,
                        status
                    ),

                INDEX
                    org_memberships_user_status_index
                    (
                        user_id,
                        status
                    ),

                INDEX
                    org_memberships_org_role_index
                    (
                        organization_id,
                        role_code,
                        status
                    ),

                INDEX
                    org_memberships_catalog_index
                    (
                        source_catalog_id,
                        status
                    ),

                CONSTRAINT
                    org_memberships_catalog_fk
                    FOREIGN KEY (source_catalog_id)
                    REFERENCES organization_catalogs(id)
                    ON DELETE SET NULL
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createMembershipVerifications(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            organization_membership_verifications
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                membership_id BIGINT UNSIGNED
                    NOT NULL,

                verification_type_code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'pending',

                evidence_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                verified_by_user_id BIGINT UNSIGNED
                    NULL,

                verified_at DATETIME NULL,

                metadata_json LONGTEXT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    org_membership_verify_ref_unique
                    (public_reference),

                INDEX
                    org_membership_verify_member_index
                    (
                        membership_id,
                        status_code
                    ),

                INDEX
                    org_membership_verify_type_index
                    (
                        verification_type_code,
                        status_code
                    ),

                CONSTRAINT
                    org_membership_verify_membership_fk
                    FOREIGN KEY (membership_id)
                    REFERENCES organization_memberships(id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }
}
