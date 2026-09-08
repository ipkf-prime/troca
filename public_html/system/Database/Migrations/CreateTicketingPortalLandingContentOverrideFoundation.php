<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;


/**
 * TICKETING_PORTAL_LANDING_CONTENT_OVERRIDE_FOUNDATION_V1
 *
 * Global public landing content remains owned by core.primary.
 *
 * Ticketing stores only sparse Portal-specific overrides:
 *
 * - settings: key/value override of global landing settings;
 * - items: upsert/hide overlay by (item_type, code).
 *
 * Runtime precedence:
 *
 * Global Landing
 *      -> Portal Landing Settings
 *      -> Portal Landing Item Overlay
 *
 * Branding remains independent:
 *
 * Global Theme
 *      -> Project Brand Override
 *      -> Portal Brand Override
 */
final class CreateTicketingPortalLandingContentOverrideFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createPortalLandingSettings();

        $this->createPortalLandingItems();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Portal landing configuration becomes durable
         * operational configuration/audit data.
         */
    }


    private function createPortalLandingSettings(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_support_portal_landing_settings
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                portal_id BIGINT UNSIGNED
                    NOT NULL,

                setting_key VARCHAR(120)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                setting_value MEDIUMTEXT
                    NULL,

                value_type VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'string',

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

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_portal_landing_settings_key_unique
                    (
                        portal_id,
                        setting_key
                    ),

                KEY
                    ticketing_portal_landing_settings_active_index
                    (
                        portal_id,
                        is_active,
                        setting_key
                    ),


                CONSTRAINT
                    ticketing_portal_landing_settings_portal_fk

                FOREIGN KEY
                    (
                        portal_id
                    )

                REFERENCES
                    ticketing_support_portals
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


    private function createPortalLandingItems(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_support_portal_landing_items
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                portal_id BIGINT UNSIGNED
                    NOT NULL,

                item_type VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                code VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                override_mode VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'upsert',

                eyebrow VARCHAR(255)
                    NULL,

                title VARCHAR(255)
                    NULL,

                body TEXT
                    NULL,

                image_url VARCHAR(500)
                    NULL,

                mobile_image_url VARCHAR(500)
                    NULL,

                action_text VARCHAR(255)
                    NULL,

                action_url VARCHAR(1000)
                    NULL,

                action_target VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT '_self',

                icon VARCHAR(100)
                    NULL,

                sort_order INT UNSIGNED
                    NOT NULL
                    DEFAULT 100,

                is_active TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                starts_at DATETIME
                    NULL,

                ends_at DATETIME
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
                    ticketing_portal_landing_items_reference_unique
                    (
                        public_reference
                    ),

                UNIQUE KEY
                    ticketing_portal_landing_items_identity_unique
                    (
                        portal_id,
                        item_type,
                        code
                    ),

                KEY
                    ticketing_portal_landing_items_runtime_index
                    (
                        portal_id,
                        item_type,
                        is_active,
                        sort_order,
                        id
                    ),


                CONSTRAINT
                    ticketing_portal_landing_items_portal_fk

                FOREIGN KEY
                    (
                        portal_id
                    )

                REFERENCES
                    ticketing_support_portals
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
