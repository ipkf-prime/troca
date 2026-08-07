<?php

namespace App\Services;

use App\Repositories\NotificationApprovalRepository;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class NotificationApprovalManagementService extends BaseService
{
    private const VIEW_PERMISSION =
        'notifications.approvals.view';

    private const DECIDE_PERMISSION =
        'notifications.approvals.decide';

    public function __construct(
        private ?NotificationApprovalRepository $repository = null,
        private ?NotificationApprovalStateMachine $stateMachine = null,
        private ?AuthorizationService $authorization = null,
        private ?NotificationApprovalDispatchService $dispatch = null
    ) {
        $this->repository ??=
            new NotificationApprovalRepository();

        $this->stateMachine ??=
            new NotificationApprovalStateMachine();

        $this->authorization ??=
            new AuthorizationService();

        $this->dispatch ??=
            new NotificationApprovalDispatchService(
                $this->repository,
                $this->stateMachine,
                $this->authorization
            );
    }

    public function queue(
        int $actorUserId,
        int $limit = 100
    ): array {
        $this->assertPermission(
            $actorUserId,
            self::VIEW_PERMISSION,
            'notification_approval_view_forbidden'
        );

        $rows = $this->repository->queue(
            $limit
        );

        return array_map(
            function (array $row): array {
                $status = (string) (
                    $row['status_code']
                    ?? ''
                );

                $requestId = (int) (
                    $row['id'] ?? 0
                );

                $targets = [];

                if ($requestId > 0) {
                    $targets = array_map(
                        static fn (
                            array $target
                        ): array => [
                            'public_reference' =>
                                (string) (
                                    $target[
                                        'public_reference'
                                    ] ?? ''
                                ),

                            'recipient_title' =>
                                (string) (
                                    $target[
                                        'recipient_title'
                                    ] ?? ''
                                ),

                            'channel_code' =>
                                (string) (
                                    $target[
                                        'channel_code'
                                    ] ?? ''
                                ),

                            /*
                             * Never expose destination_snapshot
                             * through the manager queue model.
                             */
                            'destination_masked' =>
                                (string) (
                                    $target[
                                        'destination_masked'
                                    ] ?? ''
                                ),

                            'status_code' =>
                                (string) (
                                    $target[
                                        'status_code'
                                    ] ?? ''
                                ),
                        ],
                        $this->repository->targets(
                            $requestId
                        )
                    );
                }

                return $row + [
                    'status_label' =>
                        $this->stateMachine->label(
                            $status
                        ),

                    'channels' =>
                        $this->decodeChannels(
                            $row[
                                'channels_json'
                            ] ?? null
                        ),

                    'targets' =>
                        $targets,
                ];
            },
            $rows
        );
    }

    public function approve(
        int $actorUserId,
        string $publicReference,
        ?string $reason = null
    ): array {
        /*
         * Decision and dispatch intentionally use separate
         * transactions. The approval decision is durable before
         * any provider call starts.
         */
        $decision = $this->decide(
            $actorUserId,
            $publicReference,
            'approve',
            NotificationApprovalStateMachine::APPROVED,
            $reason
        );

        $dispatch = $this->dispatch->dispatch(
            $actorUserId,
            $publicReference
        );

        return [
            'public_reference' =>
                $publicReference,

            'decision_code' =>
                'approve',

            'approval_status' =>
                NotificationApprovalStateMachine::APPROVED,

            'status_code' =>
                (string) (
                    $dispatch[
                        'status_code'
                    ] ?? ''
                ),

            'decision' =>
                $decision,

            'dispatch' =>
                $dispatch,
        ];
    }

    public function reject(
        int $actorUserId,
        string $publicReference,
        ?string $reason
    ): array {
        $reason = trim(
            (string) $reason
        );

        if ($reason === '') {
            throw new InvalidArgumentException(
                'notification_approval_reject_reason_required'
            );
        }

        return $this->decide(
            $actorUserId,
            $publicReference,
            'reject',
            NotificationApprovalStateMachine::REJECTED,
            $reason
        );
    }

    private function decide(
        int $actorUserId,
        string $publicReference,
        string $decisionCode,
        string $toStatus,
        ?string $reason
    ): array {
        $this->assertPermission(
            $actorUserId,
            self::DECIDE_PERMISSION,
            'notification_approval_decide_forbidden'
        );

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
            throw new InvalidArgumentException(
                'notification_approval_reference_invalid'
            );
        }

        $reason = trim(
            (string) $reason
        );

        if (
            mb_strlen(
                $reason,
                'UTF-8'
            ) > 2000
        ) {
            throw new InvalidArgumentException(
                'notification_approval_decision_reason_invalid'
            );
        }

        return $this->repository->transaction(
            function (
                NotificationApprovalRepository $repository
            ) use (
                $actorUserId,
                $publicReference,
                $decisionCode,
                $toStatus,
                $reason
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
                    $request['status_code']
                    ?? ''
                );

                $this->stateMachine
                    ->assertTransition(
                        $fromStatus,
                        $toStatus
                    );

                $stepOrder = (int) (
                    $request[
                        'current_step_order'
                    ] ?? 0
                );

                if ($stepOrder < 1) {
                    throw new RuntimeException(
                        'notification_approval_active_step_missing'
                    );
                }

                $step =
                    $repository->lockActiveStep(
                        (int) $request['id'],
                        $stepOrder
                    );

                if (!is_array($step)) {
                    throw new RuntimeException(
                        'notification_approval_active_step_missing'
                    );
                }

                $permissionCode =
                    $this->approverPermission(
                        $step
                    );

                if (
                    !$this->authorization
                        ->hasPermission(
                            $actorUserId,
                            $permissionCode
                        )
                ) {
                    throw new RuntimeException(
                        'notification_approval_approver_ineligible'
                    );
                }

                return $repository->recordDecision(
                    $request,
                    $step,
                    $actorUserId,
                    $decisionCode,
                    $reason !== ''
                        ? $reason
                        : null,
                    [
                        'user_id' =>
                            $actorUserId,
                        'permission_code' =>
                            $permissionCode,
                        'decision_code' =>
                            $decisionCode,
                    ],
                    $toStatus
                );
            }
        );
    }

    private function approverPermission(
        array $step
    ): string {
        $raw = trim(
            (string) (
                $step[
                    'approver_rule_json'
                ] ?? ''
            )
        );

        if ($raw === '') {
            throw new RuntimeException(
                'notification_approval_approver_rule_invalid'
            );
        }

        try {
            $rule = json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (
            JsonException $exception
        ) {
            throw new RuntimeException(
                'notification_approval_approver_rule_invalid',
                0,
                $exception
            );
        }

        if (
            !is_array($rule)
            || ($rule['type'] ?? null)
                !== 'permission'
        ) {
            throw new RuntimeException(
                'notification_approval_approver_rule_invalid'
            );
        }

        $permissionCode = trim(
            (string) (
                $rule[
                    'permission_code'
                ] ?? ''
            )
        );

        if ($permissionCode === '') {
            throw new RuntimeException(
                'notification_approval_approver_rule_invalid'
            );
        }

        return $permissionCode;
    }

    private function assertPermission(
        int $actorUserId,
        string $permissionCode,
        string $errorCode
    ): void {
        if (
            $actorUserId < 1
            || !$this->authorization
                ->hasPermission(
                    $actorUserId,
                    $permissionCode
                )
        ) {
            throw new RuntimeException(
                $errorCode
            );
        }
    }

    private function decodeChannels(
        mixed $value
    ): array {
        if (!is_string($value)) {
            return [];
        }

        try {
            $channels = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return [];
        }

        if (!is_array($channels)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (
                        mixed $channel
                    ): string =>
                        strtolower(trim(
                            (string) $channel
                        )),
                    $channels
                ),
                static fn (
                    string $channel
                ): bool =>
                    $channel !== ''
            )
        );
    }
}
