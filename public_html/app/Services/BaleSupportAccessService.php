<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SupportProjectRepository;
use App\Services\Ticketing\TicketRequesterOnboardingService;
use App\Services\Ticketing\TicketService;
use IPKF\Database\Connections\ConnectionResolver;

/**
 * Canonical Bale -> Ticketing access boundary.
 *
 * This service intentionally owns no Ticketing authorization policy.
 * It composes the existing canonical Ticketing services and repositories.
 *
 * It must not:
 * - query Ticketing tables directly;
 * - infer access from Bale identity alone;
 * - duplicate requester/staff/project/service/ticket authorization;
 * - contain UI content, permission names, project identifiers or UI routes.
 */
final class BaleSupportAccessService
{
    private SupportProjectRepository $projects;

    private TicketRequesterOnboardingService $onboarding;

    private TicketService $tickets;

    public function __construct(
        ?ConnectionResolver $connections = null,
        ?SupportProjectRepository $projects = null,
        ?TicketRequesterOnboardingService $onboarding = null,
        ?TicketService $tickets = null
    ) {
        $connections ??= new ConnectionResolver();

        $this->projects =
            $projects
            ?? new SupportProjectRepository(
                $connections
            );

        $this->onboarding =
            $onboarding
            ?? new TicketRequesterOnboardingService(
                $connections
            );

        $this->tickets =
            $tickets
            ?? new TicketService();
    }

    public function hasRequesterAccess(
        int $userId
    ): bool {
        if ($userId < 1) {
            return false;
        }

        return
            $this->onboarding
                ->hasMembership(
                    $userId
                );
    }

    public function hasStaffAccess(
        int $userId
    ): bool {
        if ($userId < 1) {
            return false;
        }

        return
            $this->onboarding
                ->hasStaffMembership(
                    $userId
                );
    }

    public function projectsForUser(
        int $userId
    ): array {
        if ($userId < 1) {
            return [];
        }

        return
            $this->projects
                ->forUser(
                    $this->userReference(
                        $userId
                    )
                );
    }

    public function projectForUser(
        string $projectReference,
        int $userId
    ): ?array {
        $projectReference =
            trim($projectReference);

        if (
            $userId < 1
            || $projectReference === ''
        ) {
            return null;
        }

        return
            $this->projects
                ->projectForUser(
                    $projectReference,
                    $this->userReference(
                        $userId
                    )
                );
    }

    public function serviceForUser(
        string $projectReference,
        string $serviceReference,
        int $userId
    ): ?array {
        $projectReference =
            trim($projectReference);

        $serviceReference =
            trim($serviceReference);

        if (
            $userId < 1
            || $projectReference === ''
            || $serviceReference === ''
        ) {
            return null;
        }

        return
            $this->projects
                ->serviceForUser(
                    $projectReference,
                    $serviceReference,
                    $this->userReference(
                        $userId
                    )
                );
    }

    public function ticketForUser(
        string $ticketReference,
        int $userId
    ): ?array {
        $ticketReference =
            trim($ticketReference);

        if (
            $userId < 1
            || $ticketReference === ''
        ) {
            return null;
        }

        $detail =
            $this->tickets
                ->detailForUser(
                    $ticketReference,
                    $userId
                );

        if (
            !is_array($detail)
            || !is_array(
                $detail['ticket']
                ?? null
            )
        ) {
            return null;
        }

        return $detail;
    }

    /**
     * Existing Ticketing persisted user-reference contract.
     * This is identity serialization, not an authorization decision.
     */
    private function userReference(
        int $userId
    ): string {
        return
            'user:'
            . $userId;
    }
}
