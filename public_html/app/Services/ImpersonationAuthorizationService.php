<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ImpersonationAuthorizationRepository;
use Throwable;

final class ImpersonationAuthorizationService
{
    public const PERMISSION =
        'users.impersonate';

    private const SCOPE_TYPES = [
        'global',
        'national',
        'province',
        'county',
        'district',
        'village',
        'city',
        'organization',
        'company',
        'warehouse',
        'center',
        'org_unit',
        'project',
        'own',
        'assigned',
    ];

    private const LOCAL_SCOPES = [
        'own',
        'assigned',
    ];

    private const GEOGRAPHIC_SCOPES = [
        'province',
        'county',
        'district',
        'village',
        'city',
    ];

    private ?ImpersonationAuthorizationRepository
        $repository;


    public function __construct(
        ?ImpersonationAuthorizationRepository
            $repository = null
    ) {
        $this->repository =
            $repository;
    }


    public function decide(
        int $actorUserId,
        int $targetUserId,
        int $actorAssignmentId
    ): array {
        if (
            $actorUserId < 1
            || $targetUserId < 1
            || $actorAssignmentId < 1
        ) {
            return $this->deny(
                'invalid_request'
            );
        }

        if (
            $actorUserId
            === $targetUserId
        ) {
            return $this->deny(
                'same_user'
            );
        }

        try {
            $repository =
                $this->repository
                ??= new
                    ImpersonationAuthorizationRepository();

            $assignment =
                $repository->actorAssignment(
                    $actorUserId,
                    $actorAssignmentId
                );

            if ($assignment === null) {
                return $this->deny(
                    'actor_assignment_invalid'
                );
            }

            $targetUser =
                $repository->targetUser(
                    $targetUserId
                );

            if ($targetUser === null) {
                return $this->deny(
                    'target_ineligible'
                );
            }

            return $this->evaluate(
                [
                    'user_id' =>
                        $actorUserId,

                    'permission_allowed' =>
                        $repository
                            ->permissionForAssignment(
                                $actorUserId,
                                $actorAssignmentId,
                                self::PERMISSION
                            ),

                    'can_manage_other_users' =>
                        (bool) (
                            $assignment[
                                'can_manage_other_users'
                            ]
                            ?? false
                        ),

                    'priority' =>
                        (int) (
                            $assignment[
                                'priority'
                            ]
                            ?? 0
                        ),

                    'scopes' =>
                        $repository
                            ->scopesForAssignment(
                                $actorAssignmentId
                            ),
                ],
                [
                    'user_id' =>
                        $targetUserId,

                    'eligible' =>
                        (bool) (
                            $targetUser[
                                'eligible'
                            ]
                            ?? false
                        ),

                    'assignments' =>
                        $repository
                            ->activeAssignmentsForUser(
                                $targetUserId
                            ),
                ]
            );
        } catch (Throwable) {
            return $this->deny(
                'authorization_context_unavailable'
            );
        }
    }


    public function evaluate(
        array $actor,
        array $target
    ): array {
        $actorUserId =
            (int) (
                $actor['user_id']
                ?? 0
            );

        $targetUserId =
            (int) (
                $target['user_id']
                ?? 0
            );

        if (
            $actorUserId < 1
            || $targetUserId < 1
        ) {
            return $this->deny(
                'invalid_request'
            );
        }

        if (
            $actorUserId
            === $targetUserId
        ) {
            return $this->deny(
                'same_user'
            );
        }

        if (
            empty(
                $actor[
                    'permission_allowed'
                ]
            )
        ) {
            return $this->deny(
                'permission_denied'
            );
        }

        if (
            empty(
                $actor[
                    'can_manage_other_users'
                ]
            )
        ) {
            return $this->deny(
                'actor_not_manager'
            );
        }

        if (
            empty(
                $target[
                    'eligible'
                ]
            )
        ) {
            return $this->deny(
                'target_ineligible'
            );
        }

        $actorPriority =
            (int) (
                $actor['priority']
                ?? 0
            );

        if ($actorPriority < 1) {
            return $this->deny(
                'actor_assignment_invalid'
            );
        }

        $targetAssignments =
            $target['assignments']
            ?? null;

        if (
            !is_array(
                $targetAssignments
            )
            || $targetAssignments === []
        ) {
            return $this->deny(
                'target_assignment_missing'
            );
        }

        foreach (
            $targetAssignments
            as $assignment
        ) {
            if (!is_array($assignment)) {
                return $this->deny(
                    'target_assignment_invalid'
                );
            }

            $priority =
                (int) (
                    $assignment[
                        'priority'
                    ]
                    ?? 0
                );

            if (
                $priority < 1
                || $priority
                    >= $actorPriority
            ) {
                return $this->deny(
                    'target_role_not_lower'
                );
            }

            if (
                !isset(
                    $assignment[
                        'scopes'
                    ]
                )
                || !is_array(
                    $assignment[
                        'scopes'
                    ]
                )
            ) {
                return $this->deny(
                    'target_scope_invalid'
                );
            }

            if (
                !empty(
                    $assignment[
                        'requires_scope'
                    ]
                )
                && $assignment[
                    'scopes'
                ] === []
            ) {
                return $this->deny(
                    'target_scope_missing'
                );
            }
        }

        $actorScopes =
            $this->normalizeScopes(
                $actor['scopes']
                ?? null
            );

        if (
            $actorScopes === null
            || $actorScopes === []
        ) {
            return $this->deny(
                'actor_scope_invalid'
            );
        }

        $actorAllows = [];
        $actorDenies = [];

        foreach (
            $actorScopes
            as $scope
        ) {
            if (
                $this->isLocalScope(
                    $scope[
                        'scope_type_code'
                    ]
                )
            ) {
                continue;
            }

            if (
                $scope[
                    'effect_code'
                ] === 'deny'
            ) {
                $actorDenies[] =
                    $scope;
            } else {
                $actorAllows[] =
                    $scope;
            }
        }

        if ($actorAllows === []) {
            return $this->deny(
                'actor_scope_invalid'
            );
        }

        $targetContexts = [];

        foreach (
            $targetAssignments
            as $assignment
        ) {
            $scopes =
                $this->normalizeScopes(
                    $assignment[
                        'scopes'
                    ]
                );

            if ($scopes === null) {
                return $this->deny(
                    'target_scope_invalid'
                );
            }

            foreach ($scopes as $scope) {
                if (
                    $scope[
                        'effect_code'
                    ] !== 'allow'
                ) {
                    continue;
                }

                if (
                    $this->isLocalScope(
                        $scope[
                            'scope_type_code'
                        ]
                    )
                ) {
                    continue;
                }

                $targetContexts[] =
                    $scope;
            }
        }

        if ($targetContexts === []) {
            if (
                $this->hasGlobalAllow(
                    $actorAllows
                )
            ) {
                return $this->allow();
            }

            return $this->deny(
                'target_scope_unresolved'
            );
        }

        foreach (
            $targetContexts
            as $targetContext
        ) {
            foreach (
                $actorDenies
                as $actorDeny
            ) {
                if (
                    $this->scopeCovers(
                        $actorDeny,
                        $targetContext
                    )
                ) {
                    return $this->deny(
                        'actor_scope_denied'
                    );
                }
            }

            $covered = false;

            foreach (
                $actorAllows
                as $actorAllow
            ) {
                if (
                    $this->scopeCovers(
                        $actorAllow,
                        $targetContext
                    )
                ) {
                    $covered = true;
                    break;
                }
            }

            if (!$covered) {
                return $this->deny(
                    'target_scope_outside_actor'
                );
            }
        }

        return $this->allow();
    }


    private function normalizeScopes(
        mixed $scopes
    ): ?array {
        if (!is_array($scopes)) {
            return null;
        }

        $result = [];

        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                return null;
            }

            $type =
                strtolower(
                    trim(
                        (string) (
                            $scope[
                                'scope_type_code'
                            ]
                            ?? ''
                        )
                    )
                );

            $reference =
                trim(
                    (string) (
                        $scope[
                            'scope_reference'
                        ]
                        ?? ''
                    )
                );

            $effect =
                strtolower(
                    trim(
                        (string) (
                            $scope[
                                'effect_code'
                            ]
                            ?? ''
                        )
                    )
                );

            if (
                !in_array(
                    $type,
                    self::SCOPE_TYPES,
                    true
                )
                || !in_array(
                    $effect,
                    [
                        'allow',
                        'deny',
                    ],
                    true
                )
            ) {
                return null;
            }

            if (
                $type !== 'global'
                && $reference === ''
            ) {
                return null;
            }

            if (
                $type === 'global'
                && $reference === ''
            ) {
                $reference = '*';
            }

            $aliases =
                $scope[
                    'reference_aliases'
                ]
                ?? [
                    $reference,
                ];

            if (!is_array($aliases)) {
                return null;
            }

            $safeAliases = [];

            foreach ($aliases as $alias) {
                $alias =
                    trim(
                        (string) $alias
                    );

                if ($alias === '') {
                    continue;
                }

                if (
                    !in_array(
                        $alias,
                        $safeAliases,
                        true
                    )
                ) {
                    $safeAliases[] =
                        $alias;
                }
            }

            if (
                $reference !== ''
                && !in_array(
                    $reference,
                    $safeAliases,
                    true
                )
            ) {
                $safeAliases[] =
                    $reference;
            }

            $ancestors =
                $scope[
                    'ancestors'
                ]
                ?? [];

            if (!is_array($ancestors)) {
                return null;
            }

            $safeAncestors = [];

            foreach (
                $ancestors
                as $ancestorType =>
                    $ancestorValues
            ) {
                $ancestorType =
                    strtolower(
                        trim(
                            (string)
                                $ancestorType
                        )
                    );

                if (
                    !in_array(
                        $ancestorType,
                        self::GEOGRAPHIC_SCOPES,
                        true
                    )
                ) {
                    return null;
                }

                if (
                    !is_array(
                        $ancestorValues
                    )
                ) {
                    $ancestorValues = [
                        $ancestorValues,
                    ];
                }

                $values = [];

                foreach (
                    $ancestorValues
                    as $value
                ) {
                    $value =
                        trim(
                            (string) $value
                        );

                    if (
                        $value !== ''
                        && !in_array(
                            $value,
                            $values,
                            true
                        )
                    ) {
                        $values[] =
                            $value;
                    }
                }

                if ($values === []) {
                    return null;
                }

                $safeAncestors[
                    $ancestorType
                ] =
                    $values;
            }

            $result[] = [
                'scope_type_code' =>
                    $type,

                'scope_reference' =>
                    $reference,

                'effect_code' =>
                    $effect,

                'include_descendants' =>
                    !empty(
                        $scope[
                            'include_descendants'
                        ]
                    ),

                'reference_aliases' =>
                    $safeAliases,

                'ancestors' =>
                    $safeAncestors,
            ];
        }

        return $result;
    }


    private function scopeCovers(
        array $actorScope,
        array $targetScope
    ): bool {
        $actorType =
            $actorScope[
                'scope_type_code'
            ];

        $actorReference =
            $actorScope[
                'scope_reference'
            ];

        $targetType =
            $targetScope[
                'scope_type_code'
            ];

        if ($actorType === 'global') {
            return true;
        }

        if ($actorType === $targetType) {
            if (
                $actorReference === '*'
            ) {
                return true;
            }

            return in_array(
                $actorReference,
                $targetScope[
                    'reference_aliases'
                ],
                true
            );
        }

        if (
            empty(
                $actorScope[
                    'include_descendants'
                ]
            )
            || !in_array(
                $actorType,
                self::GEOGRAPHIC_SCOPES,
                true
            )
        ) {
            return false;
        }

        $ancestorValues =
            $targetScope[
                'ancestors'
            ][
                $actorType
            ]
            ?? [];

        return
            $actorReference === '*'
            || in_array(
                $actorReference,
                $ancestorValues,
                true
            );
    }


    private function hasGlobalAllow(
        array $scopes
    ): bool {
        foreach ($scopes as $scope) {
            if (
                $scope[
                    'scope_type_code'
                ] === 'global'
            ) {
                return true;
            }
        }

        return false;
    }


    private function isLocalScope(
        string $type
    ): bool {
        return in_array(
            $type,
            self::LOCAL_SCOPES,
            true
        );
    }


    private function allow(): array
    {
        return [
            'allowed' =>
                true,

            'reason_code' =>
                'allowed',
        ];
    }


    private function deny(
        string $reasonCode
    ): array {
        return [
            'allowed' =>
                false,

            'reason_code' =>
                $reasonCode,
        ];
    }
}
