<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;


/**
 * T3F_C1_DYNAMIC_UI_CONTENT_FOUNDATION_V1
 *
 * Shared control plane: core.primary
 *
 * Scope model is generic. Ticketing-specific Project/Portal
 * columns are deliberately NOT part of the shared schema.
 *
 * Examples:
 *
 * global
 *
 * module:ticketing
 *
 * scope:ticketing:
 *   project:TSP-NEP
 *
 * scope:ticketing:
 *   project:TSP-NEP:
 *   portal:TSPT-...
 *
 * scope:work:
 *   workspace:WS-001:
 *   team:TEAM-02
 *
 * Cross-database foreign keys are forbidden.
 */
final class CreateDynamicUiContentFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createDefinitions();
        $this->createOverrides();
    }


    public function down(): void
    {
        /*
         * Deliberately non-destructive.
         *
         * Dynamic UI content becomes durable operational
         * configuration and is not automatically dropped.
         */
    }


    private function createDefinitions(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ui_content_definitions
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(48)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                content_key VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                content_type VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                default_locale VARCHAR(15)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'fa',

                http_status SMALLINT UNSIGNED
                    NULL,

                description VARCHAR(1000)
                    NULL,

                metadata_json LONGTEXT
                    NULL,

                is_active TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                created_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                updated_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at TIMESTAMP
                    NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP
                    NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ui_content_definitions_reference_unique
                    (
                        public_reference
                    ),

                UNIQUE KEY
                    ui_content_definitions_key_unique
                    (
                        content_key
                    ),

                KEY
                    ui_content_definitions_type_active_index
                    (
                        content_type,
                        is_active,
                        id
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createOverrides(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ui_content_overrides
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(48)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                definition_id BIGINT UNSIGNED
                    NOT NULL,

                scope_type VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                scope_key VARCHAR(512)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                module_key VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                scope_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                scope_path_json LONGTEXT
                    NULL,

                locale VARCHAR(15)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'fa',

                title VARCHAR(500)
                    NULL,

                body LONGTEXT
                    NULL,

                icon_code VARCHAR(100)
                    NULL,

                severity_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                layout_variant VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                visibility_mode VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'inherit',

                primary_action_code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                primary_action_label VARCHAR(255)
                    NULL,

                secondary_action_code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                secondary_action_label VARCHAR(255)
                    NULL,

                metadata_json LONGTEXT
                    NULL,

                starts_at DATETIME
                    NULL,

                ends_at DATETIME
                    NULL,

                is_active TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                created_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                updated_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                created_at TIMESTAMP
                    NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP
                    NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ui_content_overrides_reference_unique
                    (
                        public_reference
                    ),

                UNIQUE KEY
                    ui_content_overrides_identity_unique
                    (
                        definition_id,
                        scope_key,
                        locale
                    ),

                KEY
                    ui_content_overrides_runtime_index
                    (
                        definition_id,
                        is_active,
                        scope_type,
                        locale
                    ),

                KEY
                    ui_content_overrides_module_scope_index
                    (
                        module_key,
                        scope_type,
                        scope_reference,
                        is_active
                    ),


                CONSTRAINT
                    ui_content_overrides_definition_fk

                FOREIGN KEY
                    (
                        definition_id
                    )

                REFERENCES
                    ui_content_definitions
                    (
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
