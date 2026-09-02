<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\SupportTopicRoutingAdminRepository;

final class SupportTopicRoutingAdminService
{
    public function __construct(
        private ?SupportTopicRoutingAdminRepository $routing = null
    ) {
        $this->routing ??=
            new SupportTopicRoutingAdminRepository();
    }


    public function page(
        string $projectReference
    ): ?array {
        $project =
            $this->routing->projectByReference(
                trim($projectReference)
            );

        if ($project === null) {
            return null;
        }

        return
            array_merge(
                [
                    'project' => $project,
                ],
                $this->routing->pageData(
                    (int) $project['id']
                )
            );
    }


    public function mutate(
        string $projectReference,
        string $action,
        array $input
    ): array {
        $project =
            $this->routing->projectByReference(
                trim($projectReference)
            );

        if (
            $project === null
            || !empty($project['archived_at'])
        ) {
            return [
                'ok' => false,
                'not_found' => true,
            ];
        }

        $projectId =
            (int) $project['id'];

        if ($action === 'topic.create') {
            return
                $this->createTopic(
                    $projectId,
                    $input
                );
        }

        if ($action === 'topic.update') {
            return
                $this->updateTopic(
                    $projectId,
                    $input
                );
        }


        if ($action === 'rule.create') {
            return
                $this->createRule(
                    $projectId,
                    $input
                );
        }

        return [
            'ok' => false,
            'errors' => [
                'عملیات درخواستی معتبر نیست.',
            ],
        ];
    }


    private function createTopic(
        int $projectId,
        array $input
    ): array {
        $code =
            strtolower(
                trim(
                    (string) (
                        $input['code']
                        ?? ''
                    )
                )
            );

        $title =
            trim(
                (string) (
                    $input['title']
                    ?? ''
                )
            );

        $serviceId =
            (int) (
                $input['service_id']
                ?? 0
            );

        $parentId =
            (int) (
                $input['parent_topic_id']
                ?? 0
            );

        $errors = [];

        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,99}$/',
                $code
            ) !== 1
        ) {
            $errors[] =
                'کد موضوع معتبر نیست.';
        }

        if ($title === '') {
            $errors[] =
                'عنوان موضوع الزامی است.';
        }

        if (
            $this->routing->topicCodeExists(
                $projectId,
                $code
            )
        ) {
            $errors[] =
                'کد موضوع تکراری است.';
        }


        $serviceId =
            $serviceId > 0
                ? $serviceId
                : null;

        if (
            $serviceId !== null
            && $this->routing->service(
                $projectId,
                $serviceId
            ) === null
        ) {
            $errors[] =
                'سرویس انتخاب‌شده معتبر نیست.';
        }


        $parentId =
            $parentId > 0
                ? $parentId
                : null;

        if (
            $parentId !== null
            && $this->routing->topic(
                $projectId,
                $parentId
            ) === null
        ) {
            $errors[] =
                'موضوع والد معتبر نیست.';
        }


        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }


        $this->routing->createTopicGoverned([
            'public_reference' =>
                $this->reference('TTP'),

            'project_id' =>
                $projectId,

            'service_id' =>
                $serviceId,

            'parent_topic_id' =>
                $parentId,

            'code' =>
                $code,

            'title' =>
                mb_substr(
                    $title,
                    0,
                    255,
                    'UTF-8'
                ),

            'description' =>
                $this->nullableText(
                    $input['description']
                    ?? null
                ),

            'is_selectable' =>
                $this->flag(
                    $input['is_selectable']
                    ?? 0
                ),

            'is_default' =>
                $this->flag(
                    $input['is_default']
                    ?? 0
                ),

            'sort_order' =>
                max(
                    0,
                    (int) (
                        $input['sort_order']
                        ?? 0
                    )
                ),
        ]);

        return [
            'ok' => true,
            'status' => 'topic-created',
        ];
    }


    /*
     * TICKETING_SUPPORT_TOPIC_GOVERNANCE_V1
     *
     * Low-risk:
     *   title / description / sort order.
     *
     * Structural:
     *   service / parent / selectable / default / status.
     *
     * Structural edits with existing dependants require explicit
     * impact acknowledgement.  Changing service after a topic has
     * tickets or routing rules is blocked because that would alter
     * the semantic routing scope of live/historical references.
     */
    private function updateTopic(
        int $projectId,
        array $input
    ): array {
        $topicId =
            max(
                0,
                (int) (
                    $input['topic_id']
                    ?? 0
                )
            );

        $current =
            $this->routing
                ->topic(
                    $projectId,
                    $topicId
                );

        if ($current === null) {
            return [
                'ok' => false,
                'errors' => [
                    'موضوع مورد نظر پیدا نشد.',
                ],
            ];
        }

        $title =
            trim(
                (string) (
                    $input['title']
                    ?? ''
                )
            );

        $description =
            trim(
                (string) (
                    $input['description']
                    ?? ''
                )
            );

        $serviceId =
            max(
                0,
                (int) (
                    $input['service_id']
                    ?? 0
                )
            );

        $parentTopicId =
            max(
                0,
                (int) (
                    $input['parent_topic_id']
                    ?? 0
                )
            );

        $isSelectable =
            !empty(
                $input['is_selectable']
            )
                ? 1
                : 0;

        $isDefault =
            !empty(
                $input['is_default']
            )
                ? 1
                : 0;

        $status =
            strtolower(
                trim(
                    (string) (
                        $input['status']
                        ?? 'active'
                    )
                )
            );

        $sortOrder =
            max(
                0,
                (int) (
                    $input['sort_order']
                    ?? 0
                )
            );

        $confirmImpact =
            !empty(
                $input['confirm_impact']
            );

        $errors = [];

        if ($title === '') {
            $errors[] =
                'عنوان موضوع الزامی است.';
        }

        if (
            function_exists('mb_strlen')
            ? mb_strlen(
                $title,
                'UTF-8'
            ) > 255
            : strlen($title) > 255
        ) {
            $errors[] =
                'عنوان موضوع بیش از حد مجاز است.';
        }

        if (
            function_exists('mb_strlen')
            ? mb_strlen(
                $description,
                'UTF-8'
            ) > 10000
            : strlen($description) > 10000
        ) {
            $errors[] =
                'توضیحات موضوع بیش از حد مجاز است.';
        }

        if (
            !in_array(
                $status,
                [
                    'active',
                    'inactive',
                ],
                true
            )
        ) {
            $errors[] =
                'وضعیت موضوع معتبر نیست.';
        }

        if (
            $isDefault === 1
            && (
                $isSelectable !== 1
                || $status !== 'active'
            )
        ) {
            $errors[] =
                'موضوع پیش‌فرض باید فعال و قابل انتخاب باشد.';
        }

        $service = null;

        if ($serviceId > 0) {
            $service =
                $this->routing
                    ->service(
                        $projectId,
                        $serviceId
                    );

            if ($service === null) {
                $errors[] =
                    'زیرسامانه انتخاب‌شده معتبر نیست.';
            }
        }

        $parent = null;

        if ($parentTopicId > 0) {

            if ($parentTopicId === $topicId) {
                $errors[] =
                    'یک موضوع نمی‌تواند والد خودش باشد.';
            } else {
                $parent =
                    $this->routing
                        ->topic(
                            $projectId,
                            $parentTopicId
                        );

                if ($parent === null) {
                    $errors[] =
                        'موضوع والد معتبر نیست.';
                }
            }

            if (
                $parent !== null
                && $this->routing
                    ->topicWouldCreateCycle(
                        $projectId,
                        $topicId,
                        $parentTopicId
                    )
            ) {
                $errors[] =
                    'این جابه‌جایی باعث ایجاد چرخه در دسته‌بندی می‌شود.';
            }

            if (
                $parent !== null
                && strtolower(
                    trim(
                        (string) (
                            $parent['status']
                            ?? ''
                        )
                    )
                ) !== 'active'
            ) {
                $errors[] =
                    'موضوع والد باید فعال باشد.';
            }

            if ($parent !== null) {
                $parentServiceId =
                    isset(
                        $parent['service_id']
                    )
                    && $parent['service_id'] !== null
                        ? (int) $parent['service_id']
                        : 0;

                if (
                    $parentServiceId > 0
                    && $parentServiceId !== $serviceId
                ) {
                    $errors[] =
                        'موضوع والد باید متعلق به همان زیرسامانه یا عمومی باشد.';
                }
            }
        }

        $children =
            $this->routing
                ->topicChildren(
                    $projectId,
                    $topicId
                );

        if ($serviceId > 0) {
            foreach ($children as $child) {
                $childServiceId =
                    isset(
                        $child['service_id']
                    )
                    && $child['service_id'] !== null
                        ? (int) $child['service_id']
                        : 0;

                if ($childServiceId !== $serviceId) {
                    $errors[] =
                        'زیرسامانه این دسته با یکی از زیرموضوع‌های فعلی سازگار نیست. ابتدا ساختار زیرموضوع‌ها را اصلاح کنید.';
                    break;
                }
            }
        }

        $impact =
            $this->routing
                ->topicImpact(
                    $projectId,
                    $topicId
                );

        if ($impact === null) {
            $errors[] =
                'اثر تغییر موضوع قابل محاسبه نیست.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $oldServiceId =
            isset(
                $current['service_id']
            )
            && $current['service_id'] !== null
                ? (int) $current['service_id']
                : 0;

        $oldParentTopicId =
            isset(
                $current['parent_topic_id']
            )
            && $current['parent_topic_id'] !== null
                ? (int) $current['parent_topic_id']
                : 0;

        $structuralChange =
            $oldServiceId !== $serviceId
            || $oldParentTopicId !== $parentTopicId
            || (int) (
                $current['is_selectable']
                ?? 0
            ) !== $isSelectable
            || (int) (
                $current['is_default']
                ?? 0
            ) !== $isDefault
            || strtolower(
                trim(
                    (string) (
                        $current['status']
                        ?? ''
                    )
                )
            ) !== $status;

        $childCount =
            (int) (
                $impact['child_count']
                ?? 0
            );

        $activeChildCount =
            (int) (
                $impact['active_child_count']
                ?? 0
            );

        $ruleCount =
            (int) (
                $impact['routing_rule_count']
                ?? 0
            );

        $activeRuleCount =
            (int) (
                $impact['active_routing_rule_count']
                ?? 0
            );

        $ticketCount =
            (int) (
                $impact['ticket_count']
                ?? 0
            );

        $openTicketCount =
            (int) (
                $impact['open_ticket_count']
                ?? 0
            );

        if (
            $oldServiceId !== $serviceId
            && (
                $ruleCount > 0
                || $ticketCount > 0
            )
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'زیرسامانه موضوعی که در قانون مسیریابی یا تیکت استفاده شده است قابل تغییر مستقیم نیست. موضوع جدید بسازید و موضوع قبلی را غیرفعال کنید.',
                ],
            ];
        }

        if (
            $status === 'inactive'
            && $activeChildCount > 0
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'تا زمانی که این دسته زیرموضوع فعال دارد، غیرفعال‌کردن آن مجاز نیست.',
                ],
            ];
        }

        $impactCount =
            $childCount
            + $ruleCount
            + $ticketCount;

        if (
            $structuralChange
            && $impactCount > 0
            && !$confirmImpact
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'این تغییر ساختاری است و روی '
                    . $childCount
                    . ' زیرموضوع، '
                    . $ruleCount
                    . ' قانون مسیریابی و '
                    . $ticketCount
                    . ' تیکت اثر دارد. برای ادامه گزینه «اثر تغییر ساختاری را بررسی کردم» را فعال کنید.',
                ],
            ];
        }

        $this->routing
            ->updateTopicGoverned([
                'id' =>
                    $topicId,

                'project_id' =>
                    $projectId,

                'service_id' =>
                    $serviceId > 0
                        ? $serviceId
                        : null,

                'parent_topic_id' =>
                    $parentTopicId > 0
                        ? $parentTopicId
                        : null,

                'title' =>
                    $title,

                'description' =>
                    $description !== ''
                        ? $description
                        : null,

                'is_selectable' =>
                    $isSelectable,

                'is_default' =>
                    $isDefault,

                'status' =>
                    $status,

                'sort_order' =>
                    $sortOrder,
            ]);

        return [
            'ok' => true,
            'status' => 'topic-updated',
            'impact' => [
                'children' =>
                    $childCount,

                'active_children' =>
                    $activeChildCount,

                'rules' =>
                    $ruleCount,

                'active_rules' =>
                    $activeRuleCount,

                'tickets' =>
                    $ticketCount,

                'open_tickets' =>
                    $openTicketCount,
            ],
        ];
    }


    private function createRule(
        int $projectId,
        array $input
    ): array {
        $serviceId =
            (int) (
                $input['service_id']
                ?? 0
            );

        $topicId =
            (int) (
                $input['topic_id']
                ?? 0
            );

        $layerId =
            (int) (
                $input['target_layer_id']
                ?? 0
            );

        $nodeId =
            (int) (
                $input['target_node_id']
                ?? 0
            );

        $queueId =
            (int) (
                $input['target_queue_id']
                ?? 0
            );

        $teamId =
            (int) (
                $input['target_team_id']
                ?? 0
            );

        $memberId =
            (int) (
                $input['fixed_project_member_id']
                ?? 0
            );

        $scopeType =
            strtolower(
                trim(
                    (string) (
                        $input['scope_type_code']
                        ?? 'all'
                    )
                )
            );

        $scopeReference =
            trim(
                (string) (
                    $input['scope_reference']
                    ?? ''
                )
            );

        $assignmentMode =
            strtolower(
                trim(
                    (string) (
                        $input['assignment_mode_code']
                        ?? 'inherit'
                    )
                )
            );

        $title =
            trim(
                (string) (
                    $input['title']
                    ?? ''
                )
            );

        $errors = [];


        if ($title === '') {
            $errors[] =
                'عنوان قانون الزامی است.';
        }


        $serviceId =
            $serviceId > 0
                ? $serviceId
                : null;

        if (
            $serviceId !== null
            && $this->routing->service(
                $projectId,
                $serviceId
            ) === null
        ) {
            $errors[] =
                'سرویس قانون معتبر نیست.';
        }


        $topicId =
            $topicId > 0
                ? $topicId
                : null;

        if (
            $topicId !== null
            && $this->routing->topic(
                $projectId,
                $topicId
            ) === null
        ) {
            $errors[] =
                'موضوع قانون معتبر نیست.';
        }


        $layer =
            $this->routing->layer(
                $projectId,
                $layerId
            );

        $node =
            $this->routing->node(
                $projectId,
                $nodeId
            );

        $queue =
            $this->routing->queue(
                $projectId,
                $queueId
            );

        $team =
            $this->routing->team(
                $projectId,
                $teamId
            );


        if (
            $layer === null
            || $node === null
            || $queue === null
            || $team === null
        ) {
            $errors[] =
                'مقصد مسیریابی کامل یا معتبر نیست.';
        } else {

            if (
                (int) $node['layer_id']
                !== $layerId
            ) {
                $errors[] =
                    'گره با لایه انتخاب‌شده همخوانی ندارد.';
            }

            if (
                (int) $queue['node_id']
                !== $nodeId
            ) {
                $errors[] =
                    'صف با گره انتخاب‌شده همخوانی ندارد.';
            }

            if (
                !$this->routing
                    ->teamOwnsNodeAndQueue(
                        $teamId,
                        $nodeId,
                        $queueId
                    )
            ) {
                $errors[] =
                    'تیم به گره و صف انتخاب‌شده متصل نیست.';
            }
        }


        if (
            !in_array(
                $scopeType,
                [
                    'all',
                    'organization',
                ],
                true
            )
        ) {
            $errors[] =
                'نوع Scope معتبر نیست.';
        }


        if (
            $scopeType === 'organization'
            && $scopeReference === ''
        ) {
            $errors[] =
                'برای Scope سازمانی شناسه سازمان الزامی است.';
        }


        if (
            !in_array(
                $assignmentMode,
                [
                    'inherit',
                    'manual',
                    'least_loaded',
                    'round_robin',
                    'fixed',
                ],
                true
            )
        ) {
            $errors[] =
                'روش تخصیص معتبر نیست.';
        }


        $memberId =
            $memberId > 0
                ? $memberId
                : null;

        if (
            $assignmentMode === 'fixed'
            && $memberId === null
        ) {
            $errors[] =
                'برای تخصیص ثابت باید کارشناس انتخاب شود.';
        }


        if ($memberId !== null) {

            if (
                $this->routing->member(
                    $projectId,
                    $memberId
                ) === null
            ) {
                $errors[] =
                    'کارشناس ثابت معتبر نیست.';
            } elseif (
                !$this->routing
                    ->memberBelongsToTeam(
                        $teamId,
                        $memberId
                    )
            ) {
                $errors[] =
                    'کارشناس ثابت عضو تیم انتخاب‌شده نیست.';
            }
        }


        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }


        $this->routing->createRule([
            'public_reference' =>
                $this->reference('TRR'),

            'project_id' =>
                $projectId,

            'service_id' =>
                $serviceId,

            'topic_id' =>
                $topicId,

            'title' =>
                mb_substr(
                    $title,
                    0,
                    255,
                    'UTF-8'
                ),

            'description' =>
                $this->nullableText(
                    $input['description']
                    ?? null
                ),

            'scope_type_code' =>
                $scopeType,

            'scope_reference' =>
                $scopeType === 'all'
                    ? null
                    : $scopeReference,

            'target_layer_id' =>
                $layerId,

            'target_node_id' =>
                $nodeId,

            'target_queue_id' =>
                $queueId,

            'target_team_id' =>
                $teamId,

            'fixed_project_member_id' =>
                $memberId,

            'assignment_mode_code' =>
                $assignmentMode,

            'priority' =>
                max(
                    1,
                    min(
                        100000,
                        (int) (
                            $input['priority']
                            ?? 100
                        )
                    )
                ),

            'sort_order' =>
                max(
                    0,
                    (int) (
                        $input['sort_order']
                        ?? 0
                    )
                ),
        ]);

        return [
            'ok' => true,
            'status' => 'rule-created',
        ];
    }


    private function flag(
        $value
    ): int {
        return
            in_array(
                (string) $value,
                [
                    '1',
                    'on',
                    'true',
                    'yes',
                ],
                true
            )
                ? 1
                : 0;
    }


    private function nullableText(
        $value
    ): ?string {
        $value =
            trim(
                (string) $value
            );

        return
            $value === ''
                ? null
                : mb_substr(
                    $value,
                    0,
                    3000,
                    'UTF-8'
                );
    }


    private function reference(
        string $prefix
    ): string {
        return
            $prefix
            . '-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );
    }
}
