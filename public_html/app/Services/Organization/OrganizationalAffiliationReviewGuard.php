<?php

declare(strict_types=1);

namespace App\Services\Organization;

use RuntimeException;

/** A claimant must not approve or reject their own organizational claim. */
final class OrganizationalAffiliationReviewGuard
{
    public static function assertIndependentActor(int $actorUserId, int $claimantUserId): void
    {
        if ($actorUserId <= 0 || $claimantUserId <= 0 || $actorUserId === $claimantUserId) {
            throw new RuntimeException('affiliation_independent_reviewer_required');
        }
    }
}
