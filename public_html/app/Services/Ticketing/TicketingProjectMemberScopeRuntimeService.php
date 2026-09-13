<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketingDelegatedScopeRepository;
use Throwable;

/**
 * TICKETING_PROJECT_MEMBER_SCOPE_RUNTIME_BINDING_V1
 *
 * Runtime composition:
 *
 * project membership scope rows
 *   + project-defined dimension/value hierarchy
 *   + pure A5 delegated-scope evaluator
 *
 * This service is intentionally NOT wired into a mutation route yet.
 * The next integration stage must resolve the concrete target resource
 * before allowing any scoped topology mutation.
 */
final class TicketingProjectMemberScopeRuntimeService
    implements TicketingProjectMemberScopeRuntimeInterface
{
    private TicketingDelegatedScopeRepositoryInterface $repository;

    private TicketingScopedTopologyAdminEvaluator $evaluator;

    public function __construct(
        ?TicketingDelegatedScopeRepositoryInterface $repository = null,
        ?TicketingScopedTopologyAdminEvaluator $evaluator = null
    ) {
        $this->repository =
            $repository
            ?? new TicketingDelegatedScopeRepository();

        $this->evaluator =
            $evaluator
            ?? new TicketingScopedTopologyAdminEvaluator();
    }

    public function canAccessResource(
        int $userId,
        int $projectId,
        string $action,
        string $scopeTypeCode,
        string $scopeReference
    ): bool {
        if (
            $userId < 1
            || $projectId < 1
        ) {
            return false;
        }

        try {
            $scopes =
                $this->repository
                    ->delegatedScopesForUserProject(
                        $userId,
                        $projectId
                    );

            if ($scopes === []) {
                return false;
            }

            $resource =
                $this->repository
                    ->resourceScopeForProject(
                        $projectId,
                        trim($scopeTypeCode),
                        trim($scopeReference)
                    );

            if (!is_array($resource)) {
                return false;
            }

            return
                $this->evaluator->allows(
                    $scopes,
                    trim($action),
                    $resource
                );

        } catch (Throwable) {
            return false;
        }
    }

    /*
     * TICKETING_DELEGATED_SCOPE_ROUTE_ENTRY_V1
     *
     * Coarse route admission only. Concrete target authorization occurs
     * later in TicketingScopedSupportTopologyService.
     */
    public function hasAnyDelegatedActionForProject(
        int $userId,
        int $projectId,
        array $actions
    ): bool {
        if (
            $userId < 1
            || $projectId < 1
        ) {
            return false;
        }

        $actions =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn (
                                mixed $action
                            ): string =>
                                trim(
                                    (string) $action
                                ),
                            $actions
                        ),
                        static fn (
                            string $action
                        ): bool =>
                            $action !== ''
                    )
                )
            );

        if ($actions === []) {
            return false;
        }

        try {
            $scopes =
                $this->repository
                    ->delegatedScopesForUserProject(
                        $userId,
                        $projectId
                    );

            foreach ($scopes as $scope) {
                if (!is_array($scope)) {
                    continue;
                }

                $scopeType =
                    trim(
                        (string) (
                            $scope[
                                'scope_type_code'
                            ]
                            ?? ''
                        )
                    );

                $scopeReference =
                    trim(
                        (string) (
                            $scope[
                                'scope_reference'
                            ]
                            ?? ''
                        )
                    );

                if (
                    $scopeType === ''
                    || $scopeReference === ''
                ) {
                    continue;
                }

                $resource =
                    $this->repository
                        ->resourceScopeForProject(
                            $projectId,
                            $scopeType,
                            $scopeReference
                        );

                if (!is_array($resource)) {
                    continue;
                }

                foreach ($actions as $action) {
                    if (
                        $this->evaluator->allows(
                            [$scope],
                            $action,
                            $resource
                        )
                    ) {
                        return true;
                    }
                }
            }

        } catch (Throwable) {
            return false;
        }

        return false;
    }

}
