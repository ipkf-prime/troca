<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

/**
 * TICKETING_GENERIC_PROJECT_LOCAL_ADMIN_AUTHORITY_V1
 *
 * Pure project-local administration policy.
 *
 * Critical boundaries:
 * - Global/system administration remains owned by Core/global RBAC.
 * - A local project manager never receives a global permission alias.
 * - This service never opens a global /admin/ticketing/projects surface.
 * - Project and Realm identity must be resolved before this policy runs.
 * - Request payload alone is never an authoritative resource context.
 *
 * A4 route integration must build $resourceContext from trusted Ticketing
 * repository lookups before calling this policy.
 */
final class TicketingProjectLocalAdminAuthorityService
{
    private const ACTIONS = [
        'topology.view',
        'topology.create',
        'topology.update',
        'topology.delete',
    ];

    private TicketingProjectLocalAdminScopeProviderInterface $scopes;

    public function __construct(
        ?TicketingProjectLocalAdminScopeProviderInterface $scopes = null
    ) {
        $this->scopes =
            $scopes
            ?? new TicketingProjectScopedAdminScopeProvider();
    }

    /**
     * Required trusted resourceContext keys:
     *
     * realm_project_id:
     *     authoritative owning project of the Realm.
     *
     * resource_project_id:
     *     authoritative owning project of the topology resource/context.
     *
     * resource_realm_id:
     *     authoritative Realm of the topology resource/context.
     *
     * For topology.create, A4 must resolve the selected Realm first and
     * pass its project/realm identity as the resource context.
     */
    public function can(
        int $userId,
        int $projectId,
        int $realmId,
        string $action,
        array $resourceContext
    ): bool {
        if (
            $userId < 1
            || $projectId < 1
            || $realmId < 1
        ) {
            return false;
        }

        $action =
            strtolower(
                trim($action)
            );

        if (
            !in_array(
                $action,
                self::ACTIONS,
                true
            )
        ) {
            return false;
        }

        $realmProjectId =
            (int) (
                $resourceContext[
                    'realm_project_id'
                ]
                ?? 0
            );

        $resourceProjectId =
            (int) (
                $resourceContext[
                    'resource_project_id'
                ]
                ?? 0
            );

        $resourceRealmId =
            (int) (
                $resourceContext[
                    'resource_realm_id'
                ]
                ?? 0
            );

        /*
         * Fail closed unless Project + Realm ownership has already been
         * resolved and all identities agree.
         */
        if (
            $realmProjectId < 1
            || $resourceProjectId < 1
            || $resourceRealmId < 1
            || $realmProjectId !== $projectId
            || $resourceProjectId !== $projectId
            || $resourceRealmId !== $realmId
        ) {
            return false;
        }

        $scope =
            $this->scopes->projectScope(
                $userId,
                $projectId
            );

        if (!is_array($scope)) {
            return false;
        }

        /*
         * Do not trust only the provider lookup key. The returned scope
         * must explicitly identify the same project.
         */
        if (
            (int) (
                $scope[
                    'project_id'
                ]
                ?? 0
            ) !== $projectId
        ) {
            return false;
        }

        /*
         * A local administration surface is manager-only in A3.
         * Non-manager scoped grants remain resource/data visibility tools;
         * they are not promoted into configuration authority here.
         */
        if (
            empty(
                $scope[
                    'is_project_manager'
                ]
            )
        ) {
            return false;
        }

        /*
         * Topology administration additionally requires the canonical
         * project scope to expose full project topology. This keeps an
         * inconsistent or partial manager projection fail-closed.
         */
        if (
            empty(
                $scope[
                    'topology_full_project'
                ]
            )
        ) {
            return false;
        }

        return true;
    }
}
