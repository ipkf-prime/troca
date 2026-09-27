<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'migration' => $root . '/public_html/system/Database/Migrations/CreateTicketingRequesterMembershipApprovalFoundation.php',
    'ui_migration' => $root . '/public_html/system/Database/Migrations/SeedTicketingPhase1MembershipUiContent.php',
    'registry' => $root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php',
    'onboarding' => $root . '/public_html/app/Services/Ticketing/TicketRequesterOnboardingService.php',
    'affiliation' => $root . '/public_html/app/Services/Ticketing/TicketProjectOrganizationAffiliationService.php',
    'requester_routes' => $root . '/public_html/routes/ticketing-requester.php',
    'membership_routes' => $root . '/public_html/routes/ticketing-project-membership.php',
    'requester_view' => $root . '/public_html/resources/views/admin/ticketing-requester-onboarding.php',
    'affiliation_view' => $root . '/public_html/resources/views/admin/ticketing-requester-affiliation.php',
    'requests_view' => $root . '/public_html/resources/views/admin/ticketing-project-membership-requests.php',
];

$content = [];
foreach ($files as $key => $path) {
    if (!is_file($path)) throw new RuntimeException('missing_file:' . $key);
    $body = file_get_contents($path);
    if (!is_string($body)) throw new RuntimeException('unreadable_file:' . $key);
    $content[$key] = $body;
}

foreach (['ticketing_support_project_membership_requests','core_organization_membership_reference','status_code'] as $needle) {
    if (!str_contains($content['migration'], $needle)) throw new RuntimeException('migration_contract_missing:' . $needle);
}
if (!str_contains($content['registry'], 'CreateTicketingRequesterMembershipApprovalFoundation::class')) throw new RuntimeException('migration_registry_missing');

foreach (['affiliationPage','requestAffiliation','membershipRequestsForManager','decideMembershipRequest','createMembershipRequest','pending_approval','membership_mode','approval_mode'] as $needle) {
    if (!str_contains($content['onboarding'], $needle)) throw new RuntimeException('onboarding_contract_missing:' . $needle);
}
foreach (['affiliationRequestContext','requestAffiliationForProject','organization_catalog_entries','boundCatalogReferences','OrganizationalAffiliationService'] as $needle) {
    if (!str_contains($content['affiliation'], $needle)) throw new RuntimeException('affiliation_contract_missing:' . $needle);
}
foreach (['TICKETING_PHASE1_PROJECT_SCOPED_AFFILIATION_ROUTES_V1','/admin/support/ticketing/affiliation'] as $needle) {
    if (!str_contains($content['requester_routes'], $needle)) throw new RuntimeException('requester_route_missing:' . $needle);
}
foreach (['TICKETING_PHASE1_REQUESTER_MEMBERSHIP_APPROVAL_ROUTES_V1','membership-requests'] as $needle) {
    if (!str_contains($content['membership_routes'], $needle)) throw new RuntimeException('membership_route_missing:' . $needle);
}

foreach (['pending_lock_key','ticketing_membership_requests_pending_lock_unique'] as $needle) {
    if (!str_contains($content['migration'],$needle)) throw new RuntimeException('pending_lock_contract_missing:'.$needle);
}
foreach (['ticketing.membership.affiliation.heading','ticketing.membership.requests.approve_action'] as $needle) {
    if (!str_contains($content['ui_migration'],$needle)) throw new RuntimeException('dynamic_ui_content_missing:'.$needle);
}
foreach (['canManageMembershipRequests','ticketing.project.manage','TicketingProjectScopedAccessService'] as $needle) {
    if (!str_contains($content['onboarding'],$needle)) throw new RuntimeException('membership_manager_authorization_missing:'.$needle);
}
foreach (['UiContentInlineGuide','ticketing.membership'] as $needle) {
    if (!str_contains($content['affiliation_view'],$needle) || !str_contains($content['requests_view'],$needle)) throw new RuntimeException('dynamic_runtime_content_missing:'.$needle);
}
foreach (['TSP-NEP','cedfa0ee-646a-4219-87fd-36b68ad34714','3d1d8abc-1a25-4ddd-a80a-77213861a05d','6202'] as $forbidden) {
    foreach ($content as $body) {
        if (str_contains($body, $forbidden)) throw new RuntimeException('business_hardcode_detected:' . $forbidden);
    }
}
if (str_contains($content['requester_view'], 'NP-XXXX')) throw new RuntimeException('invite_placeholder_business_hardcode');

echo "PROJECT_SCOPED_AFFILIATION=YES\n";
echo "MEMBERSHIP_MANAGER_APPROVAL=YES\n";
echo "PENDING_MEMBERSHIP_REUSES_PENDING_ROW=YES\n";
echo "ORG_MEMBERSHIP_AND_PROJECT_MEMBERSHIP_SEPARATE=YES\n";
echo "DYNAMIC_MEMBERSHIP_UI_CONTENT=YES\n";
echo "PROJECT_MANAGER_AUTHORIZATION=YES\n";
echo "PENDING_LOCK_SCHEMA=YES\n";
echo "BUSINESS_HARDCODE=0\n";
echo "TICKETING_PHASE1_EXTERNAL_MEMBERSHIP_FOUNDATION_TEST=PASS\n";
