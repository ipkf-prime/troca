<?php

declare(strict_types=1);

$runtime =
    trim(
        (string) getenv(
            'C4_TICKETING_RUNTIME'
        )
    );

if ($runtime === '') {
    fwrite(
        STDERR,
        "C4_TICKETING_RUNTIME_REQUIRED\n"
    );
    exit(2);
}

define('BASE_PATH', $runtime);
require BASE_PATH . '/bootstrap/app.php';

restore_error_handler();
restore_exception_handler();

use App\Repositories\TicketLifecycleTransitionRepository;
use App\Repositories\WorkTicketLifecycleSyncRepository;
use App\Services\Ticketing\TicketLifecycleTransitionService;
use App\Services\Work\WorkTicketLifecycleSyncReconciliationService;
use App\Services\Work\WorkTicketLifecycleSyncRecoveryService;
use App\Services\Work\WorkTicketLifecycleSyncSchedulerService;
use IPKF\Database\Connections\ConnectionResolver;

function failA4(
    string $message
): never {
    throw new RuntimeException(
        'C4_A4_FAIL:'
        . $message
    );
}

function correlatedEventCount(
    PDO $ticketDb,
    int $ticketId,
    string $correlation
): int {
    $statement =
        $ticketDb->prepare(
            "SELECT COUNT(*)
             FROM ticketing_events
             WHERE ticket_id = ?
               AND JSON_VALID(payload_json) = 1
               AND JSON_UNQUOTE(
                    JSON_EXTRACT(
                        payload_json,
                        '$.integration_context.correlation_reference'
                    )
               ) = ?"
        );

    $statement->execute([
        $ticketId,
        $correlation,
    ]);

    return
        (int) $statement
            ->fetchColumn();
}

function attemptRow(
    PDO $workDb,
    int $attemptId
): array {
    $statement =
        $workDb->prepare(
            "SELECT *
             FROM work_ticket_lifecycle_sync_attempts
             WHERE id = ?
             LIMIT 1"
        );

    $statement->execute([
        $attemptId,
    ]);

    $row =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    if (!is_array($row)) {
        failA4(
            'attempt_not_found:'
            . $attemptId
        );
    }

    return $row;
}

function createAttempt(
    PDO $workDb,
    array $fixture,
    string $suffix,
    string $resultCode,
    int $attemptCount,
    ?string $lastAttemptedAt
): array {
    $publicReference =
        'TWLSA-FI-'
        . strtoupper(
            substr(
                hash(
                    'sha256',
                    $suffix
                    . random_bytes(8)
                ),
                0,
                20
            )
        );

    $idempotency =
        'c4-fi-idem-'
        . hash(
            'sha256',
            $suffix
            . ':'
            . random_bytes(12)
        );

    $correlation =
        'c4-fi-corr-'
        . substr(
            hash(
                'sha256',
                $suffix
                . ':'
                . random_bytes(12)
            ),
            0,
            40
        );

    $statement =
        $workDb->prepare(
            "INSERT INTO work_ticket_lifecycle_sync_attempts
            (
                public_reference,
                idempotency_key,
                correlation_reference,
                rule_id,
                work_item_id,
                work_activity_event_id,
                source_link_id,
                source_link_reference,
                ticket_reference,
                previous_work_status_code,
                resulting_work_status_code,
                ticket_action_code,
                result_code,
                attempt_count,
                actor_user_reference,
                ticket_previous_status_code,
                ticket_resulting_status_code,
                ticket_event_reference,
                error_code,
                metadata_json,
                first_attempted_at,
                last_attempted_at,
                completed_at,
                created_at,
                updated_at
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, 'resolve', ?, ?, ?,
                NULL, NULL, NULL, ?, ?, ?, ?,
                NULL,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )"
        );

    $errorCode =
        $resultCode === 'failed'
            ? 'failure_injection'
            : null;

    $firstAttemptedAt =
        $attemptCount > 0
            ? (
                $lastAttemptedAt
                ?? gmdate('Y-m-d H:i:s')
            )
            : null;

    $statement->execute([
        $publicReference,
        $idempotency,
        $correlation,
        (int) $fixture['rule_id'],
        (int) $fixture['work_item_id'],
        (int) $fixture['activity_event_id'],
        (int) $fixture['source_link_id'],
        (string) $fixture['source_link_reference'],
        (string) $fixture['ticket_reference'],
        (string) $fixture['work_status_code'],
        (string) $fixture['work_status_code'],
        $resultCode,
        $attemptCount,
        (string) $fixture['actor_user_reference'],
        $errorCode,
        json_encode(
            [
                'failure_injection' => true,
                'scenario' => $suffix,
            ],
            JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        ),
        $firstAttemptedAt,
        $lastAttemptedAt,
    ]);

    $id =
        (int) $workDb
            ->lastInsertId();

    return [
        'id' => $id,
        'public_reference' => $publicReference,
        'idempotency_key' => $idempotency,
        'correlation_reference' => $correlation,
    ];
}

$resolver =
    new ConnectionResolver();

$workDb =
    $resolver->resolve(
        'work.primary'
    );

$ticketDb =
    $resolver->resolve(
        'ticketing.primary'
    );

if (
    $workDb->inTransaction()
    ||
    $ticketDb->inTransaction()
) {
    failA4(
        'unexpected_preexisting_transaction'
    );
}

/*
 * Find one real Ticket that the native lifecycle domain itself identifies as
 * resolvable by its exact current operational owner. No customer/project
 * literal is used.
 */
$candidate =
    $ticketDb->query(
        "SELECT
            t.id AS ticket_id,
            t.public_reference AS ticket_reference,
            t.status_code,
            pm.user_reference AS actor_user_reference

         FROM ticketing_tickets t

         INNER JOIN ticketing_statuses st
            ON st.code = t.status_code

         INNER JOIN ticketing_support_project_members pm
            ON pm.id =
                t.current_assignee_project_member_id
           AND pm.left_at IS NULL
           AND pm.role_code IN (
                'member',
                'manager'
           )

         INNER JOIN ticketing_support_team_members tm
            ON tm.project_member_id = pm.id
           AND tm.team_id =
                t.current_support_team_id
           AND tm.status = 'active'
           AND tm.left_at IS NULL
           AND tm.staff_role_code IN (
                'agent',
                'supervisor',
                'manager'
           )

         WHERE st.is_closed = 0
           AND t.status_code NOT IN (
                'waiting_requester',
                'resolved',
                'closed'
           )
           AND pm.user_reference REGEXP '^user:[1-9][0-9]*$'

         ORDER BY
            t.last_activity_at DESC,
            t.id DESC

         LIMIT 1"
    )->fetch(
        PDO::FETCH_ASSOC
    );

if (!is_array($candidate)) {
    failA4(
        'no_native_resolve_candidate'
    );
}

$actorReference =
    trim(
        (string)
        $candidate[
            'actor_user_reference'
        ]
    );

if (
    !preg_match(
        '/^user:([1-9][0-9]*)$/D',
        $actorReference,
        $matches
    )
) {
    failA4(
        'candidate_actor_invalid'
    );
}

$actorUserId =
    (int) $matches[1];

$ticketRepository =
    new TicketLifecycleTransitionRepository(
        $resolver
    );

$ticketService =
    new TicketLifecycleTransitionService(
        $ticketRepository
    );

$nativeSnapshot =
    $ticketRepository
        ->reconciliationSnapshot(
            (string)
            $candidate[
                'ticket_reference'
            ],
            $actorReference,
            ''
        );

if (
    ($nativeSnapshot['found'] ?? false)
        !== true
    ||
    ($nativeSnapshot['can_resolve'] ?? false)
        !== true
) {
    failA4(
        'native_resolve_candidate_revalidation_failed'
    );
}

/*
 * Select one existing Work item with a durable activity event. The fixture
 * remains entirely inside the outer Work transaction.
 */
$workFixture =
    $workDb->query(
        "SELECT
            wi.id AS work_item_id,
            wi.project_id,
            wi.status_id,
            ws.code AS work_status_code,
            wae.id AS activity_event_id

         FROM work_items wi

         INNER JOIN work_statuses ws
            ON ws.id = wi.status_id

         INNER JOIN work_activity_events wae
            ON wae.work_item_id = wi.id

         WHERE wi.archived_at IS NULL

         ORDER BY
            wae.id DESC,
            wi.id DESC

         LIMIT 1"
    )->fetch(
        PDO::FETCH_ASSOC
    );

if (!is_array($workFixture)) {
    failA4(
        'work_fixture_not_found'
    );
}

$workPre = [
    'rules' =>
        (int) $workDb->query(
            'SELECT COUNT(*) FROM work_ticket_lifecycle_sync_rules'
        )->fetchColumn(),

    'attempts' =>
        (int) $workDb->query(
            'SELECT COUNT(*) FROM work_ticket_lifecycle_sync_attempts'
        )->fetchColumn(),

    'links' =>
        (int) $workDb->query(
            'SELECT COUNT(*) FROM work_item_source_links'
        )->fetchColumn(),
];

$ticketEventPre =
    (int) $ticketDb->query(
        'SELECT COUNT(*) FROM ticketing_events'
    )->fetchColumn();

$ticketStatusPre =
    (string) $candidate[
        'status_code'
    ];

$workDb->beginTransaction();
$ticketDb->beginTransaction();

try {
    $ruleReference =
        'TWLSR-FI-'
        . strtoupper(
            substr(
                bin2hex(
                    random_bytes(12)
                ),
                0,
                20
            )
        );

    $rule =
        $workDb->prepare(
            "INSERT INTO work_ticket_lifecycle_sync_rules
            (
                public_reference,
                work_project_id,
                work_status_id,
                relation_type_code,
                ticket_action_code,
                rule_priority,
                is_active,
                created_by_user_reference,
                updated_by_user_reference,
                created_at,
                updated_at
            )
            VALUES
            (
                ?, ?, ?, 'created_from',
                'resolve', 900001, 1,
                ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )"
        );

    $rule->execute([
        $ruleReference,
        (int) $workFixture[
            'project_id'
        ],
        (int) $workFixture[
            'status_id'
        ],
        $actorReference,
        $actorReference,
    ]);

    $ruleId =
        (int) $workDb
            ->lastInsertId();

    $sourceLinkReference =
        'WISL-FI-'
        . strtoupper(
            substr(
                bin2hex(
                    random_bytes(12)
                ),
                0,
                20
            )
        );

    $link =
        $workDb->prepare(
            "INSERT INTO work_item_source_links
            (
                public_reference,
                work_item_id,
                source_module_code,
                source_resource_type,
                source_reference,
                relation_type_code,
                metadata_json,
                created_by_user_reference,
                created_at,
                updated_at
            )
            VALUES
            (
                ?, ?, 'ticketing', 'ticket',
                ?, 'created_from', ?, ?,
                UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )"
        );

    $link->execute([
        $sourceLinkReference,
        (int) $workFixture[
            'work_item_id'
        ],
        (string) $candidate[
            'ticket_reference'
        ],
        json_encode(
            [
                'failure_injection' =>
                    true,
            ],
            JSON_THROW_ON_ERROR
        ),
        $actorReference,
    ]);

    $sourceLinkId =
        (int) $workDb
            ->lastInsertId();

    $fixture = [
        'rule_id' =>
            $ruleId,

        'work_item_id' =>
            (int) $workFixture[
                'work_item_id'
            ],

        'activity_event_id' =>
            (int) $workFixture[
                'activity_event_id'
            ],

        'source_link_id' =>
            $sourceLinkId,

        'source_link_reference' =>
            $sourceLinkReference,

        'ticket_reference' =>
            (string) $candidate[
                'ticket_reference'
            ],

        'ticket_id' =>
            (int) $candidate[
                'ticket_id'
            ],

        'work_status_code' =>
            (string) $workFixture[
                'work_status_code'
            ],

        'actor_user_reference' =>
            $actorReference,

        'actor_user_id' =>
            $actorUserId,
    ];

    $syncRepository =
        new WorkTicketLifecycleSyncRepository(
            $resolver
        );

    $reconciliation =
        new WorkTicketLifecycleSyncReconciliationService(
            $resolver,
            $syncRepository,
            $ticketService
        );

    $recovery =
        new WorkTicketLifecycleSyncRecoveryService(
            $resolver,
            $syncRepository,
            $reconciliation,
            $ticketService
        );

    $scheduler =
        new WorkTicketLifecycleSyncSchedulerService(
            $resolver,
            $syncRepository,
            $reconciliation,
            $recovery
        );

    $policy = [
        'batch_limit' => 50,
        'max_attempts' => 4,
        'base_backoff_seconds' => 300,
        'max_backoff_seconds' => 3600,
        'stale_retrying_seconds' => 900,
        'automatic_retry' => true,
        'automatic_audit_repair' => true,
    ];

    /*
     * FI-1
     * Native Ticket transition commits logically inside the outer Ticket
     * transaction, while Work attempt remains pending. Reconciliation must
     * find exact correlation and repair Work audit without another Ticket
     * transition/event.
     */
    $workDb->exec(
        'SAVEPOINT c4_a4_fi1_work'
    );

    $ticketDb->exec(
        'SAVEPOINT c4_a4_fi1_ticket'
    );

    $attempt =
        createAttempt(
            $workDb,
            $fixture,
            'fi1_audit_gap',
            'pending',
            1,
            gmdate(
                'Y-m-d H:i:s',
                time() - 3600
            )
        );

    $transition =
        $ticketService
            ->transition(
                $fixture[
                    'ticket_reference'
                ],
                'resolve',
                $actorUserId,
                [
                    'lifecycle_sync' => [
                        'source_module_code' =>
                            'work',

                        'correlation_reference' =>
                            $attempt[
                                'correlation_reference'
                            ],

                        'idempotency_key' =>
                            $attempt[
                                'idempotency_key'
                            ],

                        'attempt_reference' =>
                            $attempt[
                                'public_reference'
                            ],

                        'ticket_action_code' =>
                            'resolve',
                    ],
                ]
            );

    if (
        ($transition['ok'] ?? false)
        !== true
    ) {
        failA4(
            'fi1_native_transition_failed'
        );
    }

    $eventsAfterTransition =
        correlatedEventCount(
            $ticketDb,
            $fixture[
                'ticket_id'
            ],
            $attempt[
                'correlation_reference'
            ]
        );

    if ($eventsAfterTransition !== 1) {
        failA4(
            'fi1_correlated_event_count_after_transition'
        );
    }

    $inspection =
        $reconciliation
            ->inspectAttempt(
                attemptRow(
                    $workDb,
                    $attempt['id']
                )
            );

    if (
        ($inspection['decision'] ?? '')
            !== 'already_applied'
        ||
        ($inspection[
            'can_repair_audit'
        ] ?? false)
            !== true
    ) {
        failA4(
            'fi1_reconciliation_not_already_applied'
        );
    }

    $repair =
        $recovery
            ->recoverScheduled(
                $attempt['id'],
                'repair_audit',
                'system:scheduler:failure-injection'
            );

    if (
        ($repair['status'] ?? '')
            !== 'audit_repaired'
    ) {
        failA4(
            'fi1_audit_repair_failed'
        );
    }

    $repairedAttempt =
        attemptRow(
            $workDb,
            $attempt['id']
        );

    if (
        (string) $repairedAttempt[
            'result_code'
        ] !== 'completed'
        ||
        trim(
            (string) (
                $repairedAttempt[
                    'ticket_event_reference'
                ]
                ?? ''
            )
        ) === ''
    ) {
        failA4(
            'fi1_attempt_not_completed_from_evidence'
        );
    }

    $eventsAfterRepair =
        correlatedEventCount(
            $ticketDb,
            $fixture[
                'ticket_id'
            ],
            $attempt[
                'correlation_reference'
            ]
        );

    if ($eventsAfterRepair !== 1) {
        failA4(
            'fi1_duplicate_event_after_repair'
        );
    }

    $secondRepair =
        $recovery
            ->recoverScheduled(
                $attempt['id'],
                'repair_audit',
                'system:scheduler:failure-injection'
            );

    if (
        !in_array(
            (string) (
                $secondRepair[
                    'status'
                ]
                ?? ''
            ),
            [
                'not_actionable',
                'conflict',
            ],
            true
        )
    ) {
        failA4(
            'fi1_second_repair_not_fail_closed'
        );
    }

    if (
        correlatedEventCount(
            $ticketDb,
            $fixture['ticket_id'],
            $attempt[
                'correlation_reference'
            ]
        ) !== 1
    ) {
        failA4(
            'fi1_duplicate_event_after_second_repair'
        );
    }

    echo "FI1_AUDIT_GAP_EXACT_REPAIR=PASS\n";
    echo "FI1_DUPLICATE_TICKET_EVENT=0\n";

    $ticketDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi1_ticket'
    );

    $workDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi1_work'
    );

    /*
     * FI-2
     * A failed durable attempt whose backoff has elapsed is retried through
     * Scheduler recovery exactly once.
     */
    $workDb->exec(
        'SAVEPOINT c4_a4_fi2_work'
    );

    $ticketDb->exec(
        'SAVEPOINT c4_a4_fi2_ticket'
    );

    $attempt =
        createAttempt(
            $workDb,
            $fixture,
            'fi2_scheduler_retry',
            'failed',
            1,
            gmdate(
                'Y-m-d H:i:s',
                time() - 7200
            )
        );

    $summary1 =
        $scheduler
            ->process(
                $policy,
                'system:scheduler:failure-injection'
            );

    if (
        (int) (
            $summary1[
                'retry_completed'
            ]
            ?? 0
        ) !== 1
    ) {
        failA4(
            'fi2_scheduler_retry_not_completed'
        );
    }

    $afterRetry =
        attemptRow(
            $workDb,
            $attempt['id']
        );

    if (
        (string) $afterRetry[
            'result_code'
        ] !== 'completed'
    ) {
        failA4(
            'fi2_attempt_not_completed'
        );
    }

    if (
        correlatedEventCount(
            $ticketDb,
            $fixture['ticket_id'],
            $attempt[
                'correlation_reference'
            ]
        ) !== 1
    ) {
        failA4(
            'fi2_transition_event_count'
        );
    }

    $summary2 =
        $scheduler
            ->process(
                $policy,
                'system:scheduler:failure-injection'
            );

    if (
        correlatedEventCount(
            $ticketDb,
            $fixture['ticket_id'],
            $attempt[
                'correlation_reference'
            ]
        ) !== 1
    ) {
        failA4(
            'fi2_duplicate_event_on_second_scheduler_pass'
        );
    }

    echo "FI2_SCHEDULER_RETRY_RECOVERY=PASS\n";
    echo "FI2_DUPLICATE_TICKET_EVENT=0\n";

    $ticketDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi2_ticket'
    );

    $workDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi2_work'
    );

    /*
     * FI-3
     * max attempt cap blocks retry.
     */
    $workDb->exec(
        'SAVEPOINT c4_a4_fi3_work'
    );

    $ticketDb->exec(
        'SAVEPOINT c4_a4_fi3_ticket'
    );

    $attempt =
        createAttempt(
            $workDb,
            $fixture,
            'fi3_max_attempts',
            'failed',
            4,
            gmdate(
                'Y-m-d H:i:s',
                time() - 7200
            )
        );

    $summary =
        $scheduler
            ->process(
                $policy,
                'system:scheduler:failure-injection'
            );

    if (
        (int) (
            $summary[
                'max_attempts_reached'
            ]
            ?? 0
        ) !== 1
        ||
        correlatedEventCount(
            $ticketDb,
            $fixture['ticket_id'],
            $attempt[
                'correlation_reference'
            ]
        ) !== 0
    ) {
        failA4(
            'fi3_max_attempt_guard'
        );
    }

    echo "FI3_MAX_ATTEMPT_GUARD=PASS\n";

    $ticketDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi3_ticket'
    );

    $workDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi3_work'
    );

    /*
     * FI-4
     * recent failed attempt remains in backoff and cannot transition Ticket.
     */
    $workDb->exec(
        'SAVEPOINT c4_a4_fi4_work'
    );

    $ticketDb->exec(
        'SAVEPOINT c4_a4_fi4_ticket'
    );

    $attempt =
        createAttempt(
            $workDb,
            $fixture,
            'fi4_backoff',
            'failed',
            1,
            gmdate(
                'Y-m-d H:i:s'
            )
        );

    $summary =
        $scheduler
            ->process(
                $policy,
                'system:scheduler:failure-injection'
            );

    if (
        (int) (
            $summary[
                'backoff_wait'
            ]
            ?? 0
        ) !== 1
        ||
        correlatedEventCount(
            $ticketDb,
            $fixture['ticket_id'],
            $attempt[
                'correlation_reference'
            ]
        ) !== 0
    ) {
        failA4(
            'fi4_backoff_guard'
        );
    }

    echo "FI4_BACKOFF_GUARD=PASS\n";

    $ticketDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi4_ticket'
    );

    $workDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi4_work'
    );

    /*
     * FI-5
     * stale retrying without exact correlated Ticket evidence is ambiguous.
     * Scheduler must surface review and never reset/replay.
     */
    $workDb->exec(
        'SAVEPOINT c4_a4_fi5_work'
    );

    $ticketDb->exec(
        'SAVEPOINT c4_a4_fi5_ticket'
    );

    $attempt =
        createAttempt(
            $workDb,
            $fixture,
            'fi5_stale_retrying',
            'retrying',
            2,
            gmdate(
                'Y-m-d H:i:s',
                time() - 7200
            )
        );

    $summary =
        $scheduler
            ->process(
                $policy,
                'system:scheduler:failure-injection'
            );

    $after =
        attemptRow(
            $workDb,
            $attempt['id']
        );

    if (
        (int) (
            $summary[
                'stale_needs_review'
            ]
            ?? 0
        ) !== 1
        ||
        (string) $after[
            'result_code'
        ] !== 'retrying'
        ||
        correlatedEventCount(
            $ticketDb,
            $fixture['ticket_id'],
            $attempt[
                'correlation_reference'
            ]
        ) !== 0
    ) {
        failA4(
            'fi5_stale_retrying_fail_closed'
        );
    }

    echo "FI5_STALE_RETRYING_NEEDS_REVIEW=PASS\n";
    echo "FI5_BLIND_RESET_OR_REPLAY=0\n";

    $ticketDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi5_ticket'
    );

    $workDb->exec(
        'ROLLBACK TO SAVEPOINT c4_a4_fi5_work'
    );

} finally {
    if ($ticketDb->inTransaction()) {
        $ticketDb->rollBack();
    }

    if ($workDb->inTransaction()) {
        $workDb->rollBack();
    }
}

/*
 * Exact persistent-state proof after outer rollback.
 */
$workPost = [
    'rules' =>
        (int) $workDb->query(
            'SELECT COUNT(*) FROM work_ticket_lifecycle_sync_rules'
        )->fetchColumn(),

    'attempts' =>
        (int) $workDb->query(
            'SELECT COUNT(*) FROM work_ticket_lifecycle_sync_attempts'
        )->fetchColumn(),

    'links' =>
        (int) $workDb->query(
            'SELECT COUNT(*) FROM work_item_source_links'
        )->fetchColumn(),
];

$ticketEventPost =
    (int) $ticketDb->query(
        'SELECT COUNT(*) FROM ticketing_events'
    )->fetchColumn();

$statusStatement =
    $ticketDb->prepare(
        "SELECT status_code
         FROM ticketing_tickets
         WHERE id = ?
         LIMIT 1"
    );

$statusStatement->execute([
    (int) $candidate[
        'ticket_id'
    ],
]);

$ticketStatusPost =
    (string)
    $statusStatement
        ->fetchColumn();

if ($workPost !== $workPre) {
    failA4(
        'work_rollback_preimage_mismatch'
    );
}

if (
    $ticketEventPost
    !==
    $ticketEventPre
) {
    failA4(
        'ticket_event_rollback_preimage_mismatch'
    );
}

if (
    $ticketStatusPost
    !==
    $ticketStatusPre
) {
    failA4(
        'ticket_status_rollback_preimage_mismatch'
    );
}

echo
    'A4_SELECTED_TICKET_REFERENCE='
    . (string) $candidate[
        'ticket_reference'
    ]
    . PHP_EOL;

echo
    'A4_SELECTED_ACTOR_REFERENCE='
    . $actorReference
    . PHP_EOL;

echo
    'A4_WORK_ROLLBACK_PREIMAGE='
    . json_encode(
        $workPre,
        JSON_UNESCAPED_SLASHES
    )
    . PHP_EOL;

echo
    'A4_WORK_ROLLBACK_POSTIMAGE='
    . json_encode(
        $workPost,
        JSON_UNESCAPED_SLASHES
    )
    . PHP_EOL;

echo
    'A4_TICKET_EVENT_PRECOUNT='
    . $ticketEventPre
    . PHP_EOL;

echo
    'A4_TICKET_EVENT_POSTCOUNT='
    . $ticketEventPost
    . PHP_EOL;

echo
    'A4_TICKET_STATUS_PRE='
    . $ticketStatusPre
    . PHP_EOL;

echo
    'A4_TICKET_STATUS_POST='
    . $ticketStatusPost
    . PHP_EOL;

echo "A4_DUAL_DB_ROLLBACK=PASS_EXACT_PREIMAGE\n";
echo "TICKET_WORK_LIFECYCLE_SYNC_FAILURE_INJECTION_E2E_TEST=PASS\n";
