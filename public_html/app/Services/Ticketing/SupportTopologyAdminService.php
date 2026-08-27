<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\SupportTopologyAdminRepository;

final class SupportTopologyAdminService
{
    public function __construct(
        private ?SupportTopologyAdminRepository $topology = null
    ) {
        $this->topology ??=
            new SupportTopologyAdminRepository();
    }


    public function page(
        string $projectReference
    ): ?array {
        $project =
            $this->topology->projectByReference(
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
                $this->topology->pageData(
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
            $this->topology->projectByReference(
                trim($projectReference)
            );

        if (
            $project === null
            || !empty(
                $project['archived_at']
            )
        ) {
            return [
                'ok' => false,
                'not_found' => true,
            ];
        }

        $projectId =
            (int) $project['id'];

        return match ($action) {
            'layer.create' =>
                $this->createLayer(
                    $projectId,
                    $input
                ),

            'node.create' =>
                $this->createNode(
                    $projectId,
                    $input
                ),

            'relation.create' =>
                $this->createRelation(
                    $projectId,
                    $input
                ),

            'team.create' =>
                $this->createTeam(
                    $projectId,
                    $input
                ),

            'queue.create' =>
                $this->createQueue(
                    $projectId,
                    $input
                ),

            'team_node.bind' =>
                $this->bindTeamNode(
                    $projectId,
                    $input
                ),

            'team_queue.bind' =>
                $this->bindTeamQueue(
                    $projectId,
                    $input
                ),

            'team_member.add' =>
                $this->addTeamMember(
                    $projectId,
                    $input
                ),

            default => [
                'ok' => false,
                'errors' => [
                    'عملیات درخواستی معتبر نیست.',
                ],
            ],
        };
    }


    private function createLayer(
        int $projectId,
        array $input
    ): array {
        $code =
            $this->code(
                $input['code']
                ?? ''
            );

        $title =
            $this->text(
                $input['title']
                ?? '',
                255
            );

        $rank =
            max(
                1,
                min(
                    100000,
                    (int) (
                        $input['rank_order']
                        ?? 0
                    )
                )
            );

        $errors = [];

        if (!$this->validCode($code)) {
            $errors[] =
                'کد لایه معتبر نیست.';
        }

        if ($title === '') {
            $errors[] =
                'عنوان لایه الزامی است.';
        }

        if (
            $this->topology->layerCodeExists(
                $projectId,
                $code
            )
        ) {
            $errors[] =
                'کد لایه تکراری است.';
        }

        if (
            $this->topology->layerRankExists(
                $projectId,
                $rank
            )
        ) {
            $errors[] =
                'رتبه این لایه قبلاً استفاده شده است.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $this->topology->createLayer([
            'public_reference' =>
                $this->reference('TSL'),

            'project_id' =>
                $projectId,

            'code' =>
                $code,

            'title' =>
                $title,

            'description' =>
                $this->nullable(
                    $input['description']
                    ?? null,
                    3000
                ),

            'rank_order' =>
                $rank,

            'can_observe_descendants' =>
                $this->flag(
                    $input[
                        'can_observe_descendants'
                    ]
                    ?? 0
                ),

            'can_assist_descendants' =>
                $this->flag(
                    $input[
                        'can_assist_descendants'
                    ]
                    ?? 0
                ),

            'can_takeover_descendants' =>
                $this->flag(
                    $input[
                        'can_takeover_descendants'
                    ]
                    ?? 0
                ),

            'can_transfer_downward' =>
                $this->flag(
                    $input[
                        'can_transfer_downward'
                    ]
                    ?? 0
                ),

            'is_entry_layer' =>
                $this->flag(
                    $input['is_entry_layer']
                    ?? 0
                ),

            'is_terminal_layer' =>
                $this->flag(
                    $input['is_terminal_layer']
                    ?? 0
                ),

            'sort_order' =>
                $rank,
        ]);

        return [
            'ok' => true,
            'status' => 'layer-created',
        ];
    }


    private function createNode(
        int $projectId,
        array $input
    ): array {
        $layerId =
            (int) (
                $input['layer_id']
                ?? 0
            );

        $code =
            $this->code(
                $input['code']
                ?? ''
            );

        $title =
            $this->text(
                $input['title']
                ?? '',
                255
            );

        $errors = [];

        if (
            $this->topology->layer(
                $projectId,
                $layerId
            ) === null
        ) {
            $errors[] =
                'لایه انتخاب‌شده معتبر نیست.';
        }

        if (!$this->validCode($code)) {
            $errors[] =
                'کد گره معتبر نیست.';
        }

        if ($title === '') {
            $errors[] =
                'عنوان گره الزامی است.';
        }

        if (
            $this->topology->nodeCodeExists(
                $projectId,
                $code
            )
        ) {
            $errors[] =
                'کد گره تکراری است.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $this->topology->createNode([
            'public_reference' =>
                $this->reference('TSN'),

            'project_id' =>
                $projectId,

            'layer_id' =>
                $layerId,

            'code' =>
                $code,

            'title' =>
                $title,

            'description' =>
                $this->nullable(
                    $input['description']
                    ?? null,
                    3000
                ),

            'core_organization_reference' =>
                $this->nullableAscii(
                    $input[
                        'core_organization_reference'
                    ]
                    ?? null,
                    100
                ),

            'scope_type_code' =>
                $this->nullableAscii(
                    $input['scope_type_code']
                    ?? null,
                    50
                ),

            'scope_reference' =>
                $this->nullableAscii(
                    $input['scope_reference']
                    ?? null,
                    190
                ),

            'is_intake_node' =>
                $this->flag(
                    $input['is_intake_node']
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
            'status' => 'node-created',
        ];
    }


    private function createRelation(
        int $projectId,
        array $input
    ): array {
        $parentId =
            (int) (
                $input['parent_node_id']
                ?? 0
            );

        $childId =
            (int) (
                $input['child_node_id']
                ?? 0
            );

        $parent =
            $this->topology->node(
                $projectId,
                $parentId
            );

        $child =
            $this->topology->node(
                $projectId,
                $childId
            );

        $errors = [];

        if (
            $parent === null
            || $child === null
        ) {
            $errors[] =
                'گره والد یا فرزند معتبر نیست.';
        }

        if ($parentId === $childId) {
            $errors[] =
                'یک گره نمی‌تواند والد خودش باشد.';
        }

        if (
            $parent !== null
            && $child !== null
            && (int) $parent['rank_order']
                <= (int) $child['rank_order']
        ) {
            $errors[] =
                'گره والد باید در لایه‌ای با رتبه بالاتر از گره فرزند باشد.';
        }

        if (
            $parent !== null
            && $child !== null
            && $this->topology->relationExists(
                $projectId,
                $parentId,
                $childId
            )
        ) {
            $errors[] =
                'این ارتباط قبلاً تعریف شده است.';
        }

        if (
            $parent !== null
            && $child !== null
            && $this->topology->wouldCreateCycle(
                $projectId,
                $parentId,
                $childId
            )
        ) {
            $errors[] =
                'این ارتباط باعث ایجاد چرخه در ساختار پشتیبانی می‌شود.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $this->topology->createRelation([
            'public_reference' =>
                $this->reference('TSR'),

            'project_id' =>
                $projectId,

            'parent_node_id' =>
                $parentId,

            'child_node_id' =>
                $childId,

            'is_primary_path' =>
                $this->flag(
                    $input['is_primary_path']
                    ?? 1
                ),

            'allow_escalation' =>
                $this->flag(
                    $input['allow_escalation']
                    ?? 1
                ),

            'allow_downward_transfer' =>
                $this->flag(
                    $input[
                        'allow_downward_transfer'
                    ]
                    ?? 1
                ),
        ]);

        return [
            'ok' => true,
            'status' => 'relation-created',
        ];
    }


    private function createTeam(
        int $projectId,
        array $input
    ): array {
        $code =
            $this->code(
                $input['code']
                ?? ''
            );

        $title =
            $this->text(
                $input['title']
                ?? '',
                255
            );

        $errors = [];

        if (!$this->validCode($code)) {
            $errors[] =
                'کد تیم معتبر نیست.';
        }

        if ($title === '') {
            $errors[] =
                'عنوان تیم الزامی است.';
        }

        if (
            $this->topology->teamCodeExists(
                $projectId,
                $code
            )
        ) {
            $errors[] =
                'کد تیم تکراری است.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $this->topology->createTeam([
            'public_reference' =>
                $this->reference('TST'),

            'project_id' =>
                $projectId,

            'code' =>
                $code,

            'title' =>
                $title,

            'description' =>
                $this->nullable(
                    $input['description']
                    ?? null,
                    3000
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
            'status' => 'team-created',
        ];
    }


    private function createQueue(
        int $projectId,
        array $input
    ): array {
        $nodeId =
            (int) (
                $input['node_id']
                ?? 0
            );

        $code =
            $this->code(
                $input['code']
                ?? ''
            );

        $title =
            $this->text(
                $input['title']
                ?? '',
                255
            );

        $mode =
            $this->code(
                $input[
                    'assignment_mode_code'
                ]
                ?? 'manual'
            );

        $allowedModes = [
            'manual',
            'round_robin',
            'least_loaded',
            'rule_based',
        ];

        $errors = [];

        if (
            $this->topology->node(
                $projectId,
                $nodeId
            ) === null
        ) {
            $errors[] =
                'گره صف معتبر نیست.';
        }

        if (!$this->validCode($code)) {
            $errors[] =
                'کد صف معتبر نیست.';
        }

        if ($title === '') {
            $errors[] =
                'عنوان صف الزامی است.';
        }

        if (
            !in_array(
                $mode,
                $allowedModes,
                true
            )
        ) {
            $errors[] =
                'روش تخصیص صف معتبر نیست.';
        }

        if (
            $this->topology->queueCodeExists(
                $projectId,
                $code
            )
        ) {
            $errors[] =
                'کد صف تکراری است.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $maxOpen =
            trim(
                (string) (
                    $input['max_open_per_agent']
                    ?? ''
                )
            );

        $this->topology->createQueue([
            'public_reference' =>
                $this->reference('TSQ'),

            'project_id' =>
                $projectId,

            'node_id' =>
                $nodeId,

            'code' =>
                $code,

            'title' =>
                $title,

            'description' =>
                $this->nullable(
                    $input['description']
                    ?? null,
                    3000
                ),

            'assignment_mode_code' =>
                $mode,

            'max_open_per_agent' =>
                $maxOpen === ''
                    ? null
                    : max(
                        1,
                        min(
                            100000,
                            (int) $maxOpen
                        )
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
            'status' => 'queue-created',
        ];
    }


    private function bindTeamNode(
        int $projectId,
        array $input
    ): array {
        $teamId =
            (int) (
                $input['team_id']
                ?? 0
            );

        $nodeId =
            (int) (
                $input['node_id']
                ?? 0
            );

        if (
            $this->topology->team(
                $projectId,
                $teamId
            ) === null
            || $this->topology->node(
                $projectId,
                $nodeId
            ) === null
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'تیم یا گره انتخاب‌شده معتبر نیست.',
                ],
            ];
        }

        $this->topology->bindTeamNode(
            $teamId,
            $nodeId
        );

        return [
            'ok' => true,
            'status' => 'team-node-bound',
        ];
    }


    private function bindTeamQueue(
        int $projectId,
        array $input
    ): array {
        $teamId =
            (int) (
                $input['team_id']
                ?? 0
            );

        $queueId =
            (int) (
                $input['queue_id']
                ?? 0
            );

        $team =
            $this->topology->team(
                $projectId,
                $teamId
            );

        $queue =
            $this->topology->queue(
                $projectId,
                $queueId
            );

        if (
            $team === null
            || $queue === null
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'تیم یا صف انتخاب‌شده معتبر نیست.',
                ],
            ];
        }

        if (
            !$this->topology->teamNodeBindingExists(
                $teamId,
                (int) $queue['node_id']
            )
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'ابتدا تیم را به گره مربوط به این صف متصل کنید.',
                ],
            ];
        }

        $this->topology->bindTeamQueue(
            $teamId,
            $queueId
        );

        return [
            'ok' => true,
            'status' => 'team-queue-bound',
        ];
    }


    private function addTeamMember(
        int $projectId,
        array $input
    ): array {
        $teamId =
            (int) (
                $input['team_id']
                ?? 0
            );

        $memberId =
            (int) (
                $input['project_member_id']
                ?? 0
            );

        $role =
            $this->code(
                $input['staff_role_code']
                ?? 'agent'
            );

        $allowedRoles = [
            'agent',
            'supervisor',
            'manager',
            'observer',
        ];

        $team =
            $this->topology->team(
                $projectId,
                $teamId
            );

        $member =
            $this->topology->projectMember(
                $projectId,
                $memberId
            );

        $errors = [];

        if ($team === null) {
            $errors[] =
                'تیم انتخاب‌شده معتبر نیست.';
        }

        if ($member === null) {
            $errors[] =
                'عضو پروژه معتبر نیست.';
        }

        if (
            $member !== null
            && trim(
                (string) (
                    $member['user_reference']
                    ?? ''
                )
            ) === ''
        ) {
            $errors[] =
                'کارشناس پشتیبانی باید به حساب Core متصل باشد.';
        }

        if (
            !in_array(
                $role,
                $allowedRoles,
                true
            )
        ) {
            $errors[] =
                'نقش کارشناس معتبر نیست.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $weight =
            (float) (
                $input['workload_weight']
                ?? 1
            );

        if ($weight <= 0) {
            $weight = 1;
        }

        $capabilities =
            $this->roleCapabilities(
                $role
            );

        $this->topology->addTeamMember([
            'team_id' =>
                $teamId,

            'project_member_id' =>
                $memberId,

            'staff_role_code' =>
                $role,

            'workload_weight' =>
                min(
                    1000,
                    $weight
                ),

            'can_assign' =>
                $capabilities['assign'],

            'can_observe' =>
                $capabilities['observe'],

            'can_assist' =>
                $capabilities['assist'],

            'can_takeover' =>
                $capabilities['takeover'],

            'can_transfer' =>
                $capabilities['transfer'],
        ]);

        return [
            'ok' => true,
            'status' => 'team-member-added',
        ];
    }


    private function roleCapabilities(
        string $role
    ): array {
        return match ($role) {
            'manager' => [
                'assign' => 1,
                'observe' => 1,
                'assist' => 1,
                'takeover' => 1,
                'transfer' => 1,
            ],

            'supervisor' => [
                'assign' => 1,
                'observe' => 1,
                'assist' => 1,
                'takeover' => 1,
                'transfer' => 1,
            ],

            'observer' => [
                'assign' => 0,
                'observe' => 1,
                'assist' => 0,
                'takeover' => 0,
                'transfer' => 0,
            ],

            default => [
                'assign' => 0,
                'observe' => 1,
                'assist' => 1,
                'takeover' => 0,
                'transfer' => 0,
            ],
        };
    }


    private function validCode(
        string $code
    ): bool {
        return
            preg_match(
                '/^[a-z][a-z0-9_-]{1,99}$/',
                $code
            ) === 1;
    }


    private function code(
        mixed $value
    ): string {
        return
            strtolower(
                trim(
                    (string) $value
                )
            );
    }


    private function text(
        mixed $value,
        int $limit
    ): string {
        $value =
            trim(
                (string) $value
            );

        if (
            function_exists(
                'mb_substr'
            )
        ) {
            return
                mb_substr(
                    $value,
                    0,
                    $limit,
                    'UTF-8'
                );
        }

        return
            substr(
                $value,
                0,
                $limit
            );
    }


    private function nullable(
        mixed $value,
        int $limit
    ): ?string {
        $value =
            $this->text(
                $value,
                $limit
            );

        return
            $value === ''
                ? null
                : $value;
    }


    private function nullableAscii(
        mixed $value,
        int $limit
    ): ?string {
        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return null;
        }

        return
            substr(
                $value,
                0,
                $limit
            );
    }


    private function flag(
        mixed $value
    ): int {
        return
            in_array(
                (string) $value,
                [
                    '1',
                    'on',
                    'yes',
                    'true',
                ],
                true
            )
                ? 1
                : 0;
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
