<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

/**
 * Adapter from the existing canonical Ticketing project-scope bridge
 * into the project-local administration policy.
 *
 * The underlying scope is still produced by Ticketing membership,
 * team membership and topology semantics.
 */
final class TicketingProjectScopedAdminScopeProvider
    implements TicketingProjectLocalAdminScopeProviderInterface
{
    private TicketingProjectScopedAccessService $access;

    public function __construct(
        ?TicketingProjectScopedAccessService $access = null
    ) {
        $this->access =
            $access
            ?? new TicketingProjectScopedAccessService();
    }

    public function projectScope(
        int $userId,
        int $projectId
    ): ?array {
        if (
            $userId < 1
            || $projectId < 1
        ) {
            return null;
        }

        return
            $this->access->projectScope(
                $userId,
                $projectId
            );
    }
}
