<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketWorkPolicyRepository;
use App\Services\BaseService;

final class TicketWorkPolicyService extends BaseService
{
    private const ACTION_COLUMNS = [
        'tab.view' =>
            'allow_tab_view',
        'links.view' =>
            'allow_links_view',
        'item.open' =>
            'allow_item_open',
        'item.create_from_ticket' =>
            'allow_item_create_from_ticket',
        'project.select' =>
            'allow_project_select',
    ];

    public function __construct(
        private ?TicketWorkPolicyRepository $repository = null
    ) {
        $this->repository ??=
            new TicketWorkPolicyRepository();
    }

    public function ticketContext(
        string $ticketReference,
        int $userId
    ): ?array {
        if (
            trim($ticketReference) === ''
            || $userId < 1
        ) {
            return null;
        }

        return $this->repository->ticketContext(
            trim($ticketReference),
            'user:' . $userId
        );
    }

    public function resolveDestination(
        array $context
    ): ?array {
        $candidates =
            $this->repository
                ->destinationCandidates(
                    $context
                );

        return $candidates[0]
            ?? null;
    }

    public function destinationCandidates(
        array $context
    ): array {
        return $this->repository
            ->destinationCandidates(
                $context
            );
    }

    public function resolveAccess(
        array $context,
        ?int $workProjectId
    ): array {
        $resolved = [
            'tab.view' => false,
            'links.view' => false,
            'item.open' => false,
            'item.create_from_ticket' => false,
            'project.select' => false,
        ];

        $decided = [];

        foreach (
            $this->repository
                ->accessCandidates(
                    $context,
                    $workProjectId
                )
            as $rule
        ) {
            if (
                !$this->principalMatches(
                    $rule,
                    $context
                )
            ) {
                continue;
            }

            foreach (
                self::ACTION_COLUMNS
                as $action => $column
            ) {
                if (
                    isset($decided[$action])
                    || !array_key_exists(
                        $column,
                        $rule
                    )
                    || $rule[$column] === null
                ) {
                    continue;
                }

                $resolved[$action] =
                    (int) $rule[$column]
                    === 1;

                $decided[$action] =
                    (string) (
                        $rule[
                            'public_reference'
                        ]
                        ?? ''
                    );
            }
        }

        return [
            'actions' => $resolved,
            'matched_rule_references' =>
                $decided,
            'default_effect' => 'deny',
        ];
    }

    private function principalMatches(
        array $rule,
        array $context
    ): bool {
        $type =
            strtolower(
                trim(
                    (string) (
                        $rule[
                            'principal_type_code'
                        ]
                        ?? ''
                    )
                )
            );

        $reference =
            trim(
                (string) (
                    $rule[
                        'principal_reference'
                    ]
                    ?? ''
                )
            );

        return match ($type) {
            'user' =>
                $reference !== ''
                && hash_equals(
                    $reference,
                    (string) (
                        $context[
                            'actor_user_reference'
                        ]
                        ?? ''
                    )
                ),

            'project_role' =>
                $reference !== ''
                && hash_equals(
                    $reference,
                    (string) (
                        $context[
                            'actor_project_role_code'
                        ]
                        ?? ''
                    )
                ),

            'team_role' =>
                $reference !== ''
                && hash_equals(
                    $reference,
                    (string) (
                        $context[
                            'actor_staff_role_code'
                        ]
                        ?? ''
                    )
                ),

            'current_assignee' =>
                !empty(
                    $context[
                        'actor_is_current_assignee'
                    ]
                ),

            'project_member' =>
                (int) (
                    $context[
                        'actor_project_member_id'
                    ]
                    ?? 0
                ) > 0,

            'team_member' =>
                (int) (
                    $context[
                        'actor_team_member_id'
                    ]
                    ?? 0
                ) > 0,

            'any_staff' =>
                (int) (
                    $context[
                        'actor_team_member_id'
                    ]
                    ?? 0
                ) > 0
                || in_array(
                    (string) (
                        $context[
                            'actor_project_role_code'
                        ]
                        ?? ''
                    ),
                    [
                        'member',
                        'manager',
                    ],
                    true
                ),

            default =>
                false,
        };
    }
}
