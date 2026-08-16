<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $value =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'Unreadable source: '
                . $relative
            );
        }

        return $value;
    };

$options =
    $read(
        'public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'ExternalOrganizationDirectoryFormOptions.php'
    );

$query =
    $read(
        'public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceQueryService.php'
    );

$resolver =
    $read(
        'public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'ExternalOrganizationDirectoryReferenceResolver.php'
    );

$command =
    $read(
        'public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceCommandService.php'
    );

$form =
    $read(
        'public_html/resources/views/admin/'
        . 'automation-correspondence-form.php'
    );

foreach ([
    'ExternalOrganizationDirectoryRepository',
    '->organizations(',
    '->contactPoints(',
    "'contact_points'",
] as $required) {
    if (
        !str_contains(
            $options,
            $required
        )
    ) {
        throw new RuntimeException(
            'Options contract missing: '
            . $required
        );
    }
}

foreach ([
    'external-recipient-directory-bridge-v3b',
    "'external_directory'",
    'externalDirectoryFormOptions()',
] as $required) {
    if (
        !str_contains(
            $query,
            $required
        )
    ) {
        throw new RuntimeException(
            'Query contract missing: '
            . $required
        );
    }
}

foreach ([
    'external-directory-canonical-snapshot-v3b',
    "'external_organization_name'",
    "'external_contact_point_title'",
    'organizationName(',
] as $required) {
    if (
        !str_contains(
            $resolver,
            $required
        )
    ) {
        throw new RuntimeException(
            'Resolver contract missing: '
            . $required
        );
    }
}

foreach ([
    'external-recipient-directory-outgoing-policy-v3b',
    'external_directory_organization_required',
    'external_directory_contact_point_required',
    '$externalOrganizationNameSnapshot',
    '$externalContactSnapshot',
] as $required) {
    if (
        !str_contains(
            $command,
            $required
        )
    ) {
        throw new RuntimeException(
            'Command contract missing: '
            . $required
        );
    }
}

foreach ([
    'external-recipient-directory-ui-v3b',
    'name="external_organization_public_reference[]"',
    'name="external_contact_point_public_reference[]"',
    'data-external-directory-organization',
    'data-external-directory-point',
    'data-organization-reference',
    'syncExternalDirectory',
    "\$initialDirection === 'outgoing'",
] as $required) {
    if (
        !str_contains(
            $form,
            $required
        )
    ) {
        throw new RuntimeException(
            'UI contract missing: '
            . $required
        );
    }
}

foreach ([
    'name="external_display_name[]"',
    'name="external_organization_name[]"',
    'name="external_contact_or_address[]"',
    '<span>نشانی یا تماس بیرونی</span>',
] as $required) {
    if (
        !str_contains(
            $form,
            $required
        )
    ) {
        throw new RuntimeException(
            'Legacy snapshot compatibility missing: '
            . $required
        );
    }
}

echo "External recipient directory bridge UI checks passed.\n";
