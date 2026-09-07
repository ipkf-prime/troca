<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * TICKETING_TICKET_SCOPE_SNAPSHOT_FOUNDATION_V1
 *
 * Versioned immutable Ticketing scope snapshots.
 *
 * Contract:
 *
 * - Master-data changes do not silently re-scope historical tickets.
 * - Explicit re-scope creates a new immutable snapshot version.
 * - Current state only points to an immutable snapshot/version.
 * - No existing ticket is backfilled by this migration.
 * - No Dynamic Access policy is activated here.
 */
final class CreateTicketingTicketScopeSnapshotFoundation
    extends Migration
{
    public function up(): void
    {
        $this->createSnapshots();
        $this->createSnapshotDimensions();
        $this->createSnapshotValues();
        $this->createCurrentStates();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         *
         * Authorization/audit snapshots must not be dropped
         * automatically.
         */
    }


    private function createSnapshots(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_ticket_scope_snapshots
            (
                id BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                ticket_id BIGINT UNSIGNED
                    NOT NULL,

                version_no INT UNSIGNED
                    NOT NULL,

                capture_reason_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'ticket_created',

                captured_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                captured_at DATETIME
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                metadata_json LONGTEXT
                    NULL,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,


                PRIMARY KEY (id),

                UNIQUE KEY
                    ticketing_ticket_scope_snapshots_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_ticket_scope_snapshots_ticket_version_unique
                    (
                        ticket_id,
                        version_no
                    ),

                UNIQUE KEY
                    ticketing_ticket_scope_snapshots_ticket_identity_unique
                    (
                        ticket_id,
                        id,
                        version_no
                    ),

                KEY
                    ticketing_ticket_scope_snapshots_capture_index
                    (
                        ticket_id,
                        capture_reason_code,
                        captured_at,
                        id
                    ),


                CONSTRAINT
                    ticketing_ticket_scope_snapshots_ticket_fk

                FOREIGN KEY (ticket_id)
                    REFERENCES
                        ticketing_tickets(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createSnapshotDimensions(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_ticket_scope_snapshot_dimensions
            (
                snapshot_id BIGINT UNSIGNED
                    NOT NULL,

                dimension_id BIGINT UNSIGNED
                    NOT NULL,

                dimension_reference_snapshot VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                dimension_code_snapshot VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                dimension_title_snapshot VARCHAR(255)
                    NOT NULL,

                cardinality_code_snapshot VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                hierarchy_mode_code_snapshot VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                source_mode_code_snapshot VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                source_key_snapshot VARCHAR(190)
                    NULL,

                supports_descendants_snapshot TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,


                PRIMARY KEY
                    (
                        snapshot_id,
                        dimension_id
                    ),

                KEY
                    ticketing_ticket_scope_snapshot_dimensions_dimension_index
                    (
                        dimension_id,
                        snapshot_id
                    ),


                CONSTRAINT
                    ticketing_ticket_scope_snapshot_dimensions_snapshot_fk

                FOREIGN KEY (snapshot_id)
                    REFERENCES
                        ticketing_ticket_scope_snapshots(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,


                CONSTRAINT
                    ticketing_ticket_scope_snapshot_dimensions_dimension_fk

                FOREIGN KEY (dimension_id)
                    REFERENCES
                        ticketing_scope_dimensions(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createSnapshotValues(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_ticket_scope_snapshot_values
            (
                snapshot_id BIGINT UNSIGNED
                    NOT NULL,

                dimension_id BIGINT UNSIGNED
                    NOT NULL,

                dimension_value_id BIGINT UNSIGNED
                    NOT NULL,

                value_reference_snapshot VARCHAR(190)
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_bin
                    NOT NULL,

                value_code_snapshot VARCHAR(120)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                value_title_snapshot VARCHAR(255)
                    NOT NULL,

                source_reference_snapshot VARCHAR(190)
                    NULL,

                is_primary TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,


                PRIMARY KEY
                    (
                        snapshot_id,
                        dimension_id,
                        dimension_value_id
                    ),

                KEY
                    ticketing_ticket_scope_snapshot_values_value_index
                    (
                        dimension_id,
                        dimension_value_id,
                        snapshot_id
                    ),

                KEY
                    ticketing_ticket_scope_snapshot_values_reference_index
                    (
                        dimension_id,
                        value_reference_snapshot,
                        snapshot_id
                    ),


                CONSTRAINT
                    ticketing_ticket_scope_snapshot_values_snapshot_dimension_fk

                FOREIGN KEY
                    (
                        snapshot_id,
                        dimension_id
                    )

                    REFERENCES
                        ticketing_ticket_scope_snapshot_dimensions
                        (
                            snapshot_id,
                            dimension_id
                        )

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,


                CONSTRAINT
                    ticketing_ticket_scope_snapshot_values_dimension_value_fk

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


    private function createCurrentStates(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_ticket_scope_states
            (
                ticket_id BIGINT UNSIGNED
                    NOT NULL,

                current_snapshot_id BIGINT UNSIGNED
                    NOT NULL,

                current_version_no INT UNSIGNED
                    NOT NULL,

                updated_by_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,


                PRIMARY KEY (ticket_id),

                KEY
                    ticketing_ticket_scope_states_snapshot_index
                    (
                        current_snapshot_id,
                        current_version_no
                    ),


                CONSTRAINT
                    ticketing_ticket_scope_states_ticket_fk

                FOREIGN KEY (ticket_id)
                    REFERENCES
                        ticketing_tickets(id)

                    ON DELETE RESTRICT
                    ON UPDATE RESTRICT,


                CONSTRAINT
                    ticketing_ticket_scope_states_snapshot_fk

                FOREIGN KEY
                    (
                        ticket_id,
                        current_snapshot_id,
                        current_version_no
                    )

                    REFERENCES
                        ticketing_ticket_scope_snapshots
                        (
                            ticket_id,
                            id,
                            version_no
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
