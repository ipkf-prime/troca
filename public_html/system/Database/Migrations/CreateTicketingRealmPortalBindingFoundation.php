<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;


/**
 * TICKETING_REALM_PORTAL_BINDING_FOUNDATION_V1
 *
 * Contract:
 *
 * - Core remains the global Landing / System Theme control plane.
 * - Project may define optional branding defaults.
 * - Realm owns one or more Portals.
 * - Realm.default_portal_id is the canonical default Portal pointer.
 * - Portal owns zero or more Host bindings.
 * - Host identity is environment configuration and is NOT seeded here.
 * - Portal branding stores overrides only; absence means inherit.
 * - platform-domain provisioning is deliberately not coupled here.
 */
final class CreateTicketingRealmPortalBindingFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createPortals();

        $this->createProjectBrandSettings();

        $this->createPortalBrandSettings();

        $this->createPortalHosts();

        $this->extendRealms();

        $this->backfillDefaultPortals();

        $this->backfillRealmDefaultPortal();

        $this->ensureRealmDefaultPortalConstraint();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Portal identity, Host identity and Branding configuration
         * become durable configuration/audit data.
         */
    }


    private function createPortals(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_support_portals
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                project_id BIGINT UNSIGNED
                    NOT NULL,

                realm_id BIGINT UNSIGNED
                    NOT NULL,

                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title VARCHAR(255)
                    NOT NULL,

                description TEXT
                    NULL,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                archived_at DATETIME
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
                    ticketing_support_portals_reference_unique
                    (
                        public_reference
                    ),

                UNIQUE KEY
                    ticketing_support_portals_project_realm_code_unique
                    (
                        project_id,
                        realm_id,
                        code
                    ),

                UNIQUE KEY
                    ticketing_support_portals_project_realm_identity_unique
                    (
                        project_id,
                        realm_id,
                        id
                    ),

                KEY
                    ticketing_support_portals_realm_status_index
                    (
                        project_id,
                        realm_id,
                        status,
                        archived_at,
                        id
                    ),


                CONSTRAINT
                    ticketing_support_portals_project_fk

                FOREIGN KEY
                    (
                        project_id
                    )

                REFERENCES
                    ticketing_support_projects
                    (
                        id
                    )

                ON DELETE RESTRICT
                ON UPDATE RESTRICT,


                CONSTRAINT
                    ticketing_support_portals_project_realm_fk

                FOREIGN KEY
                    (
                        project_id,
                        realm_id
                    )

                REFERENCES
                    ticketing_support_realms
                    (
                        project_id,
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


    private function createProjectBrandSettings(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_support_project_brand_settings
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                project_id BIGINT UNSIGNED
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
                    ticketing_project_brand_settings_key_unique
                    (
                        project_id,
                        setting_key
                    ),

                KEY
                    ticketing_project_brand_settings_active_index
                    (
                        project_id,
                        is_active,
                        setting_key
                    ),


                CONSTRAINT
                    ticketing_project_brand_settings_project_fk

                FOREIGN KEY
                    (
                        project_id
                    )

                REFERENCES
                    ticketing_support_projects
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


    private function createPortalBrandSettings(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_support_portal_brand_settings
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
                    ticketing_portal_brand_settings_key_unique
                    (
                        portal_id,
                        setting_key
                    ),

                KEY
                    ticketing_portal_brand_settings_active_index
                    (
                        portal_id,
                        is_active,
                        setting_key
                    ),


                CONSTRAINT
                    ticketing_portal_brand_settings_portal_fk

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


    private function createPortalHosts(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_support_portal_hosts
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                portal_id BIGINT UNSIGNED
                    NOT NULL,

                hostname VARCHAR(253)
                    CHARACTER SET ascii
                    COLLATE ascii_general_ci
                    NOT NULL,

                requires_https TINYINT(1)
                    NOT NULL
                    DEFAULT 1,

                canonical_host_slot TINYINT UNSIGNED
                    NULL,

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

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
                    ticketing_portal_hosts_reference_unique
                    (
                        public_reference
                    ),

                UNIQUE KEY
                    ticketing_portal_hosts_hostname_unique
                    (
                        hostname
                    ),

                UNIQUE KEY
                    ticketing_portal_hosts_canonical_unique
                    (
                        portal_id,
                        canonical_host_slot
                    ),

                KEY
                    ticketing_portal_hosts_status_index
                    (
                        portal_id,
                        status,
                        id
                    ),


                CONSTRAINT
                    ticketing_portal_hosts_portal_fk

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


    private function extendRealms(): void
    {
        if (
            !$this->columnExists(
                'ticketing_support_realms',
                'default_portal_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_realms

                ADD COLUMN
                    default_portal_id
                    BIGINT UNSIGNED
                    NULL

                AFTER
                    archived_at
            ");
        }


        if (
            !$this->indexExists(
                'ticketing_support_realms',
                'ticketing_support_realms_default_portal_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_realms

                ADD INDEX
                    ticketing_support_realms_default_portal_index
                    (
                        default_portal_id
                    )
            ");
        }


        if (
            !$this->indexExists(
                'ticketing_support_realms',
                'ticketing_support_realms_default_portal_identity_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_realms

                ADD INDEX
                    ticketing_support_realms_default_portal_identity_index
                    (
                        project_id,
                        id,
                        default_portal_id
                    )
            ");
        }
    }


    private function backfillDefaultPortals(): void
    {
        /*
         * Every existing Realm receives exactly one deterministic
         * default Portal.
         *
         * No Host is created here because Host is environment-specific.
         */
        $this->db->exec("
            INSERT INTO
                ticketing_support_portals
                (
                    public_reference,
                    project_id,
                    realm_id,
                    code,
                    title,
                    description,
                    status,
                    archived_at,
                    metadata_json,
                    created_by_user_reference,
                    updated_by_user_reference
                )

            SELECT
                CONCAT(
                    'TSPT-',
                    UPPER(
                        SUBSTRING(
                            SHA2(
                                CONCAT(
                                    'ticketing-default-portal:',
                                    r.public_reference
                                ),
                                256
                            ),
                            1,
                            20
                        )
                    )
                ),

                r.project_id,

                r.id,

                'default',

                r.title,

                NULL,

                CASE
                    WHEN
                        r.status = 'active'
                        AND r.archived_at IS NULL
                    THEN 'active'
                    ELSE 'inactive'
                END,

                r.archived_at,

                NULL,

                NULL,

                NULL

            FROM
                ticketing_support_realms r

            WHERE NOT EXISTS
            (
                SELECT 1

                FROM
                    ticketing_support_portals p

                WHERE p.project_id =
                        r.project_id

                  AND p.realm_id =
                        r.id

                  AND p.code =
                        'default'
            )
        ");
    }


    private function backfillRealmDefaultPortal(): void
    {
        /*
         * Preserve Realm.updated_at.
         *
         * This foundation binding is not a business edit of Realm.
         */
        $this->db->exec("
            UPDATE
                ticketing_support_realms r

            INNER JOIN
                ticketing_support_portals p

                ON p.project_id =
                    r.project_id

               AND p.realm_id =
                    r.id

               AND p.code =
                    'default'

            SET
                r.default_portal_id =
                    p.id,

                r.updated_at =
                    r.updated_at

            WHERE
                r.default_portal_id
                IS NULL
        ");
    }


    private function ensureRealmDefaultPortalConstraint(): void
    {
        if (
            $this->constraintExists(
                'ticketing_support_realms',
                'ticketing_support_realms_default_portal_fk'
            )
        ) {
            return;
        }


        /*
         * Composite FK proves that Realm.default_portal_id:
         *
         * - belongs to the same Project;
         * - belongs to this exact Realm.
         */
        $this->db->exec("
            ALTER TABLE
                ticketing_support_realms

            ADD CONSTRAINT
                ticketing_support_realms_default_portal_fk

            FOREIGN KEY
                (
                    project_id,
                    id,
                    default_portal_id
                )

            REFERENCES
                ticketing_support_portals
                (
                    project_id,
                    realm_id,
                    id
                )

            ON DELETE RESTRICT
            ON UPDATE RESTRICT
        ");
    }


    private function columnExists(
        string $table,
        string $column
    ): bool {

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.columns

                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    private function indexExists(
        string $table,
        string $index
    ): bool {

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.statistics

                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND index_name = ?
            ");

        $statement->execute([
            $table,
            $index,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    private function constraintExists(
        string $table,
        string $constraint
    ): bool {

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.table_constraints

                WHERE constraint_schema = DATABASE()
                  AND table_name = ?
                  AND constraint_name = ?
                  AND constraint_type = 'FOREIGN KEY'
            ");

        $statement->execute([
            $table,
            $constraint,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
