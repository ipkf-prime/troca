<?php

namespace App\Services\Automation\Correspondence;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Aggregates recipient-level dispatch completion into the
 * parent correspondence lifecycle.
 *
 * Aggregate rule:
 * every current primary_recipient must have at least one
 * successful dispatch in status dispatched|delivered.
 *
 * Multiple channels for the same recipient therefore do
 * not require every dispatch row to succeed.
 */
final class CorrespondenceDispatchAggregateCompletionService
{
    private AutomationOperationalRuntime $runtime;

    private EnterpriseAutomationContextService $enterpriseContext;

    public function __construct(
        ?AutomationOperationalRuntime $runtime = null,
        ?EnterpriseAutomationContextService $enterpriseContext = null
    ) {
        $this->runtime =
            $runtime
            ?? new AutomationOperationalRuntime();

        $this->enterpriseContext =
            $enterpriseContext
            ?? new EnterpriseAutomationContextService();
    }


    public function completeIfReady(
        string $correspondencePublicReference,
        int $userId
    ): array {
        $correspondencePublicReference =
            trim(
                $correspondencePublicReference
            );

        if (
            $correspondencePublicReference === ''
            ||
            $userId < 1
        ) {
            return $this->failure(
                'invalid_correspondence_aggregate_request'
            );
        }


        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

        } catch (Throwable) {
            return $this->failure(
                'organizational_context_required'
            );
        }


        $pdo =
            $this->runtime
                ->connection();


        try {
            $pdo->beginTransaction();


            /*
             * Lock the parent first. This serializes
             * aggregate completion for one correspondence.
             */
            $correspondence =
                $this->correspondenceForUpdate(
                    $pdo,
                    $correspondencePublicReference
                );


            if ($correspondence === null) {
                $pdo->rollBack();

                return $this->failure(
                    'correspondence_not_found'
                );
            }


            try {
                $this->enterpriseContext
                    ->assertCorrespondenceAccess(
                        [
                            'root_organization_id' =>
                                $correspondence[
                                    'root_organization_id'
                                ]
                                ?? null,

                            'organization_id' =>
                                $correspondence[
                                    'organization_id'
                                ]
                                ?? null,
                        ],
                        $actor
                    );

            } catch (RuntimeException) {
                $pdo->rollBack();

                return $this->failure(
                    'forbidden_scope'
                );
            }


            if (
                (string) (
                    $correspondence[
                        'direction_code'
                    ]
                    ?? ''
                ) !== 'outgoing'
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'aggregate_completion_requires_outgoing'
                );
            }


            /*
             * Idempotent aggregate terminal state.
             */
            if (
                (string) (
                    $correspondence[
                        'status_code'
                    ]
                    ?? ''
                ) === 'dispatched'
            ) {
                $pdo->rollBack();

                return [
                    'ok' =>
                        true,

                    'completed' =>
                        true,

                    'already_completed' =>
                        true,

                    'correspondence_status_code' =>
                        'dispatched',

                    'dispatched_at' =>
                        $correspondence[
                            'dispatched_at'
                        ]
                        ?? null,

                    'correspondence_status_changed' =>
                        false,

                    'event_created' =>
                        false,

                    'provider_invoked' =>
                        false,
                ];
            }


            if (
                (string) (
                    $correspondence[
                        'status_code'
                    ]
                    ?? ''
                ) !== 'registered'
                ||
                (
                    $correspondence[
                        'dispatched_at'
                    ]
                    ?? null
                ) !== null
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'correspondence_not_aggregate_completable'
                );
            }


            /*
             * Lock current primary-recipient topology.
             */
            $recipients =
                $this->primaryRecipientsForUpdate(
                    $pdo,
                    (int) $correspondence['id']
                );


            if ($recipients === []) {
                $pdo->rollBack();

                return $this->failure(
                    'primary_recipient_required'
                );
            }


            /*
             * Lock all dispatch rows belonging to this
             * correspondence.
             */
            $dispatches =
                $this->dispatchesForUpdate(
                    $pdo,
                    (int) $correspondence['id']
                );


            $recipientIds = [];

            foreach ($recipients as $recipient) {
                $recipientId =
                    (int) (
                        $recipient['id']
                        ?? 0
                    );

                if ($recipientId > 0) {
                    $recipientIds[$recipientId] =
                        true;
                }
            }


            $successfulRecipientIds = [];

            $successfulDispatchCount = 0;

            $recipientAttemptStates = [];

            $aggregateDispatchedAt = null;


            foreach ($dispatches as $dispatch) {
                $partyId =
                    (int) (
                        $dispatch[
                            'correspondence_party_id'
                        ]
                        ?? 0
                    );


                if (
                    $partyId < 1
                    ||
                    !isset(
                        $recipientIds[
                            $partyId
                        ]
                    )
                ) {
                    continue;
                }


                $status =
                    (string) (
                        $dispatch[
                            'status_code'
                        ]
                        ?? ''
                    );


                $latestAttemptStatus =
                    (string) (
                        $dispatch[
                            'latest_attempt_status_code'
                        ]
                        ?? ''
                    );


                if (
                    $latestAttemptStatus !== ''
                    &&
                    !isset(
                        $successfulRecipientIds[
                            $partyId
                        ]
                    )
                ) {
                    $recipientAttemptStates[
                        $partyId
                    ][
                        $latestAttemptStatus
                    ] = true;
                }


                if (
                    !in_array(
                        $status,
                        [
                            'dispatched',
                            'delivered',
                        ],
                        true
                    )
                    ||
                    (
                        $dispatch[
                            'cancelled_at'
                        ]
                        ?? null
                    ) !== null
                ) {
                    continue;
                }


                $timestamp =
                    trim(
                        (string) (
                            $dispatch[
                                'dispatched_at'
                            ]
                            ?? ''
                        )
                    );


                if ($timestamp === '') {
                    throw new RuntimeException(
                        'aggregate_dispatch_timestamp_unavailable'
                    );
                }


                $successfulRecipientIds[
                    $partyId
                ] = true;

                $successfulDispatchCount++;


                if (
                    $aggregateDispatchedAt === null
                    ||
                    strcmp(
                        $timestamp,
                        $aggregateDispatchedAt
                    ) > 0
                ) {
                    $aggregateDispatchedAt =
                        $timestamp;
                }
            }


            $recipientTotal =
                count(
                    $recipientIds
                );

            $recipientSuccessCount =
                count(
                    $successfulRecipientIds
                );

            $remaining =
                max(
                    0,
                    $recipientTotal
                    - $recipientSuccessCount
                );


            $recipientFailedCount = 0;
            $recipientUncertainCount = 0;
            $recipientProcessingCount = 0;
            $recipientPendingCount = 0;


            foreach (
                array_keys(
                    $recipientIds
                )
                as $recipientId
            ) {
                if (
                    isset(
                        $successfulRecipientIds[
                            $recipientId
                        ]
                    )
                ) {
                    continue;
                }


                $states =
                    $recipientAttemptStates[
                        $recipientId
                    ]
                    ?? [];


                if (isset($states['uncertain'])) {
                    $recipientUncertainCount++;
                    continue;
                }


                if (isset($states['processing'])) {
                    $recipientProcessingCount++;
                    continue;
                }


                if (isset($states['failed'])) {
                    $recipientFailedCount++;
                    continue;
                }


                $recipientPendingCount++;
            }


            /*
             * Not an error: aggregate is simply not ready.
             */
            if ($remaining > 0) {
                $pdo->rollBack();

                return [
                    'ok' =>
                        true,

                    'completed' =>
                        false,

                    'already_completed' =>
                        false,

                    'correspondence_status_code' =>
                        'registered',

                    'recipient_total' =>
                        $recipientTotal,

                    'recipient_success_count' =>
                        $recipientSuccessCount,

                    'remaining_recipient_count' =>
                        $remaining,

                    'recipient_failed_count' =>
                        $recipientFailedCount,

                    'recipient_uncertain_count' =>
                        $recipientUncertainCount,

                    'recipient_processing_count' =>
                        $recipientProcessingCount,

                    'recipient_pending_count' =>
                        $recipientPendingCount,

                    'successful_dispatch_count' =>
                        $successfulDispatchCount,

                    'aggregate_state_code' =>
                        $recipientUncertainCount > 0
                            ? 'uncertain'
                            : (
                                $recipientProcessingCount > 0
                                    ? 'processing'
                                    : (
                                        $recipientFailedCount > 0
                                            ? 'retryable_failure'
                                            : 'pending'
                                    )
                            ),

                    'correspondence_status_changed' =>
                        false,

                    'event_created' =>
                        false,

                    'provider_invoked' =>
                        false,
                ];
            }


            if ($aggregateDispatchedAt === null) {
                throw new RuntimeException(
                    'aggregate_dispatch_timestamp_unavailable'
                );
            }


            /*
             * Final aggregate transition.
             *
             * channel_code is deliberately NOT modified.
             */
            $update =
                $pdo->prepare("
                    UPDATE correspondences

                    SET
                        status_code =
                            'dispatched',

                        dispatched_at = ?,

                        updated_by_user_id = ?,

                        lock_version =
                            lock_version + 1,

                        updated_at =
                            CURRENT_TIMESTAMP

                    WHERE
                        id = ?
                        AND status_code =
                            'registered'
                        AND dispatched_at IS NULL
                ");

            $update->execute([
                $aggregateDispatchedAt,
                $userId,
                (int) $correspondence['id'],
            ]);


            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'correspondence_aggregate_completion_conflict'
                );
            }


            /*
             * One correspondence lifecycle event.
             */
            $event =
                $pdo->prepare("
                    INSERT INTO correspondence_events (
                        correspondence_id,

                        root_organization_id,
                        organization_id,
                        secretariat_desk_id,

                        referral_id,

                        event_type_code,

                        actor_user_id,
                        actor_appointment_reference,
                        actor_context_snapshot_json,
                        actor_org_unit_id,

                        occurred_at,

                        previous_status_code,
                        resulting_status_code,

                        safe_metadata_json,

                        created_at
                    )
                    VALUES (
                        ?,

                        ?,
                        ?,
                        ?,

                        NULL,

                        'dispatched',

                        ?,
                        ?,
                        ?,
                        ?,

                        ?,

                        'registered',
                        'dispatched',

                        ?,

                        CURRENT_TIMESTAMP
                    )
                ");


            $event->execute([
                (int) $correspondence['id'],

                $this->nullableInt(
                    $correspondence[
                        'root_organization_id'
                    ]
                    ?? null
                ),

                (int) $correspondence[
                    'organization_id'
                ],

                $this->nullableInt(
                    $correspondence[
                        'secretariat_desk_id'
                    ]
                    ?? null
                ),

                $userId,

                $this->nullableText(
                    $actor[
                        'appointment_reference'
                    ]
                    ?? null,
                    36
                ),

                $this->json(
                    $this->actorSnapshot(
                        $actor,
                        $userId
                    )
                ),

                $this->nullableInt(
                    $actor[
                        'org_unit_id'
                    ]
                    ?? null
                ),

                $aggregateDispatchedAt,

                $this->json(
                    [
                        'recipient_total' =>
                            $recipientTotal,

                        'recipient_success_count' =>
                            $recipientSuccessCount,

                        'remaining_recipient_count' =>
                            0,

                        'successful_dispatch_count' =>
                            $successfulDispatchCount,

                        'aggregate_rule' =>
                            'each_primary_recipient_has_successful_dispatch',
                    ]
                ),
            ]);


            if ($event->rowCount() !== 1) {
                throw new RuntimeException(
                    'correspondence_dispatched_event_insert_failed'
                );
            }


            $pdo->commit();


            return [
                'ok' =>
                    true,

                'completed' =>
                    true,

                'already_completed' =>
                    false,

                'correspondence_status_code' =>
                    'dispatched',

                'dispatched_at' =>
                    $aggregateDispatchedAt,

                'recipient_total' =>
                    $recipientTotal,

                'recipient_success_count' =>
                    $recipientSuccessCount,

                'remaining_recipient_count' =>
                    0,

                'recipient_failed_count' =>
                    0,

                'recipient_uncertain_count' =>
                    0,

                'recipient_processing_count' =>
                    0,

                'recipient_pending_count' =>
                    0,

                'successful_dispatch_count' =>
                    $successfulDispatchCount,

                'aggregate_state_code' =>
                    'dispatched',

                'correspondence_status_changed' =>
                    true,

                'event_created' =>
                    true,

                'provider_invoked' =>
                    false,
            ];

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            return $this->failure(
                $this->knownFailureCode(
                    $exception,
                    'correspondence_aggregate_completion_failed'
                )
            );
        }
    }


    private function correspondenceForUpdate(
        PDO $pdo,
        string $publicReference
    ): ?array {
        $statement =
            $pdo->prepare("
                SELECT *
                FROM correspondences
                WHERE public_reference = ?
                LIMIT 1
                FOR UPDATE
            ");

        $statement->execute([
            $publicReference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }


    private function primaryRecipientsForUpdate(
        PDO $pdo,
        int $correspondenceId
    ): array {
        $statement =
            $pdo->prepare("
                SELECT
                    id,
                    party_role_code,
                    target_kind_code,
                    sort_order

                FROM correspondence_parties

                WHERE
                    correspondence_id = ?
                    AND party_role_code =
                        'primary_recipient'

                ORDER BY
                    sort_order,
                    id

                FOR UPDATE
            ");

        $statement->execute([
            $correspondenceId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    private function dispatchesForUpdate(
        PDO $pdo,
        int $correspondenceId
    ): array {
        $statement =
            $pdo->prepare("
                SELECT
                    dispatches.id,
                    dispatches.correspondence_party_id,
                    dispatches.status_code,
                    dispatches.dispatched_at,
                    dispatches.delivered_at,
                    dispatches.failed_at,
                    dispatches.cancelled_at,

                    (
                        SELECT attempts.status_code
                        FROM correspondence_dispatch_attempts attempts
                        WHERE attempts.dispatch_id = dispatches.id
                        ORDER BY
                            attempts.attempt_number DESC,
                            attempts.id DESC
                        LIMIT 1
                    ) AS latest_attempt_status_code

                FROM correspondence_dispatches dispatches

                WHERE correspondence_id = ?

                ORDER BY id

                FOR UPDATE
            ");

        $statement->execute([
            $correspondenceId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    private function actorSnapshot(
        array $actor,
        int $userId
    ): array {
        return [
            'user_id' =>
                $userId,

            'appointment_reference' =>
                $actor[
                    'appointment_reference'
                ]
                ?? null,

            'root_organization_id' =>
                $actor[
                    'root_organization_id'
                ]
                ?? null,

            'organization_id' =>
                $actor[
                    'organization_id'
                ]
                ?? null,

            'org_unit_id' =>
                $actor[
                    'org_unit_id'
                ]
                ?? null,

            'secretariat_desk_id' =>
                $actor[
                    'secretariat_desk_id'
                ]
                ?? null,
        ];
    }


    private function nullableInt(
        mixed $value
    ): ?int {
        $value =
            (int) (
                $value
                ?? 0
            );

        return
            $value > 0
                ? $value
                : null;
    }


    private function nullableText(
        mixed $value,
        int $limit
    ): ?string {
        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return null;
        }

        return
            mb_substr(
                $value,
                0,
                $limit,
                'UTF-8'
            );
    }


    private function json(
        array $value
    ): string {
        return
            json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
    }


    private function knownFailureCode(
        Throwable $exception,
        string $fallback
    ): string {
        $message =
            strtolower(
                trim(
                    $exception->getMessage()
                )
            );

        if (
            preg_match(
                '/^[a-z][a-z0-9._-]{1,99}$/',
                $message
            ) === 1
        ) {
            return $message;
        }

        return $fallback;
    }


    private function failure(
        string $code
    ): array {
        return [
            'ok' =>
                false,

            'errors' => [
                $code,
            ],

            'completed' =>
                false,

            'correspondence_status_changed' =>
                false,

            'event_created' =>
                false,

            'provider_invoked' =>
                false,
        ];
    }
}
