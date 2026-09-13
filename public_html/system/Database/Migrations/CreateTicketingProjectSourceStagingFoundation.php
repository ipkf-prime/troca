<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * TICKETING_PROJECT_SOURCE_CONFIGURATION_AND_STAGING_V1
 *
 * Generic project-owned source configuration and immutable staging
 * envelopes for catalog snapshots.
 *
 * Boundaries:
 * - no concrete source adapter is seeded;
 * - no network transport is assumed;
 * - no credentials are stored directly in connector_config_json;
 * - staging does not mutate canonical dimension values;
 * - apply/materialization is a later explicit stage.
 */
final class CreateTicketingProjectSourceStagingFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createSources();
        $this->createRuns();
        $this->createStageRows();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Source configuration and staged snapshots can become
         * operational/audit evidence and are not automatically dropped.
         */
    }


    private function createSources(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_project_sources
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

                driver_code VARCHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                catalog_code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                /*
                 * The target project-defined dimension is resolved by
                 * project_id + dimension_code at runtime.
                 *
                 * Using the code avoids an unsafe cross-project
                 * dimension-id binding before a trusted resolver checks
                 * ownership.
                 */
                dimension_code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                /*
                 * Reference to a secret/credential facility.
                 * Secret material itself must not be stored here.
                 */
                credential_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                /*
                 * Non-secret connector options only.
                 */
                connector_config_json LONGTEXT
                    NULL,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'inactive',

                last_successful_snapshot_token VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
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
                    ticketing_project_sources_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_project_sources_project_code_unique
                    (
                        project_id,
                        code
                    ),

                KEY
                    ticketing_project_sources_project_status_index
                    (
                        project_id,
                        status,
                        id
                    ),

                KEY
                    ticketing_project_sources_driver_index
                    (
                        driver_code,
                        status,
                        id
                    ),

                KEY
                    ticketing_project_sources_dimension_index
                    (
                        project_id,
                        dimension_code,
                        status,
                        id
                    ),


                CONSTRAINT
                    ticketing_project_sources_project_fk

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


    private function createRuns(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_project_source_runs
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                source_id BIGINT UNSIGNED
                    NOT NULL,

                trigger_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'manual',

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'staging',

                source_snapshot_token VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NULL,

                cursor_start VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NULL,

                cursor_end VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NULL,

                item_count INT UNSIGNED
                    NOT NULL
                    DEFAULT 0,

                valid_count INT UNSIGNED
                    NOT NULL
                    DEFAULT 0,

                invalid_count INT UNSIGNED
                    NOT NULL
                    DEFAULT 0,

                error_summary TEXT
                    NULL,

                metadata_json LONGTEXT
                    NULL,

                requested_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                started_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                finished_at TIMESTAMP NULL
                    DEFAULT NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_project_source_runs_reference_unique
                    (public_reference),

                KEY
                    ticketing_project_source_runs_source_status_index
                    (
                        source_id,
                        status,
                        id
                    ),

                KEY
                    ticketing_project_source_runs_snapshot_index
                    (
                        source_id,
                        source_snapshot_token
                    ),


                CONSTRAINT
                    ticketing_project_source_runs_source_fk

                FOREIGN KEY (source_id)
                    REFERENCES
                        ticketing_project_sources(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createStageRows(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_project_source_stage_rows
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                run_id BIGINT UNSIGNED
                    NOT NULL,

                stage_row_number INT UNSIGNED
                    NOT NULL,

                source_reference VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NOT NULL,

                parent_source_reference VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NULL,

                title VARCHAR(255)
                    NOT NULL,

                source_status VARCHAR(30)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NOT NULL,

                attributes_json LONGTEXT
                    NULL,

                payload_hash CHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                validation_status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'pending',

                validation_errors_json LONGTEXT
                    NULL,

                staged_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_project_source_stage_rows_run_row_unique
                    (
                        run_id,
                        stage_row_number
                    ),

                UNIQUE KEY
                    ticketing_project_source_stage_rows_run_reference_unique
                    (
                        run_id,
                        source_reference
                    ),

                KEY
                    ticketing_project_source_stage_rows_parent_index
                    (
                        run_id,
                        parent_source_reference
                    ),

                KEY
                    ticketing_project_source_stage_rows_validation_index
                    (
                        run_id,
                        validation_status,
                        id
                    ),


                CONSTRAINT
                    ticketing_project_source_stage_rows_run_fk

                FOREIGN KEY (run_id)
                    REFERENCES
                        ticketing_project_source_runs(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }
}
