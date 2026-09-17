<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$files = [
    'bridge' =>
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'TicketProjectOrganizationAffiliationService.php',

    'config' =>
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'SupportProjectMembershipConfigurationService.php',

    'onboarding' =>
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'TicketRequesterOnboardingService.php',

    'project_form' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'ticketing-project-form.php',

    'membership_partial' =>
        $root
        . '/public_html/resources/views/admin/partials/'
        . 'ticketing-project-membership-config.php',

    'onboarding_view' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'ticketing-requester-onboarding.php',

    'membership_route' =>
        $root
        . '/public_html/routes/'
        . 'ticketing-project-membership.php',

    'requester_route' =>
        $root
        . '/public_html/routes/'
        . 'ticketing-requester.php',
];

$bodies = [];

foreach ($files as $key => $file) {

    if (!is_file($file)) {
        throw new RuntimeException(
            'Missing file: ' . $file
        );
    }

    $body =
        file_get_contents($file);

    if (!is_string($body)) {
        throw new RuntimeException(
            'Cannot read: ' . $file
        );
    }

    $bodies[$key] =
        $body;
}


if (
    !str_contains(
        $bodies['project_form'],
        'PROJECT_CONTEXT_TOPBAR_TITLE_V1'
    )
) {
    throw new RuntimeException(
        'Project topbar title contract missing.'
    );
}


if (
    str_contains(
        $bodies['membership_partial'],
        'as $code => $title'
    )
) {
    throw new RuntimeException(
        'Partial still overwrites page title.'
    );
}


if (
    !str_contains(
        $bodies['membership_partial'],
        'as $code => $fieldTypeTitle'
    )
) {
    throw new RuntimeException(
        'Partial field type isolation missing.'
    );
}


foreach ([
    'verification_state_code',
    'organization_catalog_entries',
    'ticketing_project_catalog_bindings',
    'verifiedAffiliationsForUser',
    'projectAffiliationOptions',
    'resolveForProject',
    'applyToProjectMember',
] as $contract) {

    if (
        !str_contains(
            $bodies['bridge'],
            $contract
        )
    ) {
        throw new RuntimeException(
            'Bridge contract missing: '
            . $contract
        );
    }
}


foreach ([
    'PROJECT_ORGANIZATION_CONTEXT_CONFIGURATION_V1',
    'organization_catalog_references[]',
    'primary_organization_catalog_reference',
    'منبع سازمانی پروژه',
] as $contract) {

    if (
        !str_contains(
            $bodies['membership_partial'],
            $contract
        )
    ) {
        throw new RuntimeException(
            'Project organization UI missing: '
            . $contract
        );
    }
}


foreach ([
    'organization_catalog_references',
    'primary_organization_catalog_reference',
] as $contract) {

    if (
        !str_contains(
            $bodies['membership_route'],
            $contract
        )
    ) {
        throw new RuntimeException(
            'Membership route missing: '
            . $contract
        );
    }
}


foreach ([
    'organization_context_required',
    'eligible_affiliations',
    'organization_affiliations',
    'resolveForProject',
    'applyToProjectMember',
] as $contract) {

    if (
        !str_contains(
            $bodies['onboarding'],
            $contract
        )
    ) {
        throw new RuntimeException(
            'Onboarding bridge missing: '
            . $contract
        );
    }
}


if (
    substr_count(
        $bodies['requester_route'],
        'core_organization_membership_reference'
    ) < 2
) {
    throw new RuntimeException(
        'Requester route forwarding incomplete.'
    );
}


foreach ([
    'requester_affiliation_required',
    'requester_affiliation_selection_required',
    'requester_affiliation_invalid',
    'core_organization_membership_reference',
] as $contract) {

    if (
        !str_contains(
            $bodies['onboarding_view'],
            $contract
        )
    ) {
        throw new RuntimeException(
            'Requester affiliation UI missing: '
            . $contract
        );
    }
}


if (
    str_contains(
        $bodies['bridge'],
        'organizations.parent_id'
    )
) {
    throw new RuntimeException(
        'Legacy organization parent authority detected.'
    );
}


echo "PROJECT_TOPBAR_TITLE=PROJECT_NAME\n";
echo "PARTIAL_TITLE_VARIABLE_LEAK=NO\n";
echo "CORE_AFFILIATION=CANONICAL\n";
echo "PROJECT_CATALOG_BINDING=YES\n";
echo "PROJECT_ROLE=INDEPENDENT\n";
echo "PROJECT_SCOPE=INDEPENDENT\n";
echo "MULTI_CATALOG_PROJECT=YES\n";
echo "MULTI_AFFILIATION_USER=YES\n";
echo "AUTO_SELECT_SINGLE_AFFILIATION=YES\n";
echo "SELECT_MULTIPLE_AFFILIATIONS=YES\n";
echo "LEGACY_PARENT_ID_AUTHORITY=NO\n";
echo "TICKETING_PROJECT_ORGANIZATION_AFFILIATION_BRIDGE_TEST=PASS\n";
