<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$command =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceCommandService.php'
    );

$party =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondencePartyRepository.php'
    );

$form =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-correspondence-form.php'
    );

if (
    !is_string($command)
    || !is_string($party)
    || !is_string($form)
) {
    fwrite(
        STDERR,
        "FAIL: V2 sources unavailable.\n"
    );

    exit(1);
}


foreach ([
    'external-recipient-directory-bridge-v2',
    "'external_organization_public_reference'",
    "'external_contact_point_public_reference'",
    '$externalOrganizationReferences',
    '$externalContactPointReferences',
    'externalDirectoryReference(',
    'invalid_external_directory_reference',
    'external_directory_organization_required',
] as $required) {
    if (!str_contains(
        $command,
        $required
    )) {
        fwrite(
            STDERR,
            "FAIL: Command persistence missing {$required}\n"
        );

        exit(1);
    }
}


foreach ([
    'external_organization_public_reference',
    'external_contact_point_public_reference',
    "'external_organization_public_reference'",
    "'external_contact_point_public_reference'",
] as $required) {
    if (!str_contains(
        $party,
        $required
    )) {
        fwrite(
            STDERR,
            "FAIL: Repository persistence missing {$required}\n"
        );

        exit(1);
    }
}


/*
 * V2 itself is deliberately persistence-only.
 *
 * Before V3B, the form must not expose the directory
 * references. Once the explicit V3B UI contract is present,
 * that historical negative assertion no longer applies.
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
                "FAIL: V2 unexpectedly activated UI field {$forbidden}\n"
            );

            exit(1);
        }
    }
}


if (
    !str_contains(
        $command,
        "'external_display_name' => \$name"
    )
    ||
    !str_contains(
        $command,
        "'external_organization_name' =>"
    )
    ||
    !str_contains(
        $command,
        "'external_contact_or_address' =>"
    )
) {
    fwrite(
        STDERR,
        "FAIL: Historical external snapshot fields were removed.\n"
    );

    exit(1);
}


echo "External recipient directory bridge persistence checks passed.\n";
