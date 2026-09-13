<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

/**
 * Read-only provider contract for delegated project-member scopes.
 *
 * The implementation owns storage lookups only. Authorization semantics
 * remain in TicketingScopedTopologyAdminEvaluator.
 */
interface TicketingDelegatedScopeRepositoryInterface
{
    public function delegatedScopesForUserProject(
        int $userId,
        int $projectId
    ): array;

    public function resourceScopeForProject(
        int $projectId,
        string $scopeTypeCode,
        string $scopeReference
    ): ?array;
}
