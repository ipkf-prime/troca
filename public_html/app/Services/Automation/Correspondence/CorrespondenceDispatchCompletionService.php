<?php

namespace App\Services\Automation\Correspondence;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Completes exactly one recipient-level dispatch after a
 * positively successful transport attempt.
 *
 * This service deliberately does NOT aggregate the parent
 * correspondence. Correspondence-wide completion is a
 * separate lifecycle step.
 */
final class CorrespondenceDispatchCompletionService
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

    /**
     * Complete one dispatch from its latest successful attempt.
     *
     * Effects:
     * - correspondence_dispatches.status_code => dispatched
     * - provider_reference copied from successful attempt
     * - dispatched_at copied from attempt completion time
     *
     * No correspondence mutation occurs here.
     * No provider is called here.
     * No follow-up is created here.
     */
    public function completeSuccess(
        string $dispatchPublicReference,
        int $userId
    ): array {
        $dispatchPublicReference =
            trim(
                $dispatchPublicReference
            );

        if (
            $dispatchPublicReference === ''
            ||
            $userId < 1
        ) {
            return $this->failure(
                'invalid_dispatch_completion_request'
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


            /*
             * Idempotent terminal success.
             *
             * `delivered` is also considered already
             * completed because it is beyond dispatched.
             */
            $dispatchStatus =
                (string) (
                    $dispatch[
                        'status_code'
                    ]
                    ?? ''
                );

            if (
                in_array(
                    $dispatchStatus,
                    [
                        'dispatched',
                        'delivered',
                    ],
                    true
                )
            ) {
                $pdo->rollBack();

                return [
                    'ok' =>
                        true,

                    'already_completed' =>
                        true,

                    'dispatch_status_code' =>
                        $dispatchStatus,

                    'provider_reference' =>
                        $dispatch[
                            'provider_reference'
                        ]
                        ?? null,

                    'dispatched_at' =>
                        $dispatch[
                            'dispatched_at'
                        ]
                        ?? null,

                    'dispatch_status_changed' =>
                        false,

                    'correspondence_status_changed' =>
                        false,
                ];
            }


            if ($dispatchStatus !== 'pending') {
                $pdo->rollBack();

                return $this->failure(
                    'dispatch_not_success_completable'
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
                    'dispatch_completion_requires_outgoing'
                );
            }


            /*
             * Until D5C aggregate transition exists, the
             * correspondence itself must still be in its
             * registered pre-dispatch state.
             */
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
                    'correspondence_not_completion_ready'
                );
            }


            $attempt =
                $this->latestAttemptForUpdate(
                    $pdo,
                    (int) $dispatch['id']
                );

            if ($attempt === null) {
                $pdo->rollBack();

                return $this->failure(
                    'successful_dispatch_attempt_required'
                );
            }


            $attemptStatus =
                (string) (
                    $attempt[
                        'status_code'
                    ]
                    ?? ''
                );


            if ($attemptStatus !== 'succeeded') {
                $pdo->rollBack();

                $error =
                    match ($attemptStatus) {
                        'processing' =>
                            'dispatch_attempt_still_processing',

                        'uncertain' =>
                            'dispatch_attempt_uncertain_requires_review',

                        'failed' =>
                            'successful_dispatch_attempt_required',

                        default =>
                            'successful_dispatch_attempt_required',
                    };

                return $this->failure(
                    $error
                );
            }


            $completedAt =
                trim(
                    (string) (
                        $attempt[
                            'completed_at'
                        ]
                        ?? ''
                    )
                );

            if ($completedAt === '') {
                $pdo->rollBack();

                return $this->failure(
                    'successful_dispatch_attempt_incomplete'
                );
            }


            $attemptProviderReference =
                $this->nullableText(
                    $attempt[
                        'provider_reference'
                    ]
                    ?? null,
                    190
                );


            $existingProviderReference =
                $this->nullableText(
                    $dispatch[
                        'provider_reference'
                    ]
                    ?? null,
                    190
                );


            if (
                $existingProviderReference !== null
                &&
                $attemptProviderReference !== null
                &&
                $existingProviderReference
                    !== $attemptProviderReference
            ) {
                $pdo->rollBack();

                return $this->failure(
                    'dispatch_provider_reference_conflict'
                );
            }


            $providerReference =
                $attemptProviderReference
                ?? $existingProviderReference;


            $update =
                $pdo->prepare("
                    UPDATE correspondence_dispatches

                    SET
                        status_code =
                            'dispatched',

                        provider_reference = ?,

                        dispatched_at = ?,

                        failed_at =
                            NULL,

                        failure_code =
                            NULL,

                        failure_message =
                            NULL,

                        updated_at =
                            CURRENT_TIMESTAMP

                    WHERE
                        id = ?
                        AND status_code =
                            'pending'
                ");

            $update->execute([
                $providerReference,
                $completedAt,
                (int) $dispatch['id'],
            ]);


            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'dispatch_success_completion_conflict'
                );
            }


            $pdo->commit();


            return [
                'ok' =>
                    true,

                'already_completed' =>
                    false,

                'dispatch_public_reference' =>
                    (string) (
                        $dispatch[
                            'public_reference'
                        ]
                        ?? ''
                    ),

                'attempt_number' =>
                    (int) (
                        $attempt[
                            'attempt_number'
                        ]
                        ?? 0
                    ),

                'attempt_status_code' =>
                    'succeeded',

                'dispatch_status_code' =>
                    'dispatched',

                'provider_reference' =>
                    $providerReference,

                'dispatched_at' =>
                    $completedAt,

                'dispatch_status_changed' =>
                    true,

                'correspondence_status_changed' =>
                    false,

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
                    'dispatch_success_completion_failed'
                )
            );
        }
    }


    private function dispatchForUpdate(
        PDO $pdo,
        string $publicReference
    ): ?array {
        $statement =
            $pdo->prepare("
                SELECT
                    dispatches.*,

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


    private function latestAttemptForUpdate(
        PDO $pdo,
        int $dispatchId
    ): ?array {
        $statement =
            $pdo->prepare("
                SELECT
                    id,
                    dispatch_id,
                    attempt_number,
                    provider_code,
                    provider_reference,
                    status_code,
                    requested_at,
                    completed_at,
                    failure_code,
                    failure_message

                FROM correspondence_dispatch_attempts

                WHERE dispatch_id = ?

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

            'dispatch_status_changed' =>
                false,

            'correspondence_status_changed' =>
                false,

            'provider_invoked' =>
                false,
        ];
    }
}
