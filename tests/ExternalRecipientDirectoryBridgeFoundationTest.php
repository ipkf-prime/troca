<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'AddExternalDirectoryReferencesToCorrespondenceParties.php';

$source =
    file_get_contents(
        $path
    );

if (!is_string($source)) {
    fwrite(
        STDERR,
        "FAIL: bridge migration unavailable.\n"
    );

    exit(1);
}

foreach ([
    'correspondence_parties',
    'external_organization_public_reference',
    'external_contact_point_public_reference',
    'CHAR(36)',
    'CHARACTER SET ascii',
    'COLLATE ascii_bin',
    'corr_party_external_org_ref_index',
    'corr_party_external_point_ref_index',
    'columnExists(',
    'indexExists(',
] as $required) {
    if (!str_contains(
        $source,
        $required
    )) {
        fwrite(
            STDERR,
            "FAIL: missing {$required}\n"
        );

        exit(1);
    }
}

/*
 * Core Directory and Automation DB are separate
 * connections. Persist stable public references,
 * not cross-database foreign keys.
 */
foreach ([
    'REFERENCES external_organizations',
    'REFERENCES external_organization_contact_points',
] as $forbidden) {
    if (str_contains(
        $source,
        $forbidden
    )) {
        fwrite(
            STDERR,
            "FAIL: forbidden cross-database FK {$forbidden}\n"
        );

        exit(1);
    }
}

echo "External recipient directory bridge foundation checks passed.\n";
