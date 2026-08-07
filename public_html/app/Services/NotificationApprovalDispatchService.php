<?php

namespace App\Services;

use App\Repositories\NotificationApprovalRepository;
use RuntimeException;
use Throwable;

class NotificationApprovalDispatchService extends BaseService
{
    private const DECIDE_PERMISSION =
        'notifications.approvals.decide';

    private const MANAGE_PERMISSION =
        'notifications.approvals.manage';

    public function __construct(
        private ?NotificationApprovalRepository $repository = null,
        private ?NotificationApprovalStateMachine $stateMachine = null,
        private ?AuthorizationService $authorization = null,
        private ?NotificationGatewayService $gateway = null
    ) {
        $this->repository ??=
            new NotificationApprovalRepository();

        $this->stateMachine ??=
            new NotificationApprovalStateMachine();

        $this->authorization ??=
            new AuthorizationService();

        $this->gateway ??=
            new NotificationGatewayService();
    }

    public function dispatch(
        int $actorUserId,
        string $publicReference
    ): array {
        if ($actorUserId < 1) {
            throw new RuntimeException(
                'notification_approval_dispatch_forbidden'
            );
        }

        $publicReference = trim(
            $publicReference
        );

        if (
            $publicReference === ''
            || strlen($publicReference) > 40
            || preg_match(
                '/^nar_[a-f0-9]{24}$/',
                $publicReference
            ) !== 1
        ) {
            throw new RuntimeException(
                'notification_approval_reference_invalid'
            );
        }

        $started =
            $this->repository->transaction(
                function (
                    NotificationApprovalRepository $repository
                ) use (
                    $actorUserId,
                    $publicReference
                ): array {
                    $request =
                        $repository->lockByReference(
                            $publicReference
                        );

                    if (!is_array($request)) {
                        throw new RuntimeException(
                            'notification_approval_request_not_found'
                        );
                    }

                    $fromStatus = (string) (
                        $request[
                            'status_code'
                        ] ?? ''
                    );

                    if (
                        !$this->stateMachine
                            ->isDispatchable(
                                $fromStatus
                            )
                    ) {
                        throw new RuntimeException(
                            'notification_approval_not_dispatchable'
                        );
                    }

                    $this->assertDispatchPermission(
                        $actorUserId,
                        $fromStatus
                    );

                    $this->stateMachine
                        ->assertTransition(
                            $fromStatus,
                            NotificationApprovalStateMachine::DISPATCHING
                        );

                    $run =
                        $repository->startDispatch(
                            $request,
                            $actorUserId,
                            $fromStatus
                        );

                    return [
                        'request' => $request,
                        'run' => $run,
                    ];
                }
            );

        $request = $started['request'];
        $run = $started['run'];

        $requestId = (int) (
            $request['id'] ?? 0
        );

        $runId = (int) (
            $run['run_id'] ?? 0
        );

        if (
            $requestId < 1
            || $runId < 1
        ) {
            throw new RuntimeException(
                'notification_approval_dispatch_invalid'
            );
        }

        /*
         * The approval actor starts and audits the dispatch run,
         * but the resulting notification delivery belongs to the
         * original requester/sender.
         */
        $requesterUserId = (int) (
            $request[
                'requester_user_id'
            ] ?? 0
        );

        if ($requesterUserId < 1) {
            throw new RuntimeException(
                'notification_approval_requester_invalid'
            );
        }

        $targets =
            $this->repository
                ->dispatchTargets(
                    $requestId
                );

        $mediaAssets =
            $this->repository
                ->mediaAssets(
                    $requestId
                );

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $items = [];
        $lastError = null;

        foreach ($targets as $target) {
            $targetId = (int) (
                $target['id'] ?? 0
            );

            if ($targetId < 1) {
                $skipped++;

                $items[] = [
                    'target_id' => 0,
                    'status_code' => 'skipped',
                    'error_code' =>
                        'notification_approval_target_invalid',
                ];

                continue;
            }

            try {
                $result =
                    $this->gateway->sendDirect(
                        $requesterUserId,
                        [
                            'channel_code' =>
                                (string) (
                                    $target[
                                        'channel_code'
                                    ] ?? ''
                                ),

                            'purpose_code' =>
                                (string) (
                                    $request[
                                        'purpose_code'
                                    ] ?? 'general'
                                ),

                            'scope_type' =>
                                (string) (
                                    $request[
                                        'requester_scope_type'
                                    ] ?? 'global'
                                ),

                            'scope_reference' =>
                                (string) (
                                    $request[
                                        'requester_scope_reference'
                                    ] ?? '*'
                                ),

                            'destination' =>
                                (string) (
                                    $target[
                                        'destination_snapshot'
                                    ] ?? ''
                                ),

                            'recipient_user_id' =>
                                (int) (
                                    $target[
                                        'recipient_user_id'
                                    ] ?? 0
                                ),

                            'recipient_user_reference' =>
                                (string) (
                                    $target[
                                        'recipient_user_reference'
                                    ] ?? ''
                                ),

                            'subject' =>
                                (string) (
                                    $request[
                                        'subject'
                                    ] ?? ''
                                ),

                            'body' =>
                                (string) (
                                    $request[
                                        'body'
                                    ] ?? ''
                                ),

                            'message_type_code' =>
                                (string) (
                                    $request[
                                        'message_type_code'
                                    ] ?? 'text'
                                ),

                            'media_assets' =>
                                $mediaAssets,
                        ]
                    );

                /*
                 * Provider call has already succeeded here.
                 * If persistence of the successful result fails,
                 * do NOT convert it to a provider failure. Throwing
                 * leaves the workflow in dispatching state instead
                 * of risking an automatic duplicate send.
                 */
                $this->repository
                    ->markTargetSent(
                        $targetId,
                        $result
                    );

                $sent++;

                $items[] = [
                    'target_id' =>
                        $targetId,

                    'target_reference' =>
                        (string) (
                            $target[
                                'public_reference'
                            ] ?? ''
                        ),

                    'channel_code' =>
                        (string) (
                            $target[
                                'channel_code'
                            ] ?? ''
                        ),

                    'recipient_title' =>
                        (string) (
                            $target[
                                'recipient_title'
                            ] ?? ''
                        ),

                    'destination_masked' =>
                        (string) (
                            $target[
                                'destination_masked'
                            ] ?? ''
                        ),

                    'status_code' =>
                        'sent',

                    'provider_title' =>
                        (string) (
                            $result[
                                'provider_title'
                            ] ?? ''
                        ),

                    'provider_type_code' =>
                        (string) (
                            $result[
                                'provider_type_code'
                            ] ?? ''
                        ),

                    'delivery_reference' =>
                        (string) (
                            $result[
                                'delivery_reference'
                            ] ?? ''
                        ),

                    'fallback_used' =>
                        !empty(
                            $result[
                                'fallback_used'
                            ]
                        ),

                    'error_code' => '',
                ];
            } catch (Throwable $exception) {
                $errorCode =
                    $this->gatewayErrorCode(
                        $exception
                    );

                /*
                 * A non-gateway failure after a successful provider
                 * call must escape, otherwise retry could duplicate
                 * a message already accepted by the provider.
                 */
                if (
                    !str_starts_with(
                        $errorCode,
                        'notification_gateway_'
                    )
                ) {
                    throw $exception;
                }

                $this->repository
                    ->markTargetFailed(
                        $targetId,
                        $errorCode
                    );

                $failed++;
                $lastError = $errorCode;

                $items[] = [
                    'target_id' =>
                        $targetId,

                    'target_reference' =>
                        (string) (
                            $target[
                                'public_reference'
                            ] ?? ''
                        ),

                    'channel_code' =>
                        (string) (
                            $target[
                                'channel_code'
                            ] ?? ''
                        ),

                    'recipient_title' =>
                        (string) (
                            $target[
                                'recipient_title'
                            ] ?? ''
                        ),

                    'destination_masked' =>
                        (string) (
                            $target[
                                'destination_masked'
                            ] ?? ''
                        ),

                    'status_code' =>
                        'failed',

                    'provider_title' => '',
                    'provider_type_code' => '',
                    'delivery_reference' => '',
                    'fallback_used' => false,
                    'error_code' =>
                        $errorCode,
                ];
            }
        }

        if (
            $sent > 0
            && $failed === 0
        ) {
            $toStatus =
                NotificationApprovalStateMachine::DISPATCHED;
        } elseif ($sent > 0) {
            $toStatus =
                NotificationApprovalStateMachine::PARTIALLY_DISPATCHED;
        } else {
            $toStatus =
                NotificationApprovalStateMachine::FAILED;

            $lastError ??=
                'notification_approval_dispatch_failed';
        }

        $summary = [
            'workflow_status' =>
                $toStatus,

            'approval_reference' =>
                $publicReference,

            'dispatch_run_reference' =>
                (string) (
                    $run[
                        'run_reference'
                    ] ?? ''
                ),

            'attempt_number' =>
                (int) (
                    $run[
                        'attempt_number'
                    ] ?? 1
                ),

            'total' =>
                count($targets),

            'sent' =>
                $sent,

            'failed' =>
                $failed,

            'skipped' =>
                $skipped,

            'items' =>
                $items,
        ];

        $finalized =
            $this->repository->transaction(
                function (
                    NotificationApprovalRepository $repository
                ) use (
                    $requestId,
                    $runId,
                    $actorUserId,
                    $toStatus,
                    $sent,
                    $failed,
                    $skipped,
                    $summary,
                    $lastError
                ): array {
                    $currentRequest =
                        $repository->lockByReference(
                            (string) (
                                $summary[
                                    'approval_reference'
                                ] ?? ''
                            )
                        );

                    if (
                        !is_array(
                            $currentRequest
                        )
                        || (int) (
                            $currentRequest[
                                'id'
                            ] ?? 0
                        ) !== $requestId
                    ) {
                        throw new RuntimeException(
                            'notification_approval_dispatch_request_conflict'
                        );
                    }

                    $currentStatus =
                        (string) (
                            $currentRequest[
                                'status_code'
                            ] ?? ''
                        );

                    $this->stateMachine
                        ->assertTransition(
                            $currentStatus,
                            $toStatus
                        );

                    $runRow =
                        $repository->lockDispatchRun(
                            $runId,
                            $requestId
                        );

                    if (!is_array($runRow)) {
                        throw new RuntimeException(
                            'notification_approval_dispatch_run_not_found'
                        );
                    }

                    if (
                        (string) (
                            $runRow[
                                'status_code'
                            ] ?? ''
                        ) !==
                        NotificationApprovalStateMachine::DISPATCHING
                    ) {
                        throw new RuntimeException(
                            'notification_approval_dispatch_run_conflict'
                        );
                    }

                    return $repository->finishDispatch(
                        $currentRequest,
                        $runRow,
                        $actorUserId,
                        $toStatus,
                        $sent,
                        $failed,
                        $skipped,
                        $summary,
                        $lastError
                    );
                }
            );

        return array_merge(
            $summary,
            [
                'status_code' =>
                    (string) (
                        $finalized[
                            'status_code'
                        ] ?? $toStatus
                    ),
            ]
        );
    }

    private function assertDispatchPermission(
        int $actorUserId,
        string $fromStatus
    ): void {
        $permission =
            $fromStatus ===
                NotificationApprovalStateMachine::APPROVED
                ? self::DECIDE_PERMISSION
                : self::MANAGE_PERMISSION;

        if (
            !$this->authorization
                ->hasPermission(
                    $actorUserId,
                    $permission
                )
        ) {
            throw new RuntimeException(
                'notification_approval_dispatch_forbidden'
            );
        }
    }

    private function gatewayErrorCode(
        Throwable $exception
    ): string {
        $code = trim(
            $exception->getMessage()
        );

        if ($code === '') {
            return
                'notification_gateway_provider_rejected';
        }

        return $code;
    }
}
