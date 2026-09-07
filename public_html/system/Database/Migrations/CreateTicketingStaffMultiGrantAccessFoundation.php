<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * TICKETING_STAFF_MULTI_GRANT_ACCESS_FOUNDATION_V1
 *
 * Project-local data access grants for Ticketing staff.
 *
 * Semantics:
 *
 *   Grant A OR Grant B OR Grant C
 *
 * Inside one Grant:
 *
 *   Dimension Rule A
 *   AND Dimension Rule B
 *   AND Resource Rule A
 *   AND Resource Rule B
 *
 * Inside one Dimension/Resource rule:
 *
 *   selected values may match ANY or ALL.
 *
 * Team/Layer capabilities remain authoritative for operational actions.
 * Grants constrain the data/resource scope on which those capabilities
 * may later be exercised.
 *
 * No business-specific dimension, value, service, topic or Realm is
 * seeded here.
 */
final class CreateTicketingStaffMultiGrantAccessFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createGrants();
        $this->createDimensionRules();
        $this->createDimensionValues();
        $this->createResourceRules();
        $this->createResourceValues();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Access grants can participate in authorization/audit history.
         */
    }


    private function createGrants(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_access_grants
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                project_member_id BIGINT UNSIGNED
                    NOT NULL,

                title VARCHAR(255)
                    NULL,


                /*
                 * Full project data scope must always be explicit.
                 *
                 * A non-unrestricted grant with no rules is fail-closed.
                 */
                is_unrestricted TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                valid_from DATETIME
                    NULL,

                valid_until DATETIME
                    NULL,

                sort_order INT UNSIGNED
                    NOT NULL
                    DEFAULT 0,

                metadata_json LONGTEXT
                    NULL,

                created_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                updated_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_access_grants_reference_unique
                    (public_reference),

                KEY
                    ticketing_access_grants_member_status_index
                    (
                        project_member_id,
                        status,
                        valid_from,
                        valid_until,
                        sort_order,
                        id
                    ),

                KEY
                    ticketing_access_grants_unrestricted_index
                    (
                        project_member_id,
                        is_unrestricted,
                        status
                    ),


                CONSTRAINT
                    ticketing_access_grants_member_fk

                FOREIGN KEY (project_member_id)
                    REFERENCES
                        ticketing_support_project_members(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createDimensionRules(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_access_grant_dimension_rules
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                access_grant_id BIGINT UNSIGNED
                    NOT NULL,

                dimension_id BIGINT UNSIGNED
                    NOT NULL,


                /*
                 * any:
                 *   at least one selected value must match.
                 *
                 * all:
                 *   every selected value must match.
                 */
                match_mode_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'any',

                include_descendants TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                sort_order INT UNSIGNED
                    NOT NULL
                    DEFAULT 0,

                metadata_json LONGTEXT
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_access_grant_dimension_rule_unique
                    (
                        access_grant_id,
                        dimension_id
                    ),

                UNIQUE KEY
                    ticketing_access_grant_dimension_rule_identity_unique
                    (
                        id,
                        dimension_id
                    ),

                KEY
                    ticketing_access_grant_dimension_rule_lookup_index
                    (
                        dimension_id,
                        status,
                        access_grant_id
                    ),


                CONSTRAINT
                    ticketing_access_grant_dimension_rule_grant_fk

                FOREIGN KEY (access_grant_id)
                    REFERENCES
                        ticketing_access_grants(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,


                CONSTRAINT
                    ticketing_access_grant_dimension_rule_dimension_fk

                FOREIGN KEY (dimension_id)
                    REFERENCES
                        ticketing_scope_dimensions(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createDimensionValues(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_access_grant_dimension_values
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                dimension_rule_id BIGINT UNSIGNED
                    NOT NULL,

                dimension_id BIGINT UNSIGNED
                    NOT NULL,

                dimension_value_id BIGINT UNSIGNED
                    NOT NULL,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                sort_order INT UNSIGNED
                    NOT NULL
                    DEFAULT 0,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_access_grant_dimension_value_unique
                    (
                        dimension_rule_id,
                        dimension_value_id
                    ),

                KEY
                    ticketing_access_grant_dimension_value_lookup_index
                    (
                        dimension_id,
                        dimension_value_id,
                        status
                    ),


                CONSTRAINT
                    ticketing_access_grant_dimension_value_rule_fk

                FOREIGN KEY
                    (
                        dimension_rule_id,
                        dimension_id
                    )

                    REFERENCES
                        ticketing_access_grant_dimension_rules
                        (
                            id,
                            dimension_id
                        )

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,


                CONSTRAINT
                    ticketing_access_grant_dimension_value_value_fk

                FOREIGN KEY
                    (
                        dimension_id,
                        dimension_value_id
                    )

                    REFERENCES
                        ticketing_scope_dimension_values
                        (
                            dimension_id,
                            id
                        )

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createResourceRules(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_access_grant_resource_rules
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                access_grant_id BIGINT UNSIGNED
                    NOT NULL,


                /*
                 * Generic Ticketing resource dimension.
                 *
                 * Current/future examples can include:
                 * service, topic, queue, layer, node, team, realm.
                 *
                 * No fixed ENUM is used.
                 */
                resource_type_code VARCHAR(50)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                match_mode_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'any',

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                sort_order INT UNSIGNED
                    NOT NULL
                    DEFAULT 0,

                metadata_json LONGTEXT
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_access_grant_resource_rule_unique
                    (
                        access_grant_id,
                        resource_type_code
                    ),

                UNIQUE KEY
                    ticketing_access_grant_resource_rule_identity_unique
                    (
                        id,
                        resource_type_code
                    ),

                KEY
                    ticketing_access_grant_resource_rule_lookup_index
                    (
                        resource_type_code,
                        status,
                        access_grant_id
                    ),


                CONSTRAINT
                    ticketing_access_grant_resource_rule_grant_fk

                FOREIGN KEY (access_grant_id)
                    REFERENCES
                        ticketing_access_grants(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createResourceValues(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_access_grant_resource_values
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                resource_rule_id BIGINT UNSIGNED
                    NOT NULL,

                resource_type_code VARCHAR(50)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                resource_reference VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NOT NULL,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                sort_order INT UNSIGNED
                    NOT NULL
                    DEFAULT 0,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_access_grant_resource_value_unique
                    (
                        resource_rule_id,
                        resource_reference
                    ),

                KEY
                    ticketing_access_grant_resource_value_lookup_index
                    (
                        resource_type_code,
                        resource_reference,
                        status
                    ),


                CONSTRAINT
                    ticketing_access_grant_resource_value_rule_fk

                FOREIGN KEY
                    (
                        resource_rule_id,
                        resource_type_code
                    )

                    REFERENCES
                        ticketing_access_grant_resource_rules
                        (
                            id,
                            resource_type_code
                        )

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }
}
