<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

/**
 * TICKETING_SCOPED_SUPPORT_TOPOLOGY_ADMINISTRATION_V1
 *
 * Delegated scope enforcement for the existing Support Topology surface.
 * Full/global authority is classified at the route layer, not here.
 */
final class TicketingScopedSupportTopologyService
{
    public function __construct(
        private ?TicketingProjectMemberScopeRuntimeInterface $scopes = null
    ) {
        $this->scopes ??=
            new TicketingProjectMemberScopeRuntimeService();
    }

    public function projectPage(
        int $userId,
        array $page
    ): array {
        $projectId =
            (int) (
                $page['project']['id']
                ?? 0
            );

        if (
            $userId < 1
            || $projectId < 1
        ) {
            return $this->emptyProjection(
                $page
            );
        }

        $visibleNodes = [];
        $visibleNodeIds = [];

        foreach (
            is_array(
                $page['nodes']
                ?? null
            )
                ? $page['nodes']
                : []
            as $node
        ) {
            if (
                !is_array($node)
                || !$this->nodeAllowed(
                    $userId,
                    $projectId,
                    'topology.view',
                    $node
                )
            ) {
                continue;
            }

            $nodeId =
                (int) (
                    $node['id']
                    ?? 0
                );

            if ($nodeId < 1) {
                continue;
            }

            $visibleNodeIds[$nodeId] =
                true;

            $visibleNodes[] =
                $node;
        }

        $page['nodes'] =
            $visibleNodes;

        $page['relations'] =
            array_values(
                array_filter(
                    is_array(
                        $page['relations']
                        ?? null
                    )
                        ? $page['relations']
                        : [],
                    static function (
                        mixed $relation
                    ) use (
                        $visibleNodeIds
                    ): bool {
                        if (!is_array($relation)) {
                            return false;
                        }

                        return
                            isset(
                                $visibleNodeIds[
                                    (int) (
                                        $relation[
                                            'parent_node_id'
                                        ]
                                        ?? 0
                                    )
                                ]
                            )
                            &&
                            isset(
                                $visibleNodeIds[
                                    (int) (
                                        $relation[
                                            'child_node_id'
                                        ]
                                        ?? 0
                                    )
                                ]
                            );
                    }
                )
            );

        $page['queues'] =
            array_values(
                array_filter(
                    is_array(
                        $page['queues']
                        ?? null
                    )
                        ? $page['queues']
                        : [],
                    static function (
                        mixed $queue
                    ) use (
                        $visibleNodeIds
                    ): bool {
                        if (!is_array($queue)) {
                            return false;
                        }

                        return
                            isset(
                                $visibleNodeIds[
                                    (int) (
                                        $queue[
                                            'node_id'
                                        ]
                                        ?? 0
                                    )
                                ]
                            );
                    }
                )
            );

        foreach (
            [
                'teams',
                'team_nodes',
                'team_queues',
                'team_members',
                'staff_candidates',
            ]
            as $projectWideKey
        ) {
            $page[$projectWideKey] = [];
        }

        $canCreateAny =
            $this->scopes
                ->hasAnyDelegatedActionForProject(
                    $userId,
                    $projectId,
                    ['topology.create']
                );

        $page['access'] = [
            'delegated_scope_mode' =>
                true,

            'allowed_mutations' =>
                $canCreateAny
                    ? [
                        'node.create',
                        'relation.create',
                        'queue.create',
                    ]
                    : [],

            'project_wide_mutations' =>
                false,
        ];

        return $page;
    }

    public function canMutate(
        int $userId,
        array $page,
        string $action,
        array $input
    ): bool {
        $projectId =
            (int) (
                $page['project']['id']
                ?? 0
            );

        if (
            $userId < 1
            || $projectId < 1
        ) {
            return false;
        }

        return match (trim($action)) {
            'node.create' =>
                $this->canCreateNode(
                    $userId,
                    $projectId,
                    $input
                ),

            'relation.create' =>
                $this->canCreateRelation(
                    $userId,
                    $projectId,
                    $page,
                    $input
                ),

            'queue.create' =>
                $this->canCreateQueue(
                    $userId,
                    $projectId,
                    $page,
                    $input
                ),

            default =>
                false,
        };
    }

    private function canCreateNode(
        int $userId,
        int $projectId,
        array $input
    ): bool {
        /*
         * Canonical organization binding belongs to a separate catalog/source
         * authority. Delegated topology writers cannot author that binding.
         */
        if (
            trim(
                (string) (
                    $input[
                        'core_organization_reference'
                    ]
                    ?? ''
                )
            ) !== ''
        ) {
            return false;
        }

        return
            $this->scopes
                ->canAccessResource(
                    $userId,
                    $projectId,
                    'topology.create',
                    trim(
                        (string) (
                            $input[
                                'scope_type_code'
                            ]
                            ?? ''
                        )
                    ),
                    trim(
                        (string) (
                            $input[
                                'scope_reference'
                            ]
                            ?? ''
                        )
                    )
                );
    }

    private function canCreateQueue(
        int $userId,
        int $projectId,
        array $page,
        array $input
    ): bool {
        $node =
            $this->nodeById(
                $page,
                (int) (
                    $input['node_id']
                    ?? 0
                )
            );

        return
            is_array($node)
            &&
            $this->nodeAllowed(
                $userId,
                $projectId,
                'topology.create',
                $node
            );
    }

    private function canCreateRelation(
        int $userId,
        int $projectId,
        array $page,
        array $input
    ): bool {
        $parent =
            $this->nodeById(
                $page,
                (int) (
                    $input[
                        'parent_node_id'
                    ]
                    ?? 0
                )
            );

        $child =
            $this->nodeById(
                $page,
                (int) (
                    $input[
                        'child_node_id'
                    ]
                    ?? 0
                )
            );

        if (
            !is_array($parent)
            || !is_array($child)
        ) {
            return false;
        }

        return
            $this->nodeAllowed(
                $userId,
                $projectId,
                'topology.create',
                $parent
            )
            &&
            $this->nodeAllowed(
                $userId,
                $projectId,
                'topology.create',
                $child
            );
    }

    private function nodeAllowed(
        int $userId,
        int $projectId,
        string $action,
        array $node
    ): bool {
        $scopeType =
            trim(
                (string) (
                    $node[
                        'scope_type_code'
                    ]
                    ?? ''
                )
            );

        $scopeReference =
            trim(
                (string) (
                    $node[
                        'scope_reference'
                    ]
                    ?? ''
                )
            );

        if (
            $scopeType === ''
            || $scopeReference === ''
        ) {
            return false;
        }

        return
            $this->scopes
                ->canAccessResource(
                    $userId,
                    $projectId,
                    $action,
                    $scopeType,
                    $scopeReference
                );
    }

    private function nodeById(
        array $page,
        int $nodeId
    ): ?array {
        if ($nodeId < 1) {
            return null;
        }

        foreach (
            is_array(
                $page['nodes']
                ?? null
            )
                ? $page['nodes']
                : []
            as $node
        ) {
            if (
                is_array($node)
                &&
                (int) (
                    $node['id']
                    ?? 0
                ) === $nodeId
            ) {
                return $node;
            }
        }

        return null;
    }

    private function emptyProjection(
        array $page
    ): array {
        foreach (
            [
                'nodes',
                'relations',
                'teams',
                'queues',
                'team_nodes',
                'team_queues',
                'team_members',
                'staff_candidates',
            ]
            as $key
        ) {
            $page[$key] = [];
        }

        $page['access'] = [
            'delegated_scope_mode' =>
                true,

            'allowed_mutations' =>
                [],

            'project_wide_mutations' =>
                false,
        ];

        return $page;
    }
}
