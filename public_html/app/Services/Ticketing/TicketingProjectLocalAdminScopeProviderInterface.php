<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

/**
 * Narrow provider contract for project-local administration.
 *
 * This is intentionally Ticketing-owned. It does not project or alias
 * Core/global permissions into project-local administration.
 */
interface TicketingProjectLocalAdminScopeProviderInterface
{
    public function projectScope(
        int $userId,
        int $projectId
    ): ?array;
}
