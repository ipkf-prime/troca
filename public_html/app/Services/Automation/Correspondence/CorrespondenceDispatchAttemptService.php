<?php

namespace App\Services\Automation\Correspondence;

use PDO;
use RuntimeException;
use Throwable;

final class CorrespondenceDispatchAttemptService
{
    private const EXECUTABLE_DISPATCH_STATUSES = [
        'pending',
    ];

    private const ATTEMPT_OUTCOMES = [
        'succeeded',
        'failed',
        'uncertain',
    ];

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

    /**
     * D4C attempt lifecycle.
     *
     * This service owns only the ATTEMPT record.
     *
     * It MUST NOT:
     * - change correspondences.status_code,
     * - change correspondences.channel_code,
     * - set correspondences.dispatched_at,
     * - complete correspondence_dispatches,
     * - create follow-up rows.
     *
     * The transport is explicitly injected by the caller.
     * There is intentionally no default real provider.
     */
    public function attempt(
        string $dispatchPublicReference,
        int $userId,
        CorrespondenceDispatchTransportInterface $transport
    ): array {
        $dispatchPublicReference =
            trim(
                $dispatchPublicReference
            );

        if (
            $dispatchPublicReference === ''
            || $userId < 1
        ) {
            return $this->failure(
                'invalid_dispatch_attempt_request'
            );
        }

        $providerCode =
            strtolower(
                trim(
                    $transport->code()
                )
            );

        if (
            preg_match(
                '/^[a-z][a-z0-9._-]{1,99}$/',
                $providerCode
            ) !== 1
        ) {
            return $this->failure(
                'dispatch_transport_code_invalid'
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

        /*
         * -------------------------------------------------
         * Phase 1:
         * Reserve one durable processing attempt.
         *
         * No external transport call happens while the
         * database transaction is open.
         * -------------------------------------------------
         */

        try {
            $pdo->beginTransaction();

            $dispatch =
                $this->dispatchForUpdate(
                    $pdo,
                    $dispatchPublicReference
                );

            if ($dispatch === null) {
                $pdo->rollBack();

                return $this->failure(
                    'dispatch_request_not_found'
                );
            }

            try {
                $this->enterpriseContext
                    ->assertCorrespondenceAccess(
                        [
                            'root_organization_id' =>
                                $dispatch[
                                    'correspondence_root_organization_id'
                                ]
                                ?? null,

                            'organization_id' =>
                                $dispatch[
                                    'correspondence_organization_id'
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
                    $dispatch[
                        'correspondence_direction_code'
                    ]
                    ?? ''
                ) !== 'outgoing'
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'dispatch_attempt_requires_outgoing'
                );
            }

            if (
                (string) (
                    $dispatch[
                        'correspondence_status_code'
                    ]
                    ?? ''
                ) !== 'registered'
                ||
                (
                    $dispatch[
                        'correspondence_dispatched_at'
                    ]
                    ?? null
                ) !== null
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'correspondence_not_attemptable'
                );
            }

            if (
                !in_array(
                    (string) (
                        $dispatch[
                            'status_code'
                        ]
                        ?? ''
                    ),
                    self::EXECUTABLE_DISPATCH_STATUSES,
                    true
                )
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'dispatch_request_not_attemptable'
                );
            }

            /*
             * A processing, uncertain, or successful attempt
             * blocks automatic continuation.
             *
             * - processing: another worker may own it.
             * - uncertain: provider acceptance is unknown;
             *   blind retry could duplicate delivery.
             * - succeeded: delivery was positively accepted;
             *   repeating transport would duplicate delivery.
             */
            $blocking =
                $this->blockingAttempt(
                    $pdo,
                    (int) $dispatch['id']
                );

            if (is_array($blocking)) {
                $pdo->rollBack();

                return [
                    'ok' => false,

                    'errors' => [
                        match (
                            (string) (
                                $blocking[
                                    'status_code'
                                ]
                                ?? ''
                            )
                        ) {
                            'succeeded' =>
                                'dispatch_attempt_already_succeeded',

                            'uncertain' =>
                                'dispatch_attempt_uncertain_requires_review',

                            default =>
                                'dispatch_attempt_already_processing',
                        },
                    ],

                    'attempt_number' =>
                        (int) (
                            $blocking[
                                'attempt_number'
                            ]
                            ?? 0
                        ),

                    'attempt_status_code' =>
                        (string) (
                            $blocking[
                                'status_code'
                            ]
                            ?? ''
                        ),
                ];
            }

            $attemptNumber =
                $this->nextAttemptNumber(
                    $pdo,
                    (int) $dispatch['id']
                );

            $insert =
                $pdo->prepare("
                    INSERT INTO correspondence_dispatch_attempts (
                        dispatch_id,
                        attempt_number,

                        provider_code,
                        provider_reference,

                        status_code,

                        destination_snapshot_json,
                        response_metadata_json,

                        requested_at,
                        completed_at,

                        failure_code,
                        failure_message,

                        created_at
                    )
                    VALUES (
                        ?,
                        ?,

                        ?,
                        NULL,

                        'processing',

                        ?,
                        NULL,

                        CURRENT_TIMESTAMP,
                        NULL,

                        NULL,
                        NULL,

                        CURRENT_TIMESTAMP
                    )
                ");

            $insert->execute([
                (int) $dispatch['id'],
                $attemptNumber,
                $providerCode,
                (string) (
                    $dispatch[
                        'destination_snapshot_json'
                    ]
                    ?? '{}'
                ),
            ]);

            if ($insert->rowCount() !== 1) {
                throw new RuntimeException(
                    'dispatch_attempt_insert_failed'
                );
            }

            $attemptId =
                (int) $pdo
                    ->lastInsertId();

            $pdo->commit();

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->failure(
                $this->knownFailureCode(
                    $exception,
                    'dispatch_attempt_begin_failed'
                )
            );
        }


        /*
         * -------------------------------------------------
         * Phase 2:
         * Transport call OUTSIDE database transaction.
         * -------------------------------------------------
         */

        try {
            $transportResult =
                $transport->send(
                    $this->transportPayload(
                        $dispatch,
                        $attemptNumber
                    )
                );

            $normalized =
                $this->normalizeTransportResult(
                    $transportResult
                );

        } catch (Throwable $exception) {
            /*
             * Exception does NOT prove provider rejection.
             *
             * Timeout / connection loss can happen after
             * remote acceptance, therefore outcome is
             * conservatively UNCERTAIN.
             */
            $normalized = [
                'outcome' =>
                    'uncertain',

                'provider_reference' =>
                    null,

                'failure_code' =>
                    $this->safeCode(
                        $exception->getMessage(),
                        'dispatch_transport_exception'
                    ),

                'failure_message' =>
                    null,

                'response_metadata' => [
                    'exception_class' =>
                        get_class(
                            $exception
                        ),
                ],
            ];
        }


        /*
         * -------------------------------------------------
         * Phase 3:
         * Complete the ATTEMPT only.
         *
         * Dispatch/correspondence completion is D5.
         * -------------------------------------------------
         */

        try {
            $pdo->beginTransaction();

            $lock =
                $pdo->prepare("
                    SELECT
                        id,
                        status_code

                    FROM correspondence_dispatch_attempts

                    WHERE
                        id = ?
                        AND dispatch_id = ?
                        AND attempt_number = ?

                    LIMIT 1
                    FOR UPDATE
                ");

            $lock->execute([
                $attemptId,
                (int) $dispatch['id'],
                $attemptNumber,
            ]);

            $attempt =
                $lock->fetch(
                    PDO::FETCH_ASSOC
                );

            if (
                !is_array($attempt)
                ||
                (
                    $attempt[
                        'status_code'
                    ]
                    ?? ''
                ) !== 'processing'
            ) {
                throw new RuntimeException(
                    'dispatch_attempt_state_conflict'
                );
            }

            $update =
                $pdo->prepare("
                    UPDATE correspondence_dispatch_attempts

                    SET
                        provider_reference = ?,
                        status_code = ?,
                        response_metadata_json = ?,
                        completed_at = CURRENT_TIMESTAMP,
                        failure_code = ?,
                        failure_message = ?

                    WHERE
                        id = ?
                        AND status_code = 'processing'
                ");

            $update->execute([
                $normalized[
                    'provider_reference'
                ],

                $normalized[
                    'outcome'
                ],

                $this->json(
                    $normalized[
                        'response_metadata'
                    ]
                ),

                $normalized[
                    'failure_code'
                ],

                $normalized[
                    'failure_message'
                ],

                $attemptId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'dispatch_attempt_completion_conflict'
                );
            }

            $pdo->commit();

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,

                'errors' => [
                    $this->knownFailureCode(
                        $exception,
                        'dispatch_attempt_completion_failed'
                    ),
                ],

                'attempt_number' =>
                    $attemptNumber,

                /*
                 * Transport may already have run.
                 * Never claim it did not.
                 */
                'transport_invoked' =>
                    true,

                'transport_outcome' =>
                    $normalized[
                        'outcome'
                    ],
            ];
        }

        return [
            'ok' =>
                $normalized[
                    'outcome'
                ] === 'succeeded',

            'attempt_number' =>
                $attemptNumber,

            'attempt_status_code' =>
                $normalized[
                    'outcome'
                ],

            'provider_code' =>
                $providerCode,

            'provider_reference' =>
                $normalized[
                    'provider_reference'
                ],

            'failure_code' =>
                $normalized[
                    'failure_code'
                ],

            'transport_invoked' =>
                true,

            'dispatch_status_changed' =>
                false,

            'correspondence_status_changed' =>
                false,
        ];
    }

    private function dispatchForUpdate(
        PDO $pdo,
        string $publicReference
    ): ?array {
        $statement =
            $pdo->prepare("
                SELECT
                    dispatches.*,

                    correspondences.public_reference
                        AS correspondence_public_reference,

                    correspondences.root_organization_id
                        AS correspondence_root_organization_id,

                    correspondences.organization_id
                        AS correspondence_organization_id,

                    correspondences.direction_code
                        AS correspondence_direction_code,

                    correspondences.status_code
                        AS correspondence_status_code,

                    correspondences.channel_code
                        AS correspondence_channel_code,

                    correspondences.dispatched_at
                        AS correspondence_dispatched_at

                FROM correspondence_dispatches
                    AS dispatches

                INNER JOIN correspondences
                    ON correspondences.id =
                       dispatches.correspondence_id

                WHERE dispatches.public_reference = ?

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

    private function blockingAttempt(
        PDO $pdo,
        int $dispatchId
    ): ?array {
        $statement =
            $pdo->prepare("
                SELECT
                    id,
                    attempt_number,
                    status_code

                FROM correspondence_dispatch_attempts

                WHERE
                    dispatch_id = ?
                    AND status_code IN (
                        'processing',
                        'uncertain',
                        'succeeded'
                    )

                ORDER BY
                    attempt_number DESC,
                    id DESC

                LIMIT 1
                FOR UPDATE
            ");

        $statement->execute([
            $dispatchId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function nextAttemptNumber(
        PDO $pdo,
        int $dispatchId
    ): int {
        $statement =
            $pdo->prepare("
                SELECT
                    COALESCE(
                        MAX(attempt_number),
                        0
                    )

                FROM correspondence_dispatch_attempts

                WHERE dispatch_id = ?
            ");

        $statement->execute([
            $dispatchId,
        ]);

        return
            max(
                1,
                (int) $statement
                    ->fetchColumn()
                + 1
            );
    }

    private function transportPayload(
        array $dispatch,
        int $attemptNumber
    ): array {
        return [
            'dispatch_id' =>
                (int) $dispatch['id'],

            'dispatch_public_reference' =>
                (string) (
                    $dispatch[
                        'public_reference'
                    ]
                    ?? ''
                ),

            'correspondence_id' =>
                (int) (
                    $dispatch[
                        'correspondence_id'
                    ]
                    ?? 0
                ),

            'correspondence_public_reference' =>
                (string) (
                    $dispatch[
                        'correspondence_public_reference'
                    ]
                    ?? ''
                ),

            'correspondence_party_id' =>
                $this->nullableInt(
                    $dispatch[
                        'correspondence_party_id'
                    ]
                    ?? null
                ),

            'channel_code' =>
                (string) (
                    $dispatch[
                        'channel_code'
                    ]
                    ?? ''
                ),

            'attempt_number' =>
                $attemptNumber,

            'target_snapshot' =>
                $this->decodeJson(
                    $dispatch[
                        'target_snapshot_json'
                    ]
                    ?? null
                ),

            'source_snapshot' =>
                $this->decodeJson(
                    $dispatch[
                        'source_snapshot_json'
                    ]
                    ?? null
                ),

            'destination_snapshot' =>
                $this->decodeJson(
                    $dispatch[
                        'destination_snapshot_json'
                    ]
                    ?? null
                ),
        ];
    }

    private function normalizeTransportResult(
        array $result
    ): array {
        $outcome =
            strtolower(
                trim(
                    (string) (
                        $result[
                            'outcome'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            !in_array(
                $outcome,
                self::ATTEMPT_OUTCOMES,
                true
            )
        ) {
            throw new RuntimeException(
                'dispatch_transport_result_invalid'
            );
        }

        $providerReference =
            $this->nullableText(
                $result[
                    'provider_reference'
                ]
                ?? null,
                190
            );

        $failureCode =
            $this->nullableCode(
                $result[
                    'failure_code'
                ]
                ?? null
            );

        $failureMessage =
            $this->nullableText(
                $result[
                    'failure_message'
                ]
                ?? null,
                1000
            );

        $metadata =
            is_array(
                $result[
                    'response_metadata'
                ]
                ?? null
            )
                ? $result[
                    'response_metadata'
                ]
                : [];

        if ($outcome === 'succeeded') {
            $failureCode = null;
            $failureMessage = null;
        }

        if (
            $outcome !== 'succeeded'
            &&
            $failureCode === null
        ) {
            $failureCode =
                $outcome === 'uncertain'
                    ? 'dispatch_transport_uncertain'
                    : 'dispatch_transport_failed';
        }

        return [
            'outcome' =>
                $outcome,

            'provider_reference' =>
                $providerReference,

            'failure_code' =>
                $failureCode,

            'failure_message' =>
                $failureMessage,

            'response_metadata' =>
                $metadata,
        ];
    }

    private function decodeJson(
        mixed $value
    ): array {
        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return [];
        }

        try {
            $decoded =
                json_decode(
                    $value,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            return
                is_array($decoded)
                    ? $decoded
                    : [];

        } catch (Throwable) {
            return [];
        }
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

    private function nullableCode(
        mixed $value
    ): ?string {
        $value =
            strtolower(
                trim(
                    (string) (
                        $value
                        ?? ''
                    )
                )
            );

        if (
            $value === ''
            ||
            preg_match(
                '/^[a-z][a-z0-9._-]{1,99}$/',
                $value
            ) !== 1
        ) {
            return null;
        }

        return $value;
    }

    private function safeCode(
        mixed $value,
        string $fallback
    ): string {
        return
            $this->nullableCode(
                $value
            )
            ?? $fallback;
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

    private function knownFailureCode(
        Throwable $exception,
        string $fallback
    ): string {
        return
            $this->safeCode(
                $exception->getMessage(),
                $fallback
            );
    }

    private function failure(
        string $code
    ): array {
        return [
            'ok' => false,

            'errors' => [
                $code,
            ],

            'transport_invoked' =>
                false,
        ];
    }
}
