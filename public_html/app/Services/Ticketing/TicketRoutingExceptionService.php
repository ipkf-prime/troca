<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketRoutingExceptionRepository;
use App\Services\AuthorizationService;

final class TicketRoutingExceptionService
{
    private TicketRoutingExceptionRepository $repository;
    private AuthorizationService $authorization;

    public function __construct(
        ?TicketRoutingExceptionRepository $repository = null,
        ?AuthorizationService $authorization = null
    ) {
        $this->repository =
            $repository
            ?? new TicketRoutingExceptionRepository();

        $this->authorization =
            $authorization
            ?? new AuthorizationService();
    }

    public function panel(
        string $ticketPublicReference,
        int $viewerUserId
    ): array {
        $ticket =
            $this->repository->ticketByReference(
                $ticketPublicReference
            );

        if (!is_array($ticket)) {
            return [
                'found' => false,
                'ticket' => [],
                'classification' =>
                    $this->notFoundClassification(),
                'can_manage' => false,
                'selectable_topics' => [],
                'default_topic' => null,
            ];
        }

        $classification =
            $this->classify($ticket);

        $canManage =
            $viewerUserId > 0
            && $this->authorization->hasPermission(
                $viewerUserId,
                'ticketing.project.manage'
            );

        $topics = [];

        if (
            $canManage
            && !empty(
                $classification['actionable']
            )
        ) {
            $topics =
                $this->repository->selectableTopics(
                    (int) (
                        $ticket['support_project_id']
                        ?? 0
                    ),
                    isset(
                        $ticket['support_service_id']
                    )
                        ? (int) $ticket['support_service_id']
                        : null
                );
        }

        $defaultTopic = null;

        foreach ($topics as $topic) {
            if (
                (int) (
                    $topic['is_default']
                    ?? 0
                ) === 1
            ) {
                $defaultTopic = $topic;
                break;
            }
        }

        return [
            'found' => true,
            'ticket' => $ticket,
            'classification' => $classification,
            'can_manage' => $canManage,
            'selectable_topics' => $topics,
            'default_topic' => $defaultTopic,
        ];
    }

    public function listActionable(
        ?int $projectId = null,
        int $limit = 500
    ): array {
        $rows =
            $this->repository->activeTickets(
                $projectId,
                $limit
            );

        $result = [];

        foreach ($rows as $row) {
            $classification =
                $this->classify($row);

            if (empty($classification['actionable'])) {
                continue;
            }

            $result[] = [
                'ticket' => $row,
                'classification' => $classification,
            ];
        }

        return $result;
    }

    public function summary(
        ?int $projectId = null,
        int $limit = 2000
    ): array {
        $items =
            $this->listActionable(
                $projectId,
                $limit
            );

        $byCode = [];

        foreach ($items as $item) {
            $code =
                (string) (
                    $item['classification']['code']
                    ?? 'unknown'
                );

            if (!isset($byCode[$code])) {
                $byCode[$code] = 0;
            }

            $byCode[$code]++;
        }

        ksort($byCode);

        return [
            'total' => count($items),
            'by_code' => $byCode,
        ];
    }

    public function classify(
        array $ticket
    ): array {
        $closed =
            (int) (
                $ticket['status_is_closed']
                ?? 0
            ) === 1;

        $topicId =
            $this->positiveId(
                $ticket['support_topic_id']
                ?? null
            );

        $ruleId =
            $this->positiveId(
                $ticket['matched_routing_rule_id']
                ?? null
            );

        $layerId =
            $this->positiveId(
                $ticket['current_support_layer_id']
                ?? null
            );

        $nodeId =
            $this->positiveId(
                $ticket['current_support_node_id']
                ?? null
            );

        $queueId =
            $this->positiveId(
                $ticket['current_support_queue_id']
                ?? null
            );

        $teamId =
            $this->positiveId(
                $ticket['current_support_team_id']
                ?? null
            );

        $assigneeId =
            $this->positiveId(
                $ticket['current_assignee_project_member_id']
                ?? null
            );

        $topology = [
            $layerId,
            $nodeId,
            $queueId,
            $teamId,
        ];

        $topologyPresent =
            count(
                array_filter(
                    $topology,
                    static fn (?int $value): bool =>
                        $value !== null
                )
            );

        $topologyNone =
            $topologyPresent === 0;

        $topologyComplete =
            $topologyPresent === 4;

        $topologyPartial =
            !$topologyNone
            && !$topologyComplete;

        $effectiveAssignmentMode =
            $this->effectiveAssignmentMode(
                $ticket
            );

        if (
            $topologyPartial
            || (
                $assigneeId !== null
                && !$topologyComplete
            )
        ) {
            return $this->classification(
                'partial_routing',
                'مسیریابی ناقص',
                'بخشی از اطلاعات مسیر تیکت ثبت شده و بخشی دیگر خالی است. این وضعیت باید قبل از هر بازیابی خودکار بررسی شود.',
                true,
                $closed,
                $effectiveAssignmentMode
            );
        }

        if ($topologyNone) {
            if ($ruleId !== null) {
                return $this->classification(
                    'invalid_topology',
                    'ساختار مسیریابی نامعتبر',
                    'قانون مسیریابی ثبت شده است اما لایه، گره، صف و تیم عملیاتی برای تیکت کامل نشده‌اند.',
                    true,
                    $closed,
                    $effectiveAssignmentMode
                );
            }

            if ($topicId !== null) {
                return $this->classification(
                    'no_matching_rule',
                    'قانون مسیریابی یافت نشد',
                    'موضوع پشتیبانی مشخص است اما هیچ قانون مسیریابی عملیاتی روی تیکت اعمال نشده است.',
                    true,
                    $closed,
                    $effectiveAssignmentMode
                );
            }

            return $this->classification(
                'missing_topic',
                'موضوع پشتیبانی تعیین نشده',
                'تیکت بدون موضوع پشتیبانی و بدون هیچ مسیر عملیاتی باقی مانده است.',
                true,
                $closed,
                $effectiveAssignmentMode
            );
        }

        if (!$topologyComplete) {
            return $this->classification(
                'invalid_topology',
                'ساختار مسیریابی نامعتبر',
                'ساختار عملیاتی تیکت قابل اتکا نیست.',
                true,
                $closed,
                $effectiveAssignmentMode
            );
        }

        if ($assigneeId === null) {
            if ($effectiveAssignmentMode === 'manual') {
                return $this->classification(
                    'awaiting_manual_assignment',
                    'در انتظار تخصیص دستی',
                    'مسیر عملیاتی کامل است و این صف برای تخصیص دستی تنظیم شده است.',
                    false,
                    $closed,
                    $effectiveAssignmentMode
                );
            }

            if (
                in_array(
                    $effectiveAssignmentMode,
                    [
                        'least_loaded',
                        'round_robin',
                        'fixed',
                    ],
                    true
                )
            ) {
                return $this->classification(
                    'no_eligible_assignee',
                    'کارشناس واجد شرایط یافت نشد',
                    'مسیر و صف مشخص هستند اما موتور تخصیص خودکار نتوانسته کارشناس واجد شرایطی انتخاب کند.',
                    true,
                    $closed,
                    $effectiveAssignmentMode
                );
            }

            return $this->classification(
                'invalid_topology',
                'حالت تخصیص نامعتبر',
                'مسیر عملیاتی کامل است اما حالت تخصیص این صف برای ادامه رسیدگی قابل تشخیص نیست.',
                true,
                $closed,
                $effectiveAssignmentMode
            );
        }

        if ($topicId === null) {
            return $this->classification(
                'legacy_topicless_routed',
                'تیکت مسیریابی‌شده بدون موضوع',
                'این تیکت قبلاً دارای مسیر و کارشناس عملیاتی شده است؛ نبود موضوع به‌تنهایی مجوز بازیابی یا بازنویسی مسیر نیست.',
                false,
                $closed,
                $effectiveAssignmentMode
            );
        }

        return $this->classification(
            'healthy',
            'مسیریابی سالم',
            'موضوع، مسیر عملیاتی و تخصیص کارشناس برای تیکت معتبر است.',
            false,
            $closed,
            $effectiveAssignmentMode
        );
    }

    private function classification(
        string $code,
        string $title,
        string $message,
        bool $rawActionable,
        bool $closed,
        ?string $assignmentMode
    ): array {
        return [
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'raw_actionable' => $rawActionable,
            'actionable' =>
                $rawActionable
                && !$closed,
            'closed' => $closed,
            'assignment_mode' => $assignmentMode,
        ];
    }

    private function effectiveAssignmentMode(
        array $ticket
    ): ?string {
        $ruleMode =
            strtolower(
                trim(
                    (string) (
                        $ticket['rule_assignment_mode_code']
                        ?? ''
                    )
                )
            );

        if (
            $ruleMode !== ''
            && $ruleMode !== 'inherit'
        ) {
            return $ruleMode;
        }

        $queueMode =
            strtolower(
                trim(
                    (string) (
                        $ticket['queue_assignment_mode_code']
                        ?? ''
                    )
                )
            );

        return $queueMode !== ''
            ? $queueMode
            : null;
    }

    private function positiveId(
        mixed $value
    ): ?int {
        if ($value === null) {
            return null;
        }

        $id = (int) $value;

        return $id > 0
            ? $id
            : null;
    }

    private function notFoundClassification(): array
    {
        return [
            'code' => 'not_found',
            'title' => 'تیکت یافت نشد',
            'message' => '',
            'raw_actionable' => false,
            'actionable' => false,
            'closed' => false,
            'assignment_mode' => null,
        ];
    }
}
