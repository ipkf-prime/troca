<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

interface TicketingProjectMemberScopeRuntimeInterface
{
    public function canAccessResource(
        int $userId,
        int $projectId,
        string $action,
        string $scopeTypeCode,
        string $scopeReference
    ): bool;

    public function hasAnyDelegatedActionForProject(
        int $userId,
        int $projectId,
        array $actions
    ): bool;
}
