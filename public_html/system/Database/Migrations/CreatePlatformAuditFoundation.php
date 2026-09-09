<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class CreatePlatformAuditFoundation extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_audit_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                public_reference
                    VARCHAR(48)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                schema_version
                    SMALLINT UNSIGNED
                    NOT NULL DEFAULT 1,

                event_code
                    VARCHAR(120)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                module_code
                    VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                action_code
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                actor_user_id
                    BIGINT UNSIGNED
                    NULL,

                actor_user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                actor_type
                    VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                actor_display_name_snapshot
                    VARCHAR(255)
                    NULL,

                target_type
                    VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                target_id
                    VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                target_reference
                    VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                result_code
                    VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                reason
                    VARCHAR(1000)
                    NULL,

                before_json
                    LONGTEXT
                    NULL,

                after_json
                    LONGTEXT
                    NULL,

                changed_fields_json
                    LONGTEXT
                    NULL,

                metadata_json
                    LONGTEXT
                    NULL,

                request_id
                    VARCHAR(128)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                correlation_id
                    VARCHAR(128)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                ip_address
                    VARCHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                user_agent
                    VARCHAR(512)
                    NULL,

                retention_policy_code
                    VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                retain_until
                    DATETIME
                    NULL,

                occurred_at
                    DATETIME
                    NOT NULL,

                created_at
                    TIMESTAMP
                    NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY
                    platform_audit_events_reference_unique (
                        public_reference
                    ),

                INDEX
                    platform_audit_events_event_time_index (
                        event_code,
                        occurred_at,
                        id
                    ),

                INDEX
                    platform_audit_events_module_time_index (
                        module_code,
                        occurred_at,
                        id
                    ),

                INDEX
                    platform_audit_events_actor_time_index (
                        actor_user_id,
                        occurred_at,
                        id
                    ),

                INDEX
                    platform_audit_events_actor_reference_time_index (
                        actor_user_reference,
                        occurred_at,
                        id
                    ),

                INDEX
                    platform_audit_events_target_time_index (
                        target_type,
                        target_id,
                        occurred_at,
                        id
                    ),

                INDEX
                    platform_audit_events_request_index (
                        request_id,
                        id
                    ),

                INDEX
                    platform_audit_events_correlation_index (
                        correlation_id,
                        id
                    ),

                INDEX
                    platform_audit_events_retention_index (
                        retain_until,
                        id
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    public function down(): void
    {
        /*
         * Deliberately non-destructive.
         *
         * Audit history is not dropped automatically.
         */
    }
}
