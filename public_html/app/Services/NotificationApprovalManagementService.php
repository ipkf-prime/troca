<?php

namespace App\Services;

use App\Repositories\NotificationApprovalRepository;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use IPKF\Support\PersianDate;
use IPKF\Support\Session;
use Throwable;

class NotificationApprovalManagementService extends BaseService
{
    private const VIEW_PERMISSION =
        'notifications.approvals.view';

    private const DECIDE_PERMISSION =
        'notifications.approvals.decide';

    private const MANAGE_PERMISSION =
        'notifications.approvals.manage';

    public function __construct(
        private ?NotificationApprovalRepository $repository = null,
        private ?NotificationApprovalStateMachine $stateMachine = null,
        private ?AuthorizationService $authorization = null,
        private ?NotificationApprovalDispatchService $dispatch = null,
        private ?NotificationApprovalAlertService $alerts = null
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

        $this->alerts ??=
            new NotificationApprovalAlertService();
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

        $requestIds = array_values(
            array_filter(
                array_map(
                    static fn (
                        array $row
                    ): int =>
                        (int) (
                            $row['id'] ?? 0
                        ),
                    $rows
                ),
                static fn (
                    int $requestId
                ): bool =>
                    $requestId > 0
            )
        );

        $targetSummaries =
            $this->repository
                ->targetSummaries(
                    $requestIds
                );

        return array_map(
            function (
                array $row
            ) use (
                $targetSummaries
            ): array {
                $status = (string) (
                    $row['status_code']
                    ?? ''
                );

                $requestId = (int) (
                    $row['id'] ?? 0
                );

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

                    'target_summary' =>
                        $targetSummaries[
                            $requestId
                        ]
                        ?? $this
                            ->emptyTargetSummary(),
                ];
            },
            $rows
        );
    }

    public function history(
        int $actorUserId,
        array $input = []
    ): array {
        $this->assertPermission(
            $actorUserId,
            self::VIEW_PERMISSION,
            'notification_approval_view_forbidden'
        );

        $filters =
            $this->historyFilters($input);

        $page =
            $this->repository->historyPage(
                $filters
            );

        $items = is_array(
            $page['items'] ?? null
        ) ? $page['items'] : [];

        $requestIds = array_values(
            array_filter(
                array_map(
                    static fn (
                        array $item
                    ): int =>
                        (int) (
                            $item['id'] ?? 0
                        ),
                    $items
                ),
                static fn (
                    int $requestId
                ): bool =>
                    $requestId > 0
            )
        );

        $targetSummaries =
            $this->repository
                ->targetSummaries(
                    $requestIds
                );

        foreach ($items as &$item) {
            $requestId = (int) (
                $item['id'] ?? 0
            );

            $item['decision_label'] =
                match (
                    (string) (
                        $item[
                            'decision_code'
                        ] ?? ''
                    )
                ) {
                    'approve' => 'تأیید',
                    'reject' => 'رد',
                    default => '—',
                };

            $item['status_label'] =
                $this->stateMachine->label(
                    (string) (
                        $item[
                            'status_code'
                        ] ?? ''
                    )
                );

            $dispatchStatus =
                (string) (
                    $item[
                        'dispatch_status_code'
                    ] ?? ''
                );

            $item['dispatch_status_label'] =
                $dispatchStatus !== ''
                    ? $this->stateMachine->label(
                        $dispatchStatus
                    )
                    : '';

            $item['channels'] =
                $this->decodeChannels(
                    $item[
                        'channels_json'
                    ] ?? null
                );

            $item['target_summary'] =
                $targetSummaries[
                    $requestId
                ]
                ?? $this->emptyTargetSummary();

            $actorAudit =
                $this->decodeActorSnapshot(
                    $item[
                        'actor_snapshot_json'
                    ] ?? null
                );

            $item['actor_assignment_id'] =
                (int) (
                    $actorAudit[
                        'assignment_id'
                    ] ?? 0
                );

            $item['actor_role_code'] =
                trim(
                    (string) (
                        $actorAudit[
                            'role'
                        ]['code']
                        ?? ''
                    )
                );

            $item['actor_role_title'] =
                trim(
                    (string) (
                        $actorAudit[
                            'role'
                        ]['title']
                        ?? ''
                    )
                );

            $actorScope = is_array(
                $actorAudit['scope']
                ?? null
            )
                ? $actorAudit['scope']
                : [];

            $item['actor_scope_type'] =
                trim(
                    (string) (
                        $actorScope[
                            'type'
                        ] ?? ''
                    )
                );

            $item['actor_scope_label'] =
                $this->actorScopeLabel(
                    $actorScope
                );

            unset(
                $item['channels_json'],
                $item['actor_snapshot_json']
            );
        }
        unset($item);

        $page['items'] = $items;
        $page['filters'] = $filters;
        $page['summary'] =
            $this->repository
                ->approvalSummary();

        return $page;
    }

    public function targetPage(
        int $actorUserId,
        string $publicReference,
        array $input = []
    ): array {
        $this->assertPermission(
            $actorUserId,
            self::VIEW_PERMISSION,
            'notification_approval_view_forbidden'
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

        $request =
            $this->repository
                ->findByReference(
                    $publicReference
                );

        if (!is_array($request)) {
            throw new RuntimeException(
                'notification_approval_request_not_found'
            );
        }

        $requestId = (int) (
            $request['id'] ?? 0
        );

        if ($requestId < 1) {
            throw new RuntimeException(
                'notification_approval_request_not_found'
            );
        }

        $filters =
            $this->targetFilters(
                $input
            );

        $page =
            $this->repository
                ->targetPage(
                    $requestId,
                    $filters
                );

        $items = is_array(
            $page['items'] ?? null
        ) ? $page['items'] : [];

        foreach ($items as &$item) {
            $channelCode = trim(
                (string) (
                    $item[
                        'channel_code'
                    ] ?? ''
                )
            );

            $statusCode = trim(
                (string) (
                    $item[
                        'status_code'
                    ] ?? ''
                )
            );

            $item['channel_label'] =
                $this->targetChannelLabel(
                    $channelCode
                );

            $item['status_label'] =
                $this->targetStatusLabel(
                    $statusCode
                );

            $item['provider_title'] =
                trim(
                    (string) (
                        $item[
                            'provider_title_snapshot'
                        ] ?? ''
                    )
                );

            unset(
                $item[
                    'provider_title_snapshot'
                ]
            );
        }
        unset($item);

        $summaryMap =
            $this->repository
                ->targetSummaries([
                    $requestId,
                ]);

        $page['items'] = $items;

        $page['request'] = [
            'public_reference' =>
                $publicReference,

            'status_code' =>
                (string) (
                    $request[
                        'status_code'
                    ] ?? ''
                ),

            'status_label' =>
                $this->stateMachine->label(
                    (string) (
                        $request[
                            'status_code'
                        ] ?? ''
                    )
                ),

            'subject' =>
                (string) (
                    $request[
                        'subject'
                    ] ?? ''
                ),
        ];

        $page['filters'] = $filters;

        $page['summary'] =
            $summaryMap[$requestId]
            ?? $this->emptyTargetSummary();

        return $page;
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

        $this->alerts->resolve(
            $publicReference
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

        $decision = $this->decide(
            $actorUserId,
            $publicReference,
            'reject',
            NotificationApprovalStateMachine::REJECTED,
            $reason
        );

        $this->alerts->resolve(
            $publicReference
        );

        return $decision;
    }

    public function retry(
        int $actorUserId,
        string $publicReference
    ): array {
        $this->assertPermission(
            $actorUserId,
            self::MANAGE_PERMISSION,
            'notification_approval_retry_forbidden'
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

        $request =
            $this->repository
                ->findByReference(
                    $publicReference
                );

        if (!is_array($request)) {
            throw new RuntimeException(
                'notification_approval_request_not_found'
            );
        }

        $status = (string) (
            $request['status_code']
            ?? ''
        );

        if (!in_array(
            $status,
            [
                NotificationApprovalStateMachine::FAILED,
                NotificationApprovalStateMachine::PARTIALLY_DISPATCHED,
            ],
            true
        )) {
            throw new RuntimeException(
                'notification_approval_retry_not_available'
            );
        }

        $dispatch =
            $this->dispatch->dispatch(
                $actorUserId,
                $publicReference
            );

        return [
            'public_reference' =>
                $publicReference,

            'retry_from_status' =>
                $status,

            'status_code' =>
                (string) (
                    $dispatch[
                        'status_code'
                    ] ?? ''
                ),

            'dispatch' =>
                $dispatch,
        ];
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

        $activeAssignmentId =
            (int) (
                Session::get(
                    'active_role_assignment_id'
                )
                ?? 0
            );

        if ($activeAssignmentId < 1) {
            throw new RuntimeException(
                'notification_approval_actor_assignment_invalid'
            );
        }

        return $this->repository->transaction(
            function (
                NotificationApprovalRepository $repository
            ) use (
                $actorUserId,
                $activeAssignmentId,
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

                $assignment =
                    $repository
                        ->actorAssignmentSnapshot(
                            $actorUserId,
                            $activeAssignmentId
                        );

                if (
                    !is_array($assignment)
                    || (
                        $assignment[
                            'assignment_id'
                        ] ?? 0
                    ) !== $activeAssignmentId
                    || (
                        $assignment[
                            'user_id'
                        ] ?? 0
                    ) !== $actorUserId
                ) {
                    throw new RuntimeException(
                        'notification_approval_actor_assignment_invalid'
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

                        'assignment_id' =>
                            $activeAssignmentId,

                        'role' => [
                            'id' =>
                                (int) (
                                    $assignment[
                                        'role_id'
                                    ] ?? 0
                                ),

                            'code' =>
                                (string) (
                                    $assignment[
                                        'role_code'
                                    ] ?? ''
                                ),

                            'title' =>
                                (string) (
                                    $assignment[
                                        'role_title'
                                    ] ?? ''
                                ),
                        ],

                        'scope' => [
                            'type' =>
                                (string) (
                                    $assignment[
                                        'scope_type'
                                    ] ?? ''
                                ),

                            'id' =>
                                $assignment[
                                    'scope_id'
                                ] ?? null,

                            'organization_id' =>
                                $assignment[
                                    'organization_id'
                                ] ?? null,

                            'include_children' =>
                                !empty(
                                    $assignment[
                                        'include_children'
                                    ]
                                ),

                            'province_id' =>
                                $assignment[
                                    'province_id'
                                ] ?? null,

                            'city_id' =>
                                $assignment[
                                    'city_id'
                                ] ?? null,

                            'county_id' =>
                                $assignment[
                                    'county_id'
                                ] ?? null,

                            'district_id' =>
                                $assignment[
                                    'district_id'
                                ] ?? null,

                            'village_id' =>
                                $assignment[
                                    'village_id'
                                ] ?? null,

                            'company_id' =>
                                $assignment[
                                    'company_id'
                                ] ?? null,

                            'center_id' =>
                                $assignment[
                                    'center_id'
                                ] ?? null,

                            'warehouse_id' =>
                                $assignment[
                                    'warehouse_id'
                                ] ?? null,

                            'fiscal_year_id' =>
                                $assignment[
                                    'fiscal_year_id'
                                ] ?? null,
                        ],

                        'assignment_state' => [
                            'is_active' =>
                                !empty(
                                    $assignment[
                                        'is_active'
                                    ]
                                ),

                            'starts_at' =>
                                $assignment[
                                    'starts_at'
                                ] ?? null,

                            'ends_at' =>
                                $assignment[
                                    'ends_at'
                                ] ?? null,
                        ],

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

    private function targetFilters(
        array $input
    ): array {
        $query = trim(
            (string) (
                $input['q'] ?? ''
            )
        );

        if (
            mb_strlen(
                $query,
                'UTF-8'
            ) > 190
        ) {
            $query = mb_substr(
                $query,
                0,
                190,
                'UTF-8'
            );
        }

        $channel = strtolower(
            trim(
                (string) (
                    $input['channel'] ?? ''
                )
            )
        );

        if (!in_array(
            $channel,
            [
                '',
                'email',
                'sms',
                'messenger',
            ],
            true
        )) {
            $channel = '';
        }

        $status = strtolower(
            trim(
                (string) (
                    $input['status'] ?? ''
                )
            )
        );

        if (!in_array(
            $status,
            [
                '',
                'pending',
                'sent',
                'failed',
            ],
            true
        )) {
            $status = '';
        }

        $perPage = (int) (
            $input['per_page'] ?? 20
        );

        if (!in_array(
            $perPage,
            [20, 50, 100],
            true
        )) {
            $perPage = 20;
        }

        return [
            'q' => $query,
            'channel' => $channel,
            'status' => $status,
            'page' => max(
                1,
                (int) (
                    $input['page'] ?? 1
                )
            ),
            'per_page' => $perPage,
        ];
    }

    private function targetChannelLabel(
        string $channel
    ): string {
        return match ($channel) {
            'email' => 'ایمیل',
            'sms' => 'پیام کوتاه',
            'messenger' => 'پیام‌رسان بله',

            default =>
                $channel !== ''
                    ? $channel
                    : '—',
        };
    }

    private function targetStatusLabel(
        string $status
    ): string {
        return match ($status) {
            'pending' => 'در انتظار',
            'sent' => 'ارسال‌شده',
            'failed' => 'ناموفق',

            default =>
                $status !== ''
                    ? $status
                    : '—',
        };
    }

    private function emptyTargetSummary(): array
    {
        return [
            'total' => 0,
            'channels' => [],
            'statuses' => [],
        ];
    }

    private function historyFilters(
        array $input
    ): array {
        $query = trim(
            (string) ($input['q'] ?? '')
        );

        if (
            mb_strlen(
                $query,
                'UTF-8'
            ) > 190
        ) {
            $query = mb_substr(
                $query,
                0,
                190,
                'UTF-8'
            );
        }

        $decision = strtolower(
            trim(
                (string) (
                    $input['status'] ?? ''
                )
            )
        );

        if (!in_array(
            $decision,
            ['', 'approve', 'reject'],
            true
        )) {
            $decision = '';
        }

        [$fromInput, $from] =
            $this->historyDateFilter(
                $input['from'] ?? ''
            );

        [$toInput, $to] =
            $this->historyDateFilter(
                $input['to'] ?? ''
            );

        if (
            $from !== ''
            && $to !== ''
            && strcmp($from, $to) > 0
        ) {
            [$from, $to] = [$to, $from];
            [$fromInput, $toInput] =
                [$toInput, $fromInput];
        }

        $perPage =
            (int) (
                $input['per_page'] ?? 20
            );

        if (!in_array(
            $perPage,
            [20, 50, 100],
            true
        )) {
            $perPage = 20;
        }

        return [
            'q' => $query,
            'decision' => $decision,
            'from' => $from,
            'to' => $to,
            'from_input' => $fromInput,
            'to_input' => $toInput,
            'page' => max(
                1,
                (int) (
                    $input['page'] ?? 1
                )
            ),
            'per_page' => $perPage,
        ];
    }

    private function historyDateFilter(
        mixed $value
    ): array {
        $input = trim(
            (string) $value
        );

        if ($input === '') {
            return ['', ''];
        }

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $input
            ) === 1
        ) {
            return [$input, $input];
        }

        try {
            $gregorian = (string) (
                PersianDate::toGregorianDate(
                    $input
                ) ?? ''
            );
        } catch (Throwable) {
            return [$input, ''];
        }

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $gregorian
            ) !== 1
        ) {
            return [$input, ''];
        }

        return [
            $input,
            $gregorian,
        ];
    }

    private function safeTargets(
        int $requestId
    ): array {
        return array_map(
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
                 * through approval management.
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

                'provider_title' =>
                    (string) (
                        $target[
                            'provider_title_snapshot'
                        ] ?? ''
                    ),
            ],
            $this->repository->targets(
                $requestId
            )
        );
    }

    private function decodeActorSnapshot(
        mixed $value
    ): array {
        if (!is_string($value)) {
            return [];
        }

        $value = trim($value);

        if ($value === '') {
            return [];
        }

        try {
            $snapshot = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return [];
        }

        return is_array($snapshot)
            ? $snapshot
            : [];
    }

    private function actorScopeLabel(
        array $scope
    ): string {
        $type = strtolower(trim(
            (string) (
                $scope['type'] ?? ''
            )
        ));

        return match ($type) {
            'global' =>
                'سراسری',

            'organization' =>
                'سازمان',

            'province' =>
                'استان',

            'county' =>
                'شهرستان',

            'city' =>
                'شهر',

            'district' =>
                'بخش',

            'village' =>
                'روستا',

            'company' =>
                'شرکت',

            'center' =>
                'مرکز',

            'warehouse' =>
                'انبار',

            default =>
                $type,
        };
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
