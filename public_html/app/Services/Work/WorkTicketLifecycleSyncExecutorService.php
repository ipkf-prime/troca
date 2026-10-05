<?php

declare(strict_types=1);

namespace App\Services\Work;

use App\Repositories\TicketLifecycleTransitionRepository;
use App\Repositories\WorkTicketLifecycleSyncRepository;
use App\Services\Ticketing\TicketLifecycleTransitionService;
use IPKF\Database\Connections\ConnectionResolver;
use PDOException;
use Throwable;

/**
 * Executes a deterministic C3 lifecycle-sync plan through the canonical
 * Ticket lifecycle service.
 *
 * Safety contract:
 * - no direct Ticket SQL exists here;
 * - actor identity must match the Work transition actor;
 * - one idempotency key may execute at most once;
 * - any existing attempt, including pending/failed/rejected, is fail-closed
 *   and is NOT automatically re-executed;
 * - native Ticket lifecycle guards remain authoritative.
 */
final class WorkTicketLifecycleSyncExecutorService
{
    private WorkTicketLifecycleSyncRepository $sync;
    private TicketLifecycleTransitionService $tickets;

    public function __construct(
        ?ConnectionResolver $connections = null,
        ?WorkTicketLifecycleSyncRepository $sync = null,
        ?TicketLifecycleTransitionService $tickets = null
    ) {
        $resolver =
            $connections
            ?? new ConnectionResolver();

        $this->sync =
            $sync
            ?? new WorkTicketLifecycleSyncRepository(
                $resolver
            );

        $this->tickets =
            $tickets
            ?? new TicketLifecycleTransitionService(
                new TicketLifecycleTransitionRepository(
                    $resolver
                )
            );
    }

    public function executePlan(
        array $plan,
        int $userId,
        array $context = []
    ): array {
        $plan =
            $this->normalizePlan(
                $plan
            );

        if ($userId < 1) {
            throw new \InvalidArgumentException(
                'work_ticket_lifecycle_sync_user_invalid'
            );
        }

        $expectedActor =
            'user:' . $userId;

        if (
            !hash_equals(
                $expectedActor,
                $plan['actor_user_reference']
            )
        ) {
            throw new \RuntimeException(
                'work_ticket_lifecycle_sync_actor_mismatch'
            );
        }

        $existing =
            $this->sync
                ->attemptByIdempotencyKey(
                    $plan['idempotency_key']
                );

        if (is_array($existing)) {
            return
                $this->existingResult(
                    $existing
                );
        }

        $attemptReference =
            'TWLSA-'
            . strtoupper(
                bin2hex(
                    random_bytes(12)
                )
            );

        try {
            $attemptId =
                $this->sync
                    ->createAttempt([
                        'public_reference' =>
                            $attemptReference,

                        'idempotency_key' =>
                            $plan[
                                'idempotency_key'
                            ],

                        'correlation_reference' =>
                            $plan[
                                'correlation_reference'
                            ],

                        'rule_id' =>
                            $plan['rule_id'],

                        'work_item_id' =>
                            $plan['work_item_id'],

                        'work_activity_event_id' =>
                            $plan[
                                'work_activity_event_id'
                            ],

                        'source_link_id' =>
                            $plan['source_link_id'],

                        'source_link_reference' =>
                            $plan[
                                'source_link_reference'
                            ],

                        'ticket_reference' =>
                            $plan[
                                'ticket_reference'
                            ],

                        'previous_work_status_code' =>
                            $plan[
                                'previous_work_status_code'
                            ],

                        'resulting_work_status_code' =>
                            $plan[
                                'resulting_work_status_code'
                            ],

                        'ticket_action_code' =>
                            $plan[
                                'ticket_action_code'
                            ],

                        'actor_user_reference' =>
                            $plan[
                                'actor_user_reference'
                            ],

                        'metadata_json' =>
                            json_encode(
                                [
                                    'correlation_reference' =>
                                        $plan[
                                            'correlation_reference'
                                        ],

                                    'rule_reference' =>
                                        $plan[
                                            'rule_reference'
                                        ],

                                    'relation_type_code' =>
                                        $plan[
                                            'relation_type_code'
                                        ],
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                                | JSON_THROW_ON_ERROR
                            ),
                    ]);

        } catch (PDOException $exception) {
            if (
                (string) $exception->getCode()
                !== '23000'
            ) {
                throw $exception;
            }

            $existing =
                $this->sync
                    ->attemptByIdempotencyKey(
                        $plan[
                            'idempotency_key'
                        ]
                    );

            if (!is_array($existing)) {
                throw $exception;
            }

            return
                $this->existingResult(
                    $existing
                );
        }

        try {
            $context =
                $this->withLifecycleAuditContext(
                    $context,
                    $plan,
                    $attemptReference
                );

            $ticketResult =
                $this->tickets
                    ->transition(
                        $plan[
                            'ticket_reference'
                        ],
                        $plan[
                            'ticket_action_code'
                        ],
                        $userId,
                        $context
                    );

            if (
                ($ticketResult['ok'] ?? false)
                !== true
            ) {
                $status =
                    trim(
                        (string) (
                            $ticketResult[
                                'status'
                            ]
                            ?? 'ticket_transition_rejected'
                        )
                    );

                $this->sync
                    ->completeAttempt(
                        $attemptId,
                        [
                            'result_code' =>
                                'rejected',

                            'ticket_previous_status_code' =>
                                null,

                            'ticket_resulting_status_code' =>
                                null,

                            'ticket_event_reference' =>
                                null,

                            'error_code' =>
                                $status,

                            'metadata_json' =>
                                $this->resultMetadata(
                                    $plan,
                                    $ticketResult
                                ),

                            'completed_at' =>
                                gmdate(
                                    'Y-m-d H:i:s'
                                ),
                        ]
                    );

                return [
                    'ok' => false,
                    'idempotent' => false,
                    'status' => $status,
                    'attempt_id' => $attemptId,
                    'attempt_reference' =>
                        $attemptReference,
                    'ticket' => $ticketResult,
                ];
            }

            $eventReference =
                trim(
                    (string) (
                        $ticketResult[
                            'event_reference'
                        ]
                        ?? ''
                    )
                );

            if ($eventReference === '') {
                throw new \RuntimeException(
                    'work_ticket_lifecycle_sync_ticket_event_missing'
                );
            }

            $this->sync
                ->completeAttempt(
                    $attemptId,
                    [
                        'result_code' =>
                            'completed',

                        'ticket_previous_status_code' =>
                            $ticketResult[
                                'previous_status_code'
                            ]
                            ?? null,

                        'ticket_resulting_status_code' =>
                            $ticketResult[
                                'resulting_status_code'
                            ]
                            ?? null,

                        'ticket_event_reference' =>
                            $eventReference,

                        'error_code' =>
                            null,

                        'metadata_json' =>
                            $this->resultMetadata(
                                $plan,
                                $ticketResult
                            ),

                        'completed_at' =>
                            gmdate(
                                'Y-m-d H:i:s'
                            ),
                    ]
                );

            return [
                'ok' => true,
                'idempotent' => false,
                'status' => 'completed',
                'attempt_id' => $attemptId,
                'attempt_reference' =>
                    $attemptReference,
                'ticket' => $ticketResult,
            ];

        } catch (Throwable $exception) {
            /*
             * Best-effort durable failure audit.
             *
             * If the caller owns an outer Work transaction, this update
             * participates in that transaction. Production execution without
             * an outer transaction leaves the pending/failed record available
             * for reconciliation instead of blind automatic replay.
             */
            try {
                $this->sync
                    ->completeAttempt(
                        $attemptId,
                        [
                            'result_code' =>
                                'failed',

                            'ticket_previous_status_code' =>
                                null,

                            'ticket_resulting_status_code' =>
                                null,

                            'ticket_event_reference' =>
                                null,

                            'error_code' =>
                                $this->safeErrorCode(
                                    $exception
                                ),

                            'metadata_json' =>
                                $this->resultMetadata(
                                    $plan,
                                    [
                                        'exception_class' =>
                                            $exception::class,
                                    ]
                                ),

                            'completed_at' =>
                                gmdate(
                                    'Y-m-d H:i:s'
                                ),
                        ]
                    );

            } catch (Throwable) {
                /*
                 * Never replace the native transition exception with an audit
                 * persistence exception.
                 */
            }

            throw $exception;
        }
    }

    private function withLifecycleAuditContext(
        array $context,
        array $plan,
        string $attemptReference
    ): array {
        $context['lifecycle_sync'] = [
            'source_module_code' => 'work',
            'correlation_reference' =>
                $plan['correlation_reference'],
            'idempotency_key' =>
                $plan['idempotency_key'],
            'attempt_reference' =>
                $attemptReference,
            'ticket_action_code' =>
                $plan['ticket_action_code'],
        ];

        return $context;
    }


    private function normalizePlan(
        array $plan
    ): array {
        $integerFields = [
            'rule_id',
            'work_project_id',
            'work_item_id',
            'work_activity_event_id',
            'source_link_id',
        ];

        foreach ($integerFields as $field) {
            $plan[$field] =
                (int) (
                    $plan[$field]
                    ?? 0
                );

            if ($plan[$field] < 1) {
                throw new \InvalidArgumentException(
                    'work_ticket_lifecycle_sync_plan_invalid'
                );
            }
        }

        $stringFields = [
            'rule_reference',
            'source_link_reference',
            'ticket_reference',
            'relation_type_code',
            'previous_work_status_code',
            'resulting_work_status_code',
            'ticket_action_code',
            'actor_user_reference',
            'idempotency_key',
            'correlation_reference',
        ];

        foreach ($stringFields as $field) {
            $plan[$field] =
                trim(
                    (string) (
                        $plan[$field]
                        ?? ''
                    )
                );

            if ($plan[$field] === '') {
                throw new \InvalidArgumentException(
                    'work_ticket_lifecycle_sync_plan_invalid'
                );
            }
        }

        if (
            !in_array(
                $plan[
                    'ticket_action_code'
                ],
                [
                    'resolve',
                    'close',
                    'reopen',
                ],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'work_ticket_lifecycle_sync_action_invalid'
            );
        }

        return $plan;
    }

    private function existingResult(
        array $attempt
    ): array {
        $resultCode =
            trim(
                (string) (
                    $attempt['result_code']
                    ?? 'pending'
                )
            );

        return [
            'ok' =>
                $resultCode ===
                    'completed',

            'idempotent' => true,

            'status' =>
                'already_'
                . (
                    $resultCode !== ''
                        ? $resultCode
                        : 'pending'
                ),

            'attempt_id' =>
                (int) (
                    $attempt['id']
                    ?? 0
                ),

            'attempt_reference' =>
                (string) (
                    $attempt[
                        'public_reference'
                    ]
                    ?? ''
                ),

            'attempt' =>
                $attempt,
        ];
    }

    private function resultMetadata(
        array $plan,
        array $result
    ): string {
        return
            json_encode(
                [
                    'correlation_reference' =>
                        $plan[
                            'correlation_reference'
                        ],

                    'rule_reference' =>
                        $plan[
                            'rule_reference'
                        ],

                    'source_link_reference' =>
                        $plan[
                            'source_link_reference'
                        ],

                    'ticket_result' =>
                        $result,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
    }

    private function safeErrorCode(
        Throwable $exception
    ): string {
        $code =
            trim(
                $exception->getMessage()
            );

        if ($code === '') {
            return
                'sync_executor_exception';
        }

        return
            substr(
                preg_replace(
                    '/[^A-Za-z0-9_.:-]+/',
                    '_',
                    $code
                )
                ?? 'sync_executor_exception',
                0,
                100
            );
    }
}
