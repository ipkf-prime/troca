<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$repositoryPath =
    $root
    . '/public_html/app/Repositories/'
    . 'ExternalOrganizationDirectoryRepository.php';

$servicePath =
    $root
    . '/public_html/app/Services/Automation/Correspondence/'
    . 'ExternalOrganizationDirectoryService.php';

$permissionPath =
    $root
    . '/public_html/system/Database/Seeds/'
    . 'AutomationCorrespondencePermissionsSeeder.php';

$repository =
    file_get_contents(
        $repositoryPath
    );

$service =
    file_get_contents(
        $servicePath
    );

$permission =
    file_get_contents(
        $permissionPath
    );

foreach ([
    'repository' => $repository,
    'service' => $service,
    'permission' => $permission,
] as $label => $source) {
    if (!is_string($source)) {
        fwrite(
            STDERR,
            "FAIL: {$label} source unavailable.\n"
        );
        exit(1);
    }
}

$repositoryRequired = [
    "resolve('core.primary')",
    'external_organizations',
    'external_organization_contact_points',
    'external_organization_contact_methods',
    'external_organization_contact_addresses',
    'contact_types',
    'address_types',
    'createOrganization',
    'updateOrganization',
    'createContactPoint',
    'updateContactPoint',
    'createContactMethod',
    'updateContactMethod',
    'createAddress',
    'updateAddress',
    "status = 'inactive'",
];

foreach (
    $repositoryRequired
    as $needle
) {
    if (
        !str_contains(
            $repository,
            $needle
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Missing repository contract: {$needle}\n"
        );
        exit(1);
    }
}

$serviceRequired = [
    'DISPATCH_CHANNELS',
    "'postal'",
    "'courier'",
    "'hand_delivery'",
    "'fax'",
    "'email'",
    "'system'",
    'saveOrganization',
    'saveContactPoint',
    'saveContactMethod',
    'saveAddress',
    'preferred_dispatch_channel_code',
    'supports_dispatch',
    'supports_followup',
    'postal_code',
    'random_bytes',
];

foreach (
    $serviceRequired
    as $needle
) {
    if (
        !str_contains(
            $service,
            $needle
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Missing service contract: {$needle}\n"
        );
        exit(1);
    }
}

foreach ([
    'DELETE FROM external_organizations',
    'DELETE FROM external_organization_contact_points',
    'DELETE FROM external_organization_contact_methods',
    'DELETE FROM external_organization_contact_addresses',
    "resolve('automation.primary')",
] as $forbidden) {
    if (
        str_contains(
            $repository,
            $forbidden
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Forbidden repository behavior: {$forbidden}\n"
        );
        exit(1);
    }
}

if (
    !str_contains(
        $permission,
        "'automation.external_directory.manage'"
    )
    || !str_contains(
        $permission,
        "'external_directory'"
    )
) {
    fwrite(
        STDERR,
        "FAIL: External directory RBAC permission missing.\n"
    );
    exit(1);
}

echo "External organization directory CRUD foundation checks passed.\n";
