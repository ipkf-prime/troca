<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * TICKETING_DYNAMIC_SCOPE_DIMENSION_FOUNDATION_V1
 *
 * Project-defined resource dimensions for Ticketing.
 *
 * This is NOT a second RBAC engine.
 *
 * Core roles, permissions and assignment scopes remain authoritative
 * for platform authorization. Ticketing dimensions describe the
 * resource facts that will later be evaluated together with:
 *
 * - Core permission / access context
 * - Ticketing operational Layer / Node / Team
 * - Service / Topic
 * - project-member multi-grant policies
 * - future Realm policies
 *
 * No business-specific dimensions or values are seeded here.
 */
final class CreateTicketingDynamicScopeDimensionFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createDimensions();
        $this->createValues();
        $this->createValuePaths();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Dimension definitions and values can become part of
         * authorization and ticket audit history. They must not
         * disappear through an automatic rollback.
         */
    }


    private function createDimensions(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_scope_dimensions
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                project_id BIGINT UNSIGNED
                    NOT NULL,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT
                    NULL,


                /*
                 * reference:
                 *   stable entity/value reference
                 *
                 * code:
                 *   controlled scalar code
                 *
                 * text:
                 *   free scalar value
                 */
                value_kind_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'reference',


                /*
                 * single / multiple
                 */
                cardinality_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'single',


                /*
                 * flat / tree
                 */
                hierarchy_mode_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'flat',


                /*
                 * managed:
                 *   values managed by Ticketing
                 *
                 * reference:
                 *   values backed by an IPKF reference provider
                 *
                 * external:
                 *   values backed by an external provider
                 */
                source_mode_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'managed',

                source_key VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                source_config_json LONGTEXT
                    NULL,

                supports_descendants TINYINT(1)
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
                    ticketing_scope_dimensions_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_scope_dimensions_project_code_unique
                    (
                        project_id,
                        code
                    ),

                KEY
                    ticketing_scope_dimensions_project_status_index
                    (
                        project_id,
                        status,
                        sort_order,
                        id
                    ),

                KEY
                    ticketing_scope_dimensions_source_index
                    (
                        source_mode_code,
                        source_key,
                        status
                    ),


                CONSTRAINT
                    ticketing_scope_dimensions_project_fk

                FOREIGN KEY (project_id)
                    REFERENCES
                        ticketing_support_projects(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createValues(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_scope_dimension_values
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                dimension_id BIGINT UNSIGNED
                    NOT NULL,

                parent_value_id BIGINT UNSIGNED
                    NULL,

                /*
                 * Canonical stable value inside the dimension.
                 *
                 * Examples are intentionally not seeded here.
                 * The value may originate from managed,
                 * reference or external sources.
                 */
                value_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                code VARCHAR(120)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                title VARCHAR(255)
                    NOT NULL,

                source_reference VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NULL,

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
                    ticketing_scope_dimension_values_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_scope_dimension_values_value_unique
                    (
                        dimension_id,
                        value_reference
                    ),

                UNIQUE KEY
                    ticketing_scope_dimension_values_code_unique
                    (
                        dimension_id,
                        code
                    ),

                /*
                 * Required by composite self-references which enforce
                 * that parent/ancestor/descendant values belong to
                 * the same dimension.
                 */
                UNIQUE KEY
                    ticketing_scope_dimension_values_dimension_id_unique
                    (
                        dimension_id,
                        id
                    ),

                KEY
                    ticketing_scope_dimension_values_status_index
                    (
                        dimension_id,
                        status,
                        sort_order,
                        id
                    ),

                KEY
                    ticketing_scope_dimension_values_parent_index
                    (
                        dimension_id,
                        parent_value_id,
                        status
                    ),

                KEY
                    ticketing_scope_dimension_values_source_index
                    (
                        dimension_id,
                        source_reference,
                        status
                    ),


                CONSTRAINT
                    ticketing_scope_dimension_values_dimension_fk

                FOREIGN KEY (dimension_id)
                    REFERENCES
                        ticketing_scope_dimensions(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,


                CONSTRAINT
                    ticketing_scope_dimension_values_parent_fk

                FOREIGN KEY
                    (
                        dimension_id,
                        parent_value_id
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


    private function createValuePaths(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_scope_dimension_value_paths
            (
                dimension_id BIGINT UNSIGNED
                    NOT NULL,

                ancestor_value_id BIGINT UNSIGNED
                    NOT NULL,

                descendant_value_id BIGINT UNSIGNED
                    NOT NULL,

                /*
                 * 0 = self
                 * 1 = direct child
                 * n = descendant distance
                 */
                depth INT UNSIGNED
                    NOT NULL,


                PRIMARY KEY
                    (
                        dimension_id,
                        ancestor_value_id,
                        descendant_value_id
                    ),

                KEY
                    ticketing_scope_dimension_paths_ancestor_index
                    (
                        ancestor_value_id,
                        descendant_value_id,
                        depth
                    ),

                KEY
                    ticketing_scope_dimension_paths_descendant_index
                    (
                        descendant_value_id,
                        ancestor_value_id,
                        depth
                    ),

                KEY
                    ticketing_scope_dimension_paths_depth_index
                    (
                        dimension_id,
                        depth
                    ),


                CONSTRAINT
                    ticketing_scope_dimension_paths_ancestor_fk

                FOREIGN KEY
                    (
                        dimension_id,
                        ancestor_value_id
                    )

                    REFERENCES
                        ticketing_scope_dimension_values
                        (
                            dimension_id,
                            id
                        )

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,


                CONSTRAINT
                    ticketing_scope_dimension_paths_descendant_fk

                FOREIGN KEY
                    (
                        dimension_id,
                        descendant_value_id
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
}
