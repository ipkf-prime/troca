<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;

final class CreateTicketingParticipantDirectoryFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createParticipants();
        $this->createImportBatches();
        $this->createImportRows();

        $this->extendMemberships();
        $this->extendTickets();

        $this->backfillParticipants();
        $this->backfillMemberships();
        $this->backfillTickets();

        $this->ensureMembershipIndexes();
        $this->ensureTicketIndexes();

        $this->ensureConstraints();

        $this->makeLegacyMembershipReferenceNullable();
    }


    public function down(): void
    {
        /*
         * Deliberately non-destructive.
         *
         * Participant identities become historical
         * references for memberships and tickets.
         */
    }


    private function createParticipants(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_participants
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                origin_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                core_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                core_person_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                full_name VARCHAR(255)
                    NOT NULL,

                email VARCHAR(255)
                    NULL,

                email_normalized VARCHAR(255)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                mobile VARCHAR(50)
                    NULL,

                mobile_normalized VARCHAR(50)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                organization_name VARCHAR(255)
                    NULL,

                external_reference VARCHAR(190)
                    NULL,

                account_state VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'contact',

                imported_batch_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                linked_at DATETIME NULL,

                disabled_at DATETIME NULL,

                archived_at DATETIME NULL,

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

                UNIQUE KEY
                    ticketing_participants_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_participants_core_user_unique
                    (core_user_reference),

                INDEX
                    ticketing_participants_state_name_index
                    (
                        account_state,
                        archived_at,
                        full_name
                    ),

                INDEX
                    ticketing_participants_email_index
                    (email_normalized),

                INDEX
                    ticketing_participants_mobile_index
                    (mobile_normalized),

                INDEX
                    ticketing_participants_external_index
                    (
                        origin_code,
                        external_reference
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createImportBatches(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_participant_import_batches
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                file_name VARCHAR(255)
                    NOT NULL,

                file_sha256 CHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'pending',

                total_rows INT UNSIGNED
                    NOT NULL DEFAULT 0,

                valid_rows INT UNSIGNED
                    NOT NULL DEFAULT 0,

                imported_rows INT UNSIGNED
                    NOT NULL DEFAULT 0,

                duplicate_rows INT UNSIGNED
                    NOT NULL DEFAULT 0,

                failed_rows INT UNSIGNED
                    NOT NULL DEFAULT 0,

                imported_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                started_at DATETIME NULL,

                completed_at DATETIME NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_participant_import_batches_reference_unique
                    (public_reference),

                INDEX
                    ticketing_participant_import_batches_status_index
                    (
                        status_code,
                        created_at
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createImportRows(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
            ticketing_participant_import_rows
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                batch_id BIGINT UNSIGNED
                    NOT NULL,

                source_row_number INT UNSIGNED
                    NOT NULL,

                raw_payload_json LONGTEXT
                    NOT NULL,

                normalized_payload_json LONGTEXT
                    NULL,

                result_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'pending',

                error_json LONGTEXT NULL,

                participant_id BIGINT UNSIGNED
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_participant_import_rows_batch_row_unique
                    (
                        batch_id,
                        source_row_number
                    ),

                INDEX
                    ticketing_participant_import_rows_result_index
                    (
                        batch_id,
                        result_code
                    ),

                INDEX
                    ticketing_participant_import_rows_participant_index
                    (participant_id),

                CONSTRAINT
                    ticketing_participant_import_rows_batch_fk
                    FOREIGN KEY (batch_id)
                    REFERENCES
                        ticketing_participant_import_batches (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,

                CONSTRAINT
                    ticketing_participant_import_rows_participant_fk
                    FOREIGN KEY (participant_id)
                    REFERENCES ticketing_participants (id)
                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function extendMemberships(): void
    {
        if (
            !$this->columnExists(
                'ticketing_support_project_members',
                'participant_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_members
                ADD COLUMN participant_id
                    BIGINT UNSIGNED NULL
                    AFTER project_id
            ");
        }
    }


    private function extendTickets(): void
    {
        if (
            !$this->columnExists(
                'ticketing_tickets',
                'requester_participant_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD COLUMN requester_participant_id
                    BIGINT UNSIGNED NULL
                    AFTER support_service_id
            ");
        }
    }


    private function backfillParticipants(): void
    {
        $statement =
            $this->db->query("
                SELECT
                    identity_reference,

                    MAX(
                        NULLIF(
                            person_reference,
                            ''
                        )
                    ) AS person_reference,

                    MAX(
                        NULLIF(
                            display_name,
                            ''
                        )
                    ) AS display_name,

                    MAX(
                        NULLIF(
                            email,
                            ''
                        )
                    ) AS email,

                    MAX(
                        NULLIF(
                            mobile,
                            ''
                        )
                    ) AS mobile,

                    MAX(
                        NULLIF(
                            organization_name,
                            ''
                        )
                    ) AS organization_name

                FROM
                (
                    SELECT
                        user_reference
                            AS identity_reference,

                        person_reference,

                        display_name_snapshot
                            AS display_name,

                        NULL AS email,
                        NULL AS mobile,
                        NULL AS organization_name

                    FROM
                        ticketing_support_project_members

                    WHERE user_reference IS NOT NULL
                      AND user_reference <> ''

                    UNION ALL

                    SELECT
                        requester_user_reference
                            AS identity_reference,

                        requester_person_reference
                            AS person_reference,

                        requester_display_name_snapshot
                            AS display_name,

                        requester_email_snapshot
                            AS email,

                        requester_mobile_snapshot
                            AS mobile,

                        requester_organization_snapshot
                            AS organization_name

                    FROM ticketing_tickets

                    WHERE requester_user_reference
                        IS NOT NULL

                      AND requester_user_reference <> ''
                ) identity_sources

                GROUP BY identity_reference

                ORDER BY identity_reference
            ");

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $exists =
            $this->db->prepare("
                SELECT id
                FROM ticketing_participants
                WHERE core_user_reference = ?
                LIMIT 1
            ");

        $insert =
            $this->db->prepare("
                INSERT INTO ticketing_participants
                (
                    public_reference,
                    origin_code,
                    core_user_reference,
                    core_person_reference,
                    full_name,
                    email,
                    email_normalized,
                    mobile,
                    mobile_normalized,
                    organization_name,
                    external_reference,
                    account_state,
                    imported_batch_reference,
                    linked_at,
                    disabled_at,
                    archived_at,
                    created_by_user_reference,
                    updated_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'core',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NULL,
                    'linked',
                    NULL,
                    UTC_TIMESTAMP(),
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        foreach ($rows as $row) {

            $identity =
                trim(
                    (string) $row[
                        'identity_reference'
                    ]
                );

            if ($identity === '') {
                continue;
            }

            $exists->execute([
                $identity,
            ]);

            if ($exists->fetchColumn()) {
                continue;
            }

            $fullName =
                trim(
                    (string) (
                        $row['display_name']
                        ?? ''
                    )
                );

            if ($fullName === '') {
                $fullName = $identity;
            }

            $email =
                trim(
                    (string) (
                        $row['email']
                        ?? ''
                    )
                );

            $mobile =
                trim(
                    (string) (
                        $row['mobile']
                        ?? ''
                    )
                );

            $insert->execute([
                $this->participantReference(),

                $identity,

                $this->nullable(
                    $row['person_reference']
                    ?? null
                ),

                $fullName,

                $email !== ''
                    ? $email
                    : null,

                $email !== ''
                    ? strtolower($email)
                    : null,

                $mobile !== ''
                    ? $mobile
                    : null,

                $this->normalizeMobile(
                    $mobile
                ),

                $this->nullable(
                    $row['organization_name']
                    ?? null
                ),
            ]);
        }
    }


    private function backfillMemberships(): void
    {
        $this->db->exec("
            UPDATE
                ticketing_support_project_members m

            INNER JOIN
                ticketing_participants p
                ON p.core_user_reference =
                    m.user_reference

            SET
                m.participant_id = p.id

            WHERE m.participant_id IS NULL
              AND m.user_reference IS NOT NULL
              AND m.user_reference <> ''
        ");
    }


    private function backfillTickets(): void
    {
        $this->db->exec("
            UPDATE
                ticketing_tickets t

            INNER JOIN
                ticketing_participants p
                ON p.core_user_reference =
                    t.requester_user_reference

            SET
                t.requester_participant_id =
                    p.id

            WHERE t.requester_participant_id
                    IS NULL

              AND t.requester_user_reference
                    IS NOT NULL

              AND t.requester_user_reference
                    <> ''
        ");
    }


    private function ensureMembershipIndexes(): void
    {
        if (
            !$this->indexExists(
                'ticketing_support_project_members',
                'ticketing_support_project_members_project_participant_unique'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_members

                ADD UNIQUE KEY
                    ticketing_support_project_members_project_participant_unique
                    (
                        project_id,
                        participant_id
                    )
            ");
        }

        if (
            !$this->indexExists(
                'ticketing_support_project_members',
                'ticketing_support_project_members_participant_active_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_members

                ADD INDEX
                    ticketing_support_project_members_participant_active_index
                    (
                        participant_id,
                        left_at,
                        project_id
                    )
            ");
        }
    }


    private function ensureTicketIndexes(): void
    {
        if (
            !$this->indexExists(
                'ticketing_tickets',
                'ticketing_tickets_requester_participant_status_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets

                ADD INDEX
                    ticketing_tickets_requester_participant_status_index
                    (
                        requester_participant_id,
                        status_code
                    )
            ");
        }
    }


    private function ensureConstraints(): void
    {
        if (
            !$this->constraintExists(
                'ticketing_support_project_members',
                'ticketing_support_project_members_participant_fk'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_members

                ADD CONSTRAINT
                    ticketing_support_project_members_participant_fk

                FOREIGN KEY (participant_id)
                REFERENCES ticketing_participants (id)

                ON DELETE RESTRICT
                ON UPDATE RESTRICT
            ");
        }

        if (
            !$this->constraintExists(
                'ticketing_tickets',
                'ticketing_tickets_requester_participant_fk'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets

                ADD CONSTRAINT
                    ticketing_tickets_requester_participant_fk

                FOREIGN KEY
                    (requester_participant_id)

                REFERENCES
                    ticketing_participants (id)

                ON DELETE RESTRICT
                ON UPDATE RESTRICT
            ");
        }
    }


    private function makeLegacyMembershipReferenceNullable(): void
    {
        if (
            !$this->columnNullable(
                'ticketing_support_project_members',
                'user_reference'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_support_project_members

                MODIFY COLUMN user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL
            ");
        }
    }


    private function participantReference(): string
    {
        return
            'TPR-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );
    }


    private function nullable(
        mixed $value
    ): ?string {
        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        return
            $value !== ''
                ? $value
                : null;
    }


    private function normalizeMobile(
        string $mobile
    ): ?string {
        $mobile =
            trim($mobile);

        if ($mobile === '') {
            return null;
        }

        $normalized =
            preg_replace(
                '/[^0-9+]/',
                '',
                $mobile
            );

        if (
            !is_string($normalized)
            || $normalized === ''
        ) {
            return null;
        }

        return $normalized;
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


    private function columnNullable(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT is_nullable
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
                LIMIT 1
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            strtoupper(
                (string) $statement->fetchColumn()
            ) === 'YES';
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
