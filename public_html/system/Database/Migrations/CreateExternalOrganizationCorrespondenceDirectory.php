<?php

namespace IPKF\Database\Migrations;

class CreateExternalOrganizationCorrespondenceDirectory extends Migration
{
    public function up(): void
    {
        $this->createExternalOrganizations();
        $this->createContactPoints();
        $this->createContactMethods();
        $this->createContactAddresses();
    }

    public function down(): void
    {
    }

    private function createExternalOrganizations(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS external_organizations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title_fa VARCHAR(255) NOT NULL,
                title_en VARCHAR(255) NULL,
                short_title VARCHAR(150) NULL,

                national_id VARCHAR(80) NULL,
                registration_number VARCHAR(100) NULL,
                website_url VARCHAR(1000) NULL,

                notes TEXT NULL,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'active',

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY external_org_public_ref_unique (
                    public_reference
                ),

                INDEX external_org_title_index (
                    title_fa
                ),

                INDEX external_org_national_id_index (
                    national_id
                ),

                INDEX external_org_status_index (
                    status
                )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createContactPoints(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS external_organization_contact_points (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                external_organization_id
                    BIGINT UNSIGNED NOT NULL,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255) NOT NULL,

                point_kind_code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'secretariat',

                contact_person_name VARCHAR(255) NULL,
                contact_person_title VARCHAR(255) NULL,

                business_hours VARCHAR(255) NULL,

                preferred_dispatch_channel_code
                    VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'active',

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY external_org_point_ref_unique (
                    public_reference
                ),

                UNIQUE KEY external_org_point_code_unique (
                    external_organization_id,
                    code
                ),

                INDEX external_org_point_org_index (
                    external_organization_id
                ),

                INDEX external_org_point_kind_index (
                    point_kind_code
                ),

                INDEX external_org_point_primary_index (
                    external_organization_id,
                    is_primary,
                    status
                ),

                CONSTRAINT external_org_point_org_fk
                    FOREIGN KEY (
                        external_organization_id
                    )
                    REFERENCES external_organizations(id)
                    ON DELETE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createContactMethods(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS external_organization_contact_methods (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                contact_point_id
                    BIGINT UNSIGNED NOT NULL,

                contact_type_id
                    BIGINT UNSIGNED NOT NULL,

                value VARCHAR(1000) NOT NULL,
                normalized_value VARCHAR(1000) NULL,
                label VARCHAR(150) NULL,

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                is_verified TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                supports_dispatch TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                supports_followup TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                sort_order INT
                    NOT NULL
                    DEFAULT 0,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'active',

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY external_org_method_ref_unique (
                    public_reference
                ),

                INDEX external_org_method_point_index (
                    contact_point_id
                ),

                INDEX external_org_method_type_index (
                    contact_type_id
                ),

                INDEX external_org_method_dispatch_index (
                    contact_point_id,
                    supports_dispatch,
                    status
                ),

                CONSTRAINT external_org_method_point_fk
                    FOREIGN KEY (
                        contact_point_id
                    )
                    REFERENCES external_organization_contact_points(id)
                    ON DELETE CASCADE
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createContactAddresses(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS external_organization_contact_addresses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference CHAR(36)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                contact_point_id
                    BIGINT UNSIGNED NOT NULL,

                address_type_id
                    BIGINT UNSIGNED NULL,

                geographic_location_id
                    BIGINT UNSIGNED NULL,

                district VARCHAR(150) NULL,
                address_line TEXT NOT NULL,
                postal_code VARCHAR(30) NULL,

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                supports_dispatch TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                status VARCHAR(30)
                    NOT NULL
                    DEFAULT 'active',

                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                UNIQUE KEY external_org_address_ref_unique (
                    public_reference
                ),

                INDEX external_org_address_point_index (
                    contact_point_id
                ),

                INDEX external_org_address_geo_index (
                    geographic_location_id
                ),

                CONSTRAINT external_org_address_point_fk
                    FOREIGN KEY (
                        contact_point_id
                    )
                    REFERENCES external_organization_contact_points(id)
                    ON DELETE CASCADE
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }
}
