<?php

declare(strict_types=1);

namespace IPKF\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class SchedulerRepository
{
    public function __construct(
        private PDO $db
    ) {
    }


    public function synchronize(
        JobRegistry $registry
    ): void {
        foreach ($registry->all() as $job) {

            $this->upsertDefinition(
                $job
            );

            $definitionId =
                $this->definitionId(
                    $job->key()
                );

            /*
             * Existing bindings are preserved.
             * A disappeared Scope becomes unavailable;
             * its history and operator settings remain.
             */
            $statement =
                $this->db->prepare("
                    UPDATE scheduler_job_bindings
                    SET
                        scope_available = 0,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE job_definition_id = ?
                ");

            $statement->execute([
                $definitionId,
            ]);


            foreach ($job->scopes() as $scope) {

                $scopeType =
                    trim(
                        (string) (
                            $scope['type']
                            ?? ''
                        )
                    );

                $scopeReference =
                    trim(
                        (string) (
                            $scope['reference']
                            ?? ''
                        )
                    );

                if (
                    $scopeType === ''
                    ||
                    $scopeReference === ''
                ) {
                    throw new RuntimeException(
                        'scheduler_scope_invalid:'
                        . $job->key()
                    );
                }

                $scopeTitle =
                    trim(
                        (string) (
                            $scope['title']
                            ?? $scopeReference
                        )
                    );

                $scopeContext =
                    is_array(
                        $scope['context']
                        ?? null
                    )
                        ? $scope['context']
                        : [];

                $statement =
                    $this->db->prepare("
                        INSERT INTO scheduler_job_bindings
                        (
                            job_definition_id,
                            scope_type,
                            scope_reference,
                            scope_title_snapshot,
                            scope_context_json,
                            scope_available,
                            created_at,
                            updated_at
                        )
                        VALUES
                        (
                            ?, ?, ?, ?, ?,
                            1,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )
                        ON DUPLICATE KEY UPDATE
                            scope_title_snapshot =
                                VALUES(scope_title_snapshot),

                            scope_context_json =
                                VALUES(scope_context_json),

                            scope_available = 1,

                            updated_at =
                                CURRENT_TIMESTAMP
                    ");

                $statement->execute([
                    $definitionId,
                    $scopeType,
                    $scopeReference,
                    $scopeTitle,

                    json_encode(
                        $scopeContext,
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_THROW_ON_ERROR
                    ),
                ]);

                $bindingId =
                    $this->bindingId(
                        $definitionId,
                        $scopeType,
                        $scopeReference
                    );

                $statement =
                    $this->db->prepare("
                        INSERT IGNORE INTO scheduler_schedules
                        (
                            binding_id,
                            state_code,
                            schedule_type,
                            interval_minutes,
                            timezone,
                            next_run_at,
                            timeout_seconds,
                            created_at,
                            updated_at
                        )
                        VALUES
                        (
                            ?,
                            'active',
                            'interval',
                            ?,
                            'Asia/Tehran',
                            NULL,
                            300,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )
                    ");

                $statement->execute([
                    $bindingId,

                    max(
                        1,
                        min(
                            1440,
                            $job->defaultIntervalMinutes()
                        )
                    ),
                ]);
            }
        }
    }


    public function activateUnscheduled(): void
    {
        /*
         * Newly discovered bindings become due after
         * their configured interval, not immediately.
         */
        $this->db->exec("
            UPDATE scheduler_schedules
            SET
                next_run_at =
                    DATE_ADD(
                        UTC_TIMESTAMP(),
                        INTERVAL interval_minutes MINUTE
                    ),
                updated_at =
                    CURRENT_TIMESTAMP
            WHERE state_code = 'active'
              AND schedule_type = 'interval'
              AND next_run_at IS NULL
        ");
    }


    public function bindings(
        string $applicationKey
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    b.id AS binding_id,

                    d.job_key,
                    d.application_key,
                    d.title AS job_title,
                    d.description AS job_description,
                    d.scope_model,

                    b.scope_type,
                    b.scope_reference,
                    b.scope_title_snapshot,
                    b.scope_context_json,
                    b.scope_available,

                    s.id AS schedule_id,
                    s.state_code,
                    s.schedule_type,
                    s.interval_minutes,
                    s.timezone,
                    s.next_run_at,
                    s.last_run_at,
                    s.last_status_code,
                    s.consecutive_failures,
                    s.locked_until

                FROM scheduler_job_bindings b

                INNER JOIN scheduler_job_definitions d
                    ON d.id = b.job_definition_id

                INNER JOIN scheduler_schedules s
                    ON s.binding_id = b.id

                WHERE d.application_key = ?

                ORDER BY
                    d.title,
                    b.scope_title_snapshot,
                    b.id
            ");

        $statement->execute([
            $applicationKey,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    public function recentRuns(
        string $applicationKey,
        int $limit = 30
    ): array {
        $limit =
            max(
                1,
                min(
                    200,
                    $limit
                )
            );

        $statement =
            $this->db->prepare("
                SELECT
                    r.*,

                    d.job_key,
                    d.title AS job_title,

                    b.scope_type,
                    b.scope_reference,
                    b.scope_title_snapshot

                FROM scheduler_job_runs r

                INNER JOIN scheduler_job_bindings b
                    ON b.id = r.binding_id

                INNER JOIN scheduler_job_definitions d
                    ON d.id = b.job_definition_id

                WHERE d.application_key = ?

                ORDER BY r.id DESC

                LIMIT {$limit}
            ");

        $statement->execute([
            $applicationKey,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    public function binding(
        int $bindingId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    b.id AS binding_id,

                    d.job_key,
                    d.application_key,

                    b.scope_type,
                    b.scope_reference,
                    b.scope_title_snapshot,
                    b.scope_context_json,
                    b.scope_available,

                    s.id AS schedule_id,
                    s.state_code,
                    s.schedule_type,
                    s.interval_minutes,
                    s.timeout_seconds,
                    s.next_run_at

                FROM scheduler_job_bindings b

                INNER JOIN scheduler_job_definitions d
                    ON d.id = b.job_definition_id

                INNER JOIN scheduler_schedules s
                    ON s.binding_id = b.id

                WHERE b.id = ?

                LIMIT 1
            ");

        $statement->execute([
            $bindingId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }


    public function updateSchedule(
        int $bindingId,
        string $stateCode,
        string $scheduleType,
        int $intervalMinutes,
        ?string $actorReference
    ): bool {
        if (
            !in_array(
                $stateCode,
                [
                    'active',
                    'paused',
                    'disabled',
                ],
                true
            )
        ) {
            return false;
        }

        if (
            !in_array(
                $scheduleType,
                [
                    'interval',
                    'manual',
                ],
                true
            )
        ) {
            return false;
        }

        if ($this->binding($bindingId) === null) {
            return false;
        }

        $intervalMinutes =
            max(
                1,
                min(
                    1440,
                    $intervalMinutes
                )
            );

        $nextRunAt = null;

        if (
            $stateCode === 'active'
            &&
            $scheduleType === 'interval'
        ) {
            $nextRunAt =
                (
                    new DateTimeImmutable(
                        'now',
                        new DateTimeZone('UTC')
                    )
                )
                    ->modify(
                        '+'
                        . $intervalMinutes
                        . ' minutes'
                    )
                    ->format(
                        'Y-m-d H:i:s'
                    );
        }

        $statement =
            $this->db->prepare("
                UPDATE scheduler_schedules
                SET
                    state_code = ?,
                    schedule_type = ?,
                    interval_minutes = ?,
                    next_run_at = ?,
                    locked_until = NULL,
                    lock_token = NULL,
                    updated_by_user_reference = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE binding_id = ?
            ");

        $statement->execute([
            $stateCode,
            $scheduleType,
            $intervalMinutes,
            $nextRunAt,
            $actorReference,
            $bindingId,
        ]);

        return true;
    }


    public function due(
        string $applicationKey,
        int $limit
    ): array {
        $limit =
            max(
                1,
                min(
                    100,
                    $limit
                )
            );

        $statement =
            $this->db->prepare("
                SELECT
                    b.id AS binding_id,

                    d.job_key,
                    d.application_key,

                    b.scope_type,
                    b.scope_reference,
                    b.scope_title_snapshot,
                    b.scope_context_json,

                    s.id AS schedule_id,
                    s.state_code,
                    s.schedule_type,
                    s.interval_minutes,
                    s.timeout_seconds,
                    s.next_run_at

                FROM scheduler_job_bindings b

                INNER JOIN scheduler_job_definitions d
                    ON d.id = b.job_definition_id

                INNER JOIN scheduler_schedules s
                    ON s.binding_id = b.id

                WHERE d.application_key = ?

                  AND d.is_active = 1
                  AND b.scope_available = 1

                  AND s.state_code = 'active'
                  AND s.schedule_type = 'interval'

                  AND s.next_run_at IS NOT NULL
                  AND s.next_run_at <= UTC_TIMESTAMP()

                  AND
                  (
                      s.locked_until IS NULL
                      OR s.locked_until < UTC_TIMESTAMP()
                  )

                ORDER BY
                    s.next_run_at,
                    s.id

                LIMIT {$limit}
            ");

        $statement->execute([
            $applicationKey,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    public function claim(
        array $binding,
        string $triggerCode,
        string $token
    ): bool {
        $timeoutSeconds =
            max(
                30,
                min(
                    3600,
                    (int) (
                        $binding['timeout_seconds']
                        ?? 300
                    )
                )
            );

        $lockedUntil =
            (
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone('UTC')
                )
            )
                ->modify(
                    '+'
                    . $timeoutSeconds
                    . ' seconds'
                )
                ->format(
                    'Y-m-d H:i:s'
                );

        $condition =
            $triggerCode === 'manual'
                ? "
                    AND state_code <> 'disabled'
                "
                : "
                    AND state_code = 'active'
                    AND schedule_type = 'interval'
                    AND next_run_at IS NOT NULL
                    AND next_run_at <= UTC_TIMESTAMP()
                ";

        $statement =
            $this->db->prepare("
                UPDATE scheduler_schedules

                SET
                    lock_token = ?,
                    locked_until = ?,
                    updated_at = CURRENT_TIMESTAMP

                WHERE binding_id = ?

                  AND
                  (
                      locked_until IS NULL
                      OR locked_until < UTC_TIMESTAMP()
                  )

                  {$condition}
            ");

        $statement->execute([
            $token,
            $lockedUntil,
            (int) $binding['binding_id'],
        ]);

        return
            $statement->rowCount()
            === 1;
    }


    public function startRun(
        array $binding,
        string $triggerCode,
        ?string $actorReference,
        string $workerReference
    ): int {
        $statement =
            $this->db->prepare("
                INSERT INTO scheduler_job_runs
                (
                    public_reference,
                    binding_id,
                    schedule_id,
                    trigger_code,
                    status_code,
                    started_at,
                    triggered_by_user_reference,
                    worker_reference,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'running',
                    UTC_TIMESTAMP(),
                    ?,
                    ?,
                    CURRENT_TIMESTAMP
                )
            ");

        $statement->execute([
            'SJR-'
            . strtoupper(
                bin2hex(
                    random_bytes(8)
                )
            ),

            (int) $binding['binding_id'],
            (int) $binding['schedule_id'],
            $triggerCode,
            $actorReference,
            $workerReference,
        ]);

        return
            (int) $this->db
                ->lastInsertId();
    }


    public function finishSuccess(
        int $runId,
        int $bindingId,
        string $token,
        array $summary,
        int $durationMs
    ): void {
        $statement =
            $this->db->prepare("
                UPDATE scheduler_job_runs
                SET
                    status_code = 'success',
                    finished_at = UTC_TIMESTAMP(),
                    duration_ms = ?,
                    summary_json = ?,
                    error_message = NULL
                WHERE id = ?
            ");

        $statement->execute([
            max(
                0,
                $durationMs
            ),

            json_encode(
                $summary,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),

            $runId,
        ]);

        $this->finishSchedule(
            $bindingId,
            $token,
            'success'
        );
    }


    public function finishFailure(
        int $runId,
        int $bindingId,
        string $token,
        string $error,
        int $durationMs
    ): void {
        $statement =
            $this->db->prepare("
                UPDATE scheduler_job_runs
                SET
                    status_code = 'failed',
                    finished_at = UTC_TIMESTAMP(),
                    duration_ms = ?,
                    error_message = ?
                WHERE id = ?
            ");

        $statement->execute([
            max(
                0,
                $durationMs
            ),

            $this->limit(
                $error,
                4000
            ),

            $runId,
        ]);

        $this->finishSchedule(
            $bindingId,
            $token,
            'failed'
        );
    }


    private function finishSchedule(
        int $bindingId,
        string $token,
        string $statusCode
    ): void {
        $failureExpression =
            $statusCode === 'success'
                ? '0'
                : 'consecutive_failures + 1';

        $statement =
            $this->db->prepare("
                UPDATE scheduler_schedules
                SET
                    last_run_at =
                        UTC_TIMESTAMP(),

                    last_status_code = ?,

                    consecutive_failures =
                        {$failureExpression},

                    next_run_at =
                        CASE
                            WHEN state_code = 'active'
                             AND schedule_type = 'interval'
                            THEN DATE_ADD(
                                UTC_TIMESTAMP(),
                                INTERVAL interval_minutes MINUTE
                            )
                            ELSE NULL
                        END,

                    locked_until = NULL,
                    lock_token = NULL,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE binding_id = ?
                  AND lock_token = ?
            ");

        $statement->execute([
            $statusCode,
            $bindingId,
            $token,
        ]);
    }


    private function upsertDefinition(
        SchedulerJobInterface $job
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO scheduler_job_definitions
                (
                    job_key,
                    application_key,
                    title,
                    description,
                    scope_model,
                    default_interval_minutes,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    application_key =
                        VALUES(application_key),

                    title =
                        VALUES(title),

                    description =
                        VALUES(description),

                    scope_model =
                        VALUES(scope_model),

                    default_interval_minutes =
                        VALUES(default_interval_minutes),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            $job->key(),
            $job->applicationKey(),
            $job->title(),
            $job->description(),
            $job->scopeModel(),

            max(
                1,
                min(
                    1440,
                    $job->defaultIntervalMinutes()
                )
            ),
        ]);
    }


    private function definitionId(
        string $jobKey
    ): int {
        $statement =
            $this->db->prepare("
                SELECT id
                FROM scheduler_job_definitions
                WHERE job_key = ?
                LIMIT 1
            ");

        $statement->execute([
            $jobKey,
        ]);

        $id =
            (int) $statement
                ->fetchColumn();

        if ($id < 1) {
            throw new RuntimeException(
                'scheduler_definition_missing:'
                . $jobKey
            );
        }

        return $id;
    }


    private function bindingId(
        int $definitionId,
        string $scopeType,
        string $scopeReference
    ): int {
        $statement =
            $this->db->prepare("
                SELECT id
                FROM scheduler_job_bindings
                WHERE job_definition_id = ?
                  AND scope_type = ?
                  AND scope_reference = ?
                LIMIT 1
            ");

        $statement->execute([
            $definitionId,
            $scopeType,
            $scopeReference,
        ]);

        $id =
            (int) $statement
                ->fetchColumn();

        if ($id < 1) {
            throw new RuntimeException(
                'scheduler_binding_missing'
            );
        }

        return $id;
    }


    private function limit(
        string $value,
        int $length
    ): string {
        if (
            function_exists(
                'mb_substr'
            )
        ) {
            return
                mb_substr(
                    $value,
                    0,
                    $length,
                    'UTF-8'
                );
        }

        return
            substr(
                $value,
                0,
                $length
            );
    }
}
