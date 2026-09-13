<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\SupportTopologyAdminRepository;

/**
 * TICKETING_PROJECT_LOCAL_TOPOLOGY_CONTEXT_V1
 *
 * Resolves the Project + default Realm identity from authoritative
 * Ticketing tables before the project-local administration policy runs.
 *
 * No request payload value is trusted as Project/Realm ownership.
 */
final class TicketingProjectLocalTopologyContextService
{
    public function __construct(
        private ?SupportTopologyAdminRepository $topology = null,
        private ?TicketingProjectLocalAdminAuthorityService $authority = null,
        private ?TicketingProjectMemberScopeRuntimeInterface $delegated = null
    ) {
        $this->topology ??=
            new SupportTopologyAdminRepository();

        $this->authority ??=
            new TicketingProjectLocalAdminAuthorityService();

        $this->delegated ??=
            new TicketingProjectMemberScopeRuntimeService();
    }

    public function context(
        string $projectReference
    ): ?array {
        $resolved =
            $this->topology
                ->projectDefaultRealmContextByReference(
                    trim($projectReference)
                );

        if (!is_array($resolved)) {
            return null;
        }

        $projectId =
            (int) (
                $resolved['project_id']
                ?? 0
            );

        $realmId =
            (int) (
                $resolved['realm_id']
                ?? 0
            );

        $realmProjectId =
            (int) (
                $resolved['realm_project_id']
                ?? 0
            );

        if (
            $projectId < 1
            || $realmId < 1
            || $realmProjectId !== $projectId
        ) {
            return null;
        }

        return [
            'project_id' =>
                $projectId,

            'realm_id' =>
                $realmId,

            'resource_context' => [
                'realm_project_id' =>
                    $realmProjectId,

                'resource_project_id' =>
                    $projectId,

                'resource_realm_id' =>
                    $realmId,
            ],
        ];
    }

    public function canAccess(
        int $userId,
        string $projectReference,
        string $action
    ): bool {
        if ($userId < 1) {
            return false;
        }

        $context =
            $this->context(
                $projectReference
            );

        if (!is_array($context)) {
            return false;
        }

        return
            $this->authority->can(
                $userId,
                (int) $context['project_id'],
                (int) $context['realm_id'],
                trim($action),
                (array) $context[
                    'resource_context'
                ]
            );
    }

    /*
     * TICKETING_SCOPED_TOPOLOGY_ROUTE_ENTRY_V1
     *
     * Full project-manager authority remains first. Delegated scopes may
     * enter only the exact topology route, and concrete mutation targets
     * are re-authorized later.
     */
    public function canRouteEntry(
        int $userId,
        string $projectReference,
        string $action
    ): bool {
        if (
            $this->canAccess(
                $userId,
                $projectReference,
                $action
            )
        ) {
            return true;
        }

        $context =
            $this->context(
                $projectReference
            );

        if (!is_array($context)) {
            return false;
        }

        $projectId =
            (int) (
                $context['project_id']
                ?? 0
            );

        if ($projectId < 1) {
            return false;
        }

        $action =
            trim($action);

        if ($action === 'topology.view') {
            return
                $this->delegated
                    ->hasAnyDelegatedActionForProject(
                        $userId,
                        $projectId,
                        ['topology.view']
                    );
        }

        if ($action === 'topology.create') {
            return
                $this->delegated
                    ->hasAnyDelegatedActionForProject(
                        $userId,
                        $projectId,
                        ['topology.create']
                    );
        }

        return false;
    }

}
