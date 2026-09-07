<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * TICKETING_SCOPE_SUBJECT_FACTS_FOUNDATION_V1
 *
 * Associates project-defined scope-dimension values with stable subjects.
 *
 * The subject is intentionally polymorphic:
 *
 *   subject_type_code + subject_reference
 *
 * Current consumers may use participant or organization subjects.
 * Future consumers can add other stable subject kinds without changing
 * this schema.
 *
 * Cross-database Core organization references are intentionally not
 * constrained by a foreign key. Validation belongs to the application
 * service/resolver layer.
 *
 * The dimension/value relation is fully constrained inside Ticketing.
 *
 * No project/business-specific values are seeded by this migration.
 */
final class CreateTicketingScopeSubjectFactsFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createFacts();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Subject facts can become part of authorization decisions
         * and historical ticket-scope reconstruction.
         */
    }


    private function createFacts(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_scope_subject_facts
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,


                /*
                 * Project is derived from the dimension.
                 *
                 * Keeping project_id out of this table prevents a
                 * project/dimension mismatch by construction.
                 */
                dimension_id BIGINT UNSIGNED
                    NOT NULL,


                /*
                 * Examples of platform-level subject kinds:
                 *
                 * participant
                 * organization
                 *
                 * The column is not an ENUM/CHECK intentionally.
                 * New subject kinds remain configuration/service concerns.
                 */
                subject_type_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                subject_reference VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NOT NULL,


                /*
                 * The selected canonical value.
                 *
                 * Composite FK below guarantees the value belongs
                 * to the same dimension.
                 */
                dimension_value_id BIGINT UNSIGNED
                    NOT NULL,


                /*
                 * manual
                 * membership
                 * import
                 * sync
                 * external
                 *
                 * No enum is used so providers remain extensible.
                 */
                fact_source_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'manual',

                source_reference VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NULL,


                /*
                 * Useful where a dimension is multi-valued but one
                 * value is designated as the primary context.
                 */
                is_primary TINYINT(1)
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
                    ticketing_scope_subject_facts_reference_unique
                    (public_reference),


                /*
                 * Current/effective lookup by subject.
                 */
                KEY
                    ticketing_scope_subject_facts_subject_index
                    (
                        subject_type_code,
                        subject_reference,
                        status,
                        dimension_id
                    ),


                /*
                 * Reverse lookup used by authorization/query planning.
                 */
                KEY
                    ticketing_scope_subject_facts_dimension_index
                    (
                        dimension_id,
                        dimension_value_id,
                        status,
                        subject_type_code
                    ),


                /*
                 * Duplicate active facts and single-cardinality rules
                 * are enforced by the application service because
                 * historical/inactive rows are intentionally retained.
                 */
                KEY
                    ticketing_scope_subject_facts_exact_index
                    (
                        dimension_id,
                        subject_type_code,
                        subject_reference,
                        dimension_value_id,
                        status
                    ),

                KEY
                    ticketing_scope_subject_facts_validity_index
                    (
                        status,
                        valid_from,
                        valid_until
                    ),

                KEY
                    ticketing_scope_subject_facts_source_index
                    (
                        fact_source_code,
                        source_reference,
                        status
                    ),


                CONSTRAINT
                    ticketing_scope_subject_facts_dimension_fk

                FOREIGN KEY (dimension_id)
                    REFERENCES
                        ticketing_scope_dimensions(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,


                CONSTRAINT
                    ticketing_scope_subject_facts_value_fk

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
}
