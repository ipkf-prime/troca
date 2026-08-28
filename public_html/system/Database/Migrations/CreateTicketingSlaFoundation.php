<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use RuntimeException;

final class CreateTicketingSlaFoundation extends Migration
{
    public function up(): void
    {
        $this->assertDependencies();

        $this->createCalendars();
        $this->createCalendarHours();
        $this->createCalendarExceptions();

        $this->createPolicies();
        $this->createTicketStates();
        $this->createSlaEvents();

        $calendarId =
            $this->seedDefaultCalendar();

        $this->seedDefaultHours(
            $calendarId
        );

        $this->seedDefaultPolicies(
            $calendarId
        );
    }


    public function down(): void
    {
    }


    private function assertDependencies(): void
    {
        foreach ([
            'ticketing_tickets',
            'ticketing_priorities',
            'ticketing_statuses',
            'ticketing_support_projects',
            'ticketing_support_queues',
        ] as $table) {

            if (!$this->tableExists($table)) {
                throw new RuntimeException(
                    'Ticketing SLA dependency missing: '
                    . $table
                );
            }
        }
    }


    private function createCalendars(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_business_calendars
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT PRIMARY KEY,

                public_reference
                    VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                project_id
                    BIGINT UNSIGNED NULL,

                code
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                title
                    VARCHAR(255)
                    NOT NULL,

                timezone
                    VARCHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'Asia/Tehran',

                is_default
                    TINYINT(1)
                    NOT NULL
                    DEFAULT 0,

                status
                    VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                effective_from_at
                    DATETIME NOT NULL,

                effective_to_at
                    DATETIME NULL,

                metadata_json
                    LONGTEXT NULL,

                created_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_business_calendars_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_business_calendars_code_unique
                    (code),

                INDEX
                    ticketing_business_calendars_project_index
                    (
                        project_id,
                        status,
                        is_default,
                        id
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createCalendarHours(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_business_calendar_hours
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT PRIMARY KEY,

                calendar_id
                    BIGINT UNSIGNED NOT NULL,

                weekday_iso
                    TINYINT UNSIGNED NOT NULL,

                segment_order
                    SMALLINT UNSIGNED
                    NOT NULL DEFAULT 1,

                start_time
                    TIME NOT NULL,

                end_time
                    TIME NOT NULL,

                is_working
                    TINYINT(1)
                    NOT NULL DEFAULT 1,

                created_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_calendar_hours_segment_unique
                    (
                        calendar_id,
                        weekday_iso,
                        segment_order
                    ),

                INDEX
                    ticketing_calendar_hours_lookup_index
                    (
                        calendar_id,
                        weekday_iso,
                        is_working,
                        segment_order
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createCalendarExceptions(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_business_calendar_exceptions
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT PRIMARY KEY,

                calendar_id
                    BIGINT UNSIGNED NOT NULL,

                exception_date
                    DATE NOT NULL,

                exception_type_code
                    VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                segment_order
                    SMALLINT UNSIGNED
                    NOT NULL DEFAULT 0,

                title
                    VARCHAR(255) NULL,

                start_time
                    TIME NULL,

                end_time
                    TIME NULL,

                metadata_json
                    LONGTEXT NULL,

                created_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_calendar_exception_unique
                    (
                        calendar_id,
                        exception_date,
                        segment_order
                    ),

                INDEX
                    ticketing_calendar_exception_lookup_index
                    (
                        calendar_id,
                        exception_date,
                        exception_type_code
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createPolicies(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_sla_policies
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT PRIMARY KEY,

                public_reference
                    VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                scope_key
                    VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                project_id
                    BIGINT UNSIGNED NULL,

                queue_id
                    BIGINT UNSIGNED NULL,

                priority_code
                    VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                calendar_id
                    BIGINT UNSIGNED NOT NULL,

                title
                    VARCHAR(255)
                    NOT NULL,

                response_minutes
                    INT UNSIGNED NOT NULL,

                resolution_minutes
                    INT UNSIGNED NOT NULL,

                pause_statuses_json
                    LONGTEXT NULL,

                breach_action_code
                    VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'escalate',

                max_auto_escalations
                    SMALLINT UNSIGNED
                    NOT NULL DEFAULT 3,

                escalation_repeat_minutes
                    INT UNSIGNED
                    NOT NULL DEFAULT 60,

                effective_from_at
                    DATETIME NOT NULL,

                effective_to_at
                    DATETIME NULL,

                status
                    VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active',

                sort_order
                    INT NOT NULL DEFAULT 0,

                metadata_json
                    LONGTEXT NULL,

                created_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_sla_policy_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_sla_policy_scope_unique
                    (scope_key),

                INDEX
                    ticketing_sla_policy_resolution_index
                    (
                        priority_code,
                        project_id,
                        queue_id,
                        status,
                        effective_from_at,
                        id
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createTicketStates(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_ticket_sla_states
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT PRIMARY KEY,

                public_reference
                    VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                ticket_id
                    BIGINT UNSIGNED NOT NULL,

                policy_id
                    BIGINT UNSIGNED NOT NULL,

                calendar_id
                    BIGINT UNSIGNED NOT NULL,

                policy_scope_key_snapshot
                    VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                priority_code_snapshot
                    VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                response_target_minutes
                    INT UNSIGNED NOT NULL,

                resolution_target_minutes
                    INT UNSIGNED NOT NULL,

                response_started_at
                    DATETIME NOT NULL,

                response_due_at
                    DATETIME NOT NULL,

                response_met_at
                    DATETIME NULL,

                response_breached_at
                    DATETIME NULL,

                resolution_started_at
                    DATETIME NOT NULL,

                resolution_due_at
                    DATETIME NOT NULL,

                resolution_met_at
                    DATETIME NULL,

                resolution_breached_at
                    DATETIME NULL,

                pause_status_code
                    VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                paused_at
                    DATETIME NULL,

                accumulated_pause_business_minutes
                    INT UNSIGNED
                    NOT NULL DEFAULT 0,

                auto_escalation_count
                    SMALLINT UNSIGNED
                    NOT NULL DEFAULT 0,

                last_auto_escalated_at
                    DATETIME NULL,

                last_escalation_node_id
                    BIGINT UNSIGNED NULL,

                next_action_at
                    DATETIME NULL,

                state_code
                    VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'active',

                initialized_at
                    DATETIME NOT NULL,

                last_calculated_at
                    DATETIME NOT NULL,

                created_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_ticket_sla_state_reference_unique
                    (public_reference),

                UNIQUE KEY
                    ticketing_ticket_sla_state_ticket_unique
                    (ticket_id),

                INDEX
                    ticketing_ticket_sla_state_worker_index
                    (
                        state_code,
                        next_action_at,
                        id
                    ),

                INDEX
                    ticketing_ticket_sla_response_index
                    (
                        response_due_at,
                        response_met_at,
                        response_breached_at
                    ),

                INDEX
                    ticketing_ticket_sla_resolution_index
                    (
                        resolution_due_at,
                        resolution_met_at,
                        resolution_breached_at
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function createSlaEvents(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                ticketing_sla_events
            (
                id BIGINT UNSIGNED
                    AUTO_INCREMENT PRIMARY KEY,

                public_reference
                    VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                ticket_id
                    BIGINT UNSIGNED NOT NULL,

                sla_state_id
                    BIGINT UNSIGNED NULL,

                event_code
                    VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                actor_user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                actor_display_name_snapshot
                    VARCHAR(255) NULL,

                payload_json
                    LONGTEXT NULL,

                occurred_at
                    DATETIME NOT NULL,

                created_at
                    TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY
                    ticketing_sla_event_reference_unique
                    (public_reference),

                INDEX
                    ticketing_sla_event_ticket_index
                    (
                        ticket_id,
                        occurred_at,
                        id
                    ),

                INDEX
                    ticketing_sla_event_code_index
                    (
                        event_code,
                        occurred_at,
                        id
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function seedDefaultCalendar(): int
    {
        $code =
            'default-business-hours';

        $statement =
            $this->db->prepare("
                INSERT INTO
                    ticketing_business_calendars
                (
                    public_reference,
                    project_id,
                    code,
                    title,
                    timezone,
                    is_default,
                    status,
                    effective_from_at,
                    metadata_json
                )
                VALUES
                (
                    ?,
                    NULL,
                    ?,
                    'تقویم کاری پیش‌فرض',
                    'Asia/Tehran',
                    1,
                    'active',
                    UTC_TIMESTAMP(),
                    ?
                )

                ON DUPLICATE KEY UPDATE
                    title =
                        VALUES(title),

                    timezone =
                        VALUES(timezone),

                    is_default = 1,

                    status = 'active',

                    metadata_json =
                        VALUES(metadata_json),

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            $this->reference(
                'TBC',
                $code
            ),

            $code,

            json_encode(
                [
                    'template' =>
                        'iran-office-default-v1',

                    'editable' =>
                        true,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);


        $lookup =
            $this->db->prepare("
                SELECT id
                FROM ticketing_business_calendars
                WHERE code = ?
                LIMIT 1
            ");

        $lookup->execute([
            $code,
        ]);


        $id =
            (int) $lookup->fetchColumn();


        if ($id <= 0) {
            throw new RuntimeException(
                'Default Ticketing calendar missing.'
            );
        }


        return $id;
    }


    private function seedDefaultHours(
        int $calendarId
    ): void {
        /*
         * ISO weekday:
         * 1 Mon
         * 2 Tue
         * 3 Wed
         * 4 Thu
         * 5 Fri
         * 6 Sat
         * 7 Sun
         *
         * This is an editable initial template,
         * not a hardcoded business rule.
         */
        $segments = [
            [6, '08:00:00', '16:00:00'],
            [7, '08:00:00', '16:00:00'],
            [1, '08:00:00', '16:00:00'],
            [2, '08:00:00', '16:00:00'],
            [3, '08:00:00', '16:00:00'],
            [4, '08:00:00', '13:00:00'],
        ];


        $statement =
            $this->db->prepare("
                INSERT INTO
                    ticketing_business_calendar_hours
                (
                    calendar_id,
                    weekday_iso,
                    segment_order,
                    start_time,
                    end_time,
                    is_working
                )
                VALUES
                (
                    ?,
                    ?,
                    1,
                    ?,
                    ?,
                    1
                )

                ON DUPLICATE KEY UPDATE
                    start_time =
                        VALUES(start_time),

                    end_time =
                        VALUES(end_time),

                    is_working = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");


        foreach ($segments as $segment) {

            $statement->execute([
                $calendarId,
                $segment[0],
                $segment[1],
                $segment[2],
            ]);
        }
    }


    private function seedDefaultPolicies(
        int $calendarId
    ): void {
        /*
         * Initial defaults.
         *
         * These are configuration seed values and are
         * intentionally designed to be overridden later
         * at Project or Queue scope.
         */
        $policies = [
            [
                'low',
                'SLA اولویت کم',
                480,
                2880,
                10,
            ],

            [
                'normal',
                'SLA اولویت عادی',
                240,
                1440,
                20,
            ],

            [
                'high',
                'SLA اولویت زیاد',
                120,
                480,
                30,
            ],

            [
                'urgent',
                'SLA اولویت فوری',
                30,
                240,
                40,
            ],
        ];


        $statement =
            $this->db->prepare("
                INSERT INTO
                    ticketing_sla_policies
                (
                    public_reference,
                    scope_key,

                    project_id,
                    queue_id,

                    priority_code,
                    calendar_id,

                    title,

                    response_minutes,
                    resolution_minutes,

                    pause_statuses_json,

                    breach_action_code,

                    max_auto_escalations,
                    escalation_repeat_minutes,

                    effective_from_at,

                    status,
                    sort_order,

                    metadata_json
                )
                VALUES
                (
                    ?,
                    ?,

                    NULL,
                    NULL,

                    ?,
                    ?,

                    ?,

                    ?,
                    ?,

                    ?,

                    'escalate',

                    3,
                    60,

                    UTC_TIMESTAMP(),

                    'active',
                    ?,

                    ?
                )

                ON DUPLICATE KEY UPDATE
                    calendar_id =
                        VALUES(calendar_id),

                    title =
                        VALUES(title),

                    response_minutes =
                        VALUES(response_minutes),

                    resolution_minutes =
                        VALUES(resolution_minutes),

                    pause_statuses_json =
                        VALUES(pause_statuses_json),

                    breach_action_code =
                        VALUES(breach_action_code),

                    max_auto_escalations =
                        VALUES(max_auto_escalations),

                    escalation_repeat_minutes =
                        VALUES(
                            escalation_repeat_minutes
                        ),

                    status = 'active',

                    sort_order =
                        VALUES(sort_order),

                    metadata_json =
                        VALUES(metadata_json),

                    updated_at =
                        CURRENT_TIMESTAMP
            ");


        foreach ($policies as $policy) {

            $priorityCode =
                $policy[0];

            $scopeKey =
                'global|*|*|'
                . $priorityCode;


            $statement->execute([
                $this->reference(
                    'TSP',
                    $scopeKey
                ),

                $scopeKey,

                $priorityCode,

                $calendarId,

                $policy[1],

                $policy[2],
                $policy[3],

                json_encode(
                    [
                        'waiting_requester',
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),

                $policy[4],

                json_encode(
                    [
                        'configuration_source' =>
                            'ticketing-sla-foundation-v1',

                        'override_model' =>
                            [
                                'global',
                                'project',
                                'queue',
                            ],
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
            ]);
        }
    }


    private function reference(
        string $prefix,
        string $key
    ): string {
        return
            $prefix
            . '-'
            . strtoupper(
                substr(
                    hash(
                        'sha256',
                        'ticketing-sla-v1|'
                        . $key
                    ),
                    0,
                    20
                )
            );
    }


    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.tables

                WHERE table_schema =
                        DATABASE()

                  AND table_name = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
