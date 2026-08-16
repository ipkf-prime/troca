<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$path =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateExternalOrganizationCorrespondenceDirectory.php';

$source = file_get_contents($path);

if (!is_string($source)) {
    fwrite(STDERR, "FAIL: Core directory migration unreadable.\n");
    exit(1);
}

foreach ([
    'external_organizations',
    'external_organization_contact_points',
    'external_organization_contact_methods',
    'external_organization_contact_addresses',
    'point_kind_code',
    'preferred_dispatch_channel_code',
    'supports_dispatch',
    'supports_followup',
    'contact_person_name',
    'contact_person_title',
    'postal_code',
    'contact_type_id',
    'address_type_id',
    'external_org_method_type_index',
    'external_org_point_org_fk',
    'external_org_method_point_fk',
    'external_org_address_point_fk',
] as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(
            STDERR,
            "FAIL: Missing external directory contract: {$needle}\n"
        );
        exit(1);
    }
}

foreach ([
    'external_org_method_type_fk',
    'external_org_address_type_fk',
] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        fwrite(
            STDERR,
            "FAIL: Legacy MyISAM catalog cannot be an InnoDB FK target: {$forbidden}\n"
        );
        exit(1);
    }
}

echo "External organization correspondence directory foundation checks passed.\n";
