<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class CreateSchedulerFoundation extends Migration
{
    public function up(): void
    {
        $options =
            'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci';

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS scheduler_job_definitions
            (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                job_key VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                application_key VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255) NOT NULL,
                description TEXT NULL,

                scope_model VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'global',

                default_interval_minutes
                    INT UNSIGNED NOT NULL DEFAULT 5,

                is_active
                    TINYINT(1) NOT NULL DEFAULT 1,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY scheduler_job_definitions_key_unique
                    (job_key),

                INDEX scheduler_job_definitions_application_index
                    (application_key, is_active)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS scheduler_job_bindings
            (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                job_definition_id
                    BIGINT UNSIGNED NOT NULL,

                scope_type VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                scope_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                scope_title_snapshot
                    VARCHAR(255) NULL,

                scope_context_json
                    LONGTEXT NULL,

                scope_available
                    TINYINT(1) NOT NULL DEFAULT 1,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY scheduler_job_bindings_scope_unique
                (
                    job_definition_id,
                    scope_type,
                    scope_reference
                ),

                INDEX scheduler_job_bindings_available_index
                (
                    job_definition_id,
                    scope_available
                )
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS scheduler_schedules
            (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                binding_id
                    BIGINT UNSIGNED NOT NULL,

                state_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                schedule_type VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'interval',

                interval_minutes
                    INT UNSIGNED NOT NULL DEFAULT 5,

                timezone VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'Asia/Tehran',

                next_run_at DATETIME NULL,
                last_run_at DATETIME NULL,

                last_status_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                consecutive_failures
                    INT UNSIGNED NOT NULL DEFAULT 0,

                timeout_seconds
                    INT UNSIGNED NOT NULL DEFAULT 300,

                locked_until DATETIME NULL,

                lock_token CHAR(32)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                updated_by_user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY scheduler_schedules_binding_unique
                    (binding_id),

                INDEX scheduler_schedules_due_index
                    (
                        state_code,
                        schedule_type,
                        next_run_at
                    ),

                INDEX scheduler_schedules_lock_index
                    (locked_until)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS scheduler_job_runs
            (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                binding_id
                    BIGINT UNSIGNED NOT NULL,

                schedule_id
                    BIGINT UNSIGNED NOT NULL,

                trigger_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                status_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,

                duration_ms
                    BIGINT UNSIGNED NULL,

                summary_json
                    LONGTEXT NULL,

                error_message
                    TEXT NULL,

                triggered_by_user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                worker_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY scheduler_job_runs_reference_unique
                    (public_reference),

                INDEX scheduler_job_runs_binding_index
                    (binding_id, id),

                INDEX scheduler_job_runs_status_index
                    (status_code, started_at)
            ) {$options}
        ");
    }


    public function down(): void
    {
    }
}
