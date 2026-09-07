<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;


/**
 * TICKETING_SUPPORT_REALM_FOUNDATION_V1
 *
 * Generic Support Realm catalog.
 *
 * Contract:
 *
 * - Project is the configuration/product boundary.
 * - Realm is an operational support workspace inside a Project.
 * - A Project may own multiple Realms.
 * - Project.default_realm_id is the canonical default pointer.
 * - Existing Projects receive exactly one deterministic default Realm.
 * - Operational topology is NOT made Realm-aware in this migration.
 * - Ticket runtime is NOT changed in this migration.
 * - Dynamic Access grants are NOT activated or modified here.
 */
final class CreateTicketingSupportRealmFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createRealms();

        $this->extendProjects();

        $this->backfillDefaultRealms();

        $this->backfillProjectDefaultRealm();

        $this->ensureProjectDefaultRealmConstraint();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Realm identity and Project default bindings become part
         * of operational configuration/audit history.
         */
    }


    private function createRealms(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_support_realms
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

                status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                sort_order INT UNSIGNED
                    NOT NULL
                    DEFAULT 0,

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
                    ticketing_support_realms_reference_unique
                    (
                        public_reference
                    ),

                UNIQUE KEY
                    ticketing_support_realms_project_code_unique
                    (
                        project_id,
                        code
                    ),

                UNIQUE KEY
                    ticketing_support_realms_project_identity_unique
                    (
                        project_id,
                        id
                    ),

                KEY
                    ticketing_support_realms_project_status_index
                    (
                        project_id,
                        status,
                        archived_at,
                        sort_order,
                        id
                    ),


                CONSTRAINT
                    ticketing_support_realms_project_fk

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


    private function extendProjects(): void
    {
        if (
            !$this->columnExists(
                'ticketing_support_projects',
                'default_realm_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_projects

                ADD COLUMN
                    default_realm_id
                    BIGINT UNSIGNED
                    NULL
                    AFTER archived_at
            ");
        }


        if (
            !$this->indexExists(
                'ticketing_support_projects',
                'ticketing_support_projects_default_realm_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_projects

                ADD INDEX
                    ticketing_support_projects_default_realm_index
                    (
                        default_realm_id
                    )
            ");
        }


        if (
            !$this->indexExists(
                'ticketing_support_projects',
                'ticketing_support_projects_default_realm_identity_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_projects

                ADD INDEX
                    ticketing_support_projects_default_realm_identity_index
                    (
                        id,
                        default_realm_id
                    )
            ");
        }
    }


    private function backfillDefaultRealms(): void
    {
        $this->db->exec("
            INSERT INTO
                ticketing_support_realms
                (
                    public_reference,
                    project_id,
                    code,
                    title,
                    description,
                    status,
                    sort_order,
                    archived_at,
                    metadata_json,
                    created_by_user_reference,
                    updated_by_user_reference
                )

            SELECT
                CONCAT(
                    'TSR-',
                    UPPER(
                        SUBSTRING(
                            SHA2(
                                CONCAT(
                                    'ticketing-default-realm:',
                                    p.public_reference
                                ),
                                256
                            ),
                            1,
                            20
                        )
                    )
                ),

                p.id,

                'default',

                p.title,

                NULL,

                CASE
                    WHEN
                        p.is_active = 1
                        AND p.archived_at IS NULL
                    THEN 'active'
                    ELSE 'inactive'
                END,

                0,

                p.archived_at,

                NULL,

                NULL,

                NULL

            FROM
                ticketing_support_projects p

            WHERE NOT EXISTS
            (
                SELECT 1

                FROM
                    ticketing_support_realms r

                WHERE r.project_id =
                        p.id

                  AND r.code =
                        'default'
            )
        ");
    }


    private function backfillProjectDefaultRealm(): void
    {
        /*
         * Explicitly preserve updated_at.
         *
         * Adding the foundation pointer must not rewrite the
         * existing Project business/audit timestamp.
         */
        $this->db->exec("
            UPDATE
                ticketing_support_projects p

            INNER JOIN
                ticketing_support_realms r

                ON r.project_id =
                    p.id

               AND r.code =
                    'default'

            SET
                p.default_realm_id =
                    r.id,

                p.updated_at =
                    p.updated_at

            WHERE
                p.default_realm_id
                IS NULL
        ");
    }


    private function ensureProjectDefaultRealmConstraint(): void
    {
        if (
            !$this->constraintExists(
                'ticketing_support_projects',
                'ticketing_support_projects_default_realm_fk'
            )
        ) {
            /*
             * Composite FK proves that the selected default Realm
             * belongs to the same Project.
             */
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_projects

                ADD CONSTRAINT
                    ticketing_support_projects_default_realm_fk

                FOREIGN KEY
                    (
                        id,
                        default_realm_id
                    )

                REFERENCES
                    ticketing_support_realms
                    (
                        project_id,
                        id
                    )

                ON DELETE RESTRICT
                ON UPDATE RESTRICT
            ");
        }
    }


    private function columnExists(
        string $table,
        string $column
    ): bool {

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.columns

                WHERE table_schema =
                        DATABASE()

                  AND table_name = ?

                  AND column_name = ?
            ");

        $statement->execute(
            [
                $table,
                $column,
            ]
        );

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

                WHERE table_schema =
                        DATABASE()

                  AND table_name = ?

                  AND index_name = ?
            ");

        $statement->execute(
            [
                $table,
                $index,
            ]
        );

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

                WHERE constraint_schema =
                        DATABASE()

                  AND table_name = ?

                  AND constraint_name = ?
            ");

        $statement->execute(
            [
                $table,
                $constraint,
            ]
        );

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
