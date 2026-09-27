<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/public_html/app/Services/Organization/OrganizationalAffiliationReviewGuard.php';

use App\Services\Organization\OrganizationalAffiliationReviewGuard as Guard;

foreach ([[5, 5], [0, 5], [5, 0], [-1, 5]] as [$actor, $claimant]) {
    try {
        Guard::assertIndependentActor($actor, $claimant);
        throw new RuntimeException('UNEXPECTED_ACCEPTANCE');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'affiliation_independent_reviewer_required') {
            throw $e;
        }
    }
}

Guard::assertIndependentActor(6, 5);

$service = file_get_contents(dirname(__DIR__)
    . '/public_html/app/Services/Organization/OrganizationalAffiliationService.php');
if (!is_string($service)) {
    throw new RuntimeException('SERVICE_NOT_FOUND');
}
$decisionStart = strpos($service, 'public function decide(');
$guardCall = strpos($service, 'OrganizationalAffiliationReviewGuard::assertIndependentActor(', $decisionStart);
$managementCheck = strpos($service, '$this->canManageOrganization(', $decisionStart);
$approvalWrite = strpos($service, "verification_state_code =\n                                'verified'", $decisionStart);
if ($decisionStart === false || $guardCall === false || $managementCheck === false
    || $approvalWrite === false || !($decisionStart < $guardCall
    && $guardCall < $managementCheck && $managementCheck < $approvalWrite)) {
    throw new RuntimeException('DECISION_GUARD_ORDER_INVALID');
}

echo "ORGANIZATIONAL_AFFILIATION_REVIEW_GUARD=PASS\n";
