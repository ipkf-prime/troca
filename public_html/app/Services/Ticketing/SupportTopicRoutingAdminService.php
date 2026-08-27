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


        $this->routing->createTopic([
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
