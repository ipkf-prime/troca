<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$resolver =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'ExternalOrganizationDirectoryReferenceResolver.php'
    );

$command =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceCommandService.php'
    );

$form =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-correspondence-form.php'
    );

if (
    !is_string($resolver)
    || !is_string($command)
    || !is_string($form)
) {
    fwrite(
        STDERR,
        "FAIL: V3A source unavailable.\n"
    );

    exit(1);
}


foreach ([
    'ExternalOrganizationDirectoryRepository',
    '->organization(',
    '->contactPoint(',
    "'status'",
    "'active'",
    "'external_organization_id'",
    'invalid_external_organization_reference',
    'invalid_external_contact_point_reference',
    'external_contact_point_organization_mismatch',
    'external_directory_unavailable',
] as $required) {
    if (!str_contains(
        $resolver,
        $required
    )) {
        fwrite(
            STDERR,
            "FAIL: Resolver contract missing {$required}\n"
        );

        exit(1);
    }
}


foreach ([
    'external-recipient-directory-bridge-v3a',
    'ExternalOrganizationDirectoryReferenceResolver',
    'externalDirectoryResolver()',
    '->resolve(',
    "'external_organization_public_reference'",
    "'external_contact_point_public_reference'",
] as $required) {
    if (!str_contains(
        $command,
        $required
    )) {
        fwrite(
            STDERR,
            "FAIL: Command resolver integration missing {$required}\n"
        );

        exit(1);
    }
}


/*
 * Core directory access must remain encapsulated in
 * the resolver/repository path, not SQL inside the
 * automation command service.
 */
foreach ([
    "resolve('core.primary')",
    'FROM external_organizations',
    'FROM external_organization_contact_points',
] as $forbidden) {
    if (str_contains(
        $command,
        $forbidden
    )) {
        fwrite(
            STDERR,
            "FAIL: Command service directly couples to Core: {$forbidden}\n"
        );

        exit(1);
    }
}


/*
 * V3A itself remains backend-only.
 *
 * Before V3B, the form must not expose directory bindings.
 * Once the explicit V3B UI marker exists, ownership of those
 * fields has intentionally moved to V3B and this historical
 * negative assertion no longer applies.
 */
$v3bUiActive =
    str_contains(
        $form,
        'external-recipient-directory-ui-v3b'
    );

if (!$v3bUiActive) {
    foreach ([
        'name="external_organization_public_reference',
        'name="external_contact_point_public_reference',
    ] as $forbidden) {
        if (str_contains(
            $form,
            $forbidden
        )) {
            fwrite(
                STDERR,
                "FAIL: V3A unexpectedly enabled UI binding.\n"
            );

            exit(1);
        }
    }
}


echo "External recipient directory bridge resolver checks passed.\n";
