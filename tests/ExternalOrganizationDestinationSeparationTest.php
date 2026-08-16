<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'ExternalOrganizationDirectoryService.php'
    );

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-external-organizations.php'
    );

if (
    !is_string($service)
    || !is_string($view)
) {
    fwrite(
        STDERR,
        "FAIL: Source unavailable.\n"
    );

    exit(1);
}


$serviceStart =
    strpos(
        $service,
        'public function saveContactPoint('
    );

$serviceEnd =
    strpos(
        $service,
        'public function saveContactMethod('
    );

if (
    $serviceStart === false
    || $serviceEnd === false
) {
    fwrite(
        STDERR,
        "FAIL: Contact-point service boundaries unavailable.\n"
    );

    exit(1);
}

$pointSave =
    substr(
        $service,
        $serviceStart,
        $serviceEnd - $serviceStart
    );


foreach ([
    '$phone',
    '$extension',
    '$fax',
    '$email',
    '$district',
    '$addressLine',
    '$postalCode',
    'syncDestinationContactField(',
    'syncDestinationPostalAddress(',
] as $forbidden) {
    if (
        str_contains(
            $pointSave,
            $forbidden
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Contact-point save coupled to {$forbidden}.\n"
        );

        exit(1);
    }
}


preg_match_all(
    '~<form\b.*?action="/admin/automation/'
    . 'external-organizations/contact-points/save"'
    . '.*?</form>~s',
    $view,
    $forms
);

if (
    count($forms[0] ?? [])
    !== 2
) {
    fwrite(
        STDERR,
        "FAIL: Expected exactly two destination forms.\n"
    );

    exit(1);
}


foreach (
    $forms[0]
    as $index => $form
) {
    foreach ([
        'name="phone"',
        'name="extension"',
        'name="fax"',
        'name="email"',
        'name="postal_code"',
        'name="address_line"',
    ] as $forbidden) {
        if (
            str_contains(
                $form,
                $forbidden
            )
        ) {
            fwrite(
                STDERR,
                "FAIL: Destination form "
                . ($index + 1)
                . " still contains {$forbidden}.\n"
            );

            exit(1);
        }
    }

    foreach ([
        'name="title"',
        'name="point_kind_code"',
        'name="contact_person_name"',
        'name="contact_person_title"',
        'name="business_hours"',
        'name="preferred_dispatch_channel_code"',
        'name="is_primary"',
    ] as $required) {
        if (
            !str_contains(
                $form,
                $required
            )
        ) {
            fwrite(
                STDERR,
                "FAIL: Destination administrative field missing: "
                . $required
                . "\n"
            );

            exit(1);
        }
    }
}


foreach ([
    '/contact-methods/save',
    '/contact-methods/deactivate',
    '/addresses/save',
    '/addresses/deactivate',
    'name="value"',
    'name="address_line"',
    'name="postal_code"',
] as $required) {
    if (
        !str_contains(
            $view,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Independent CRUD missing: {$required}\n"
        );

        exit(1);
    }
}


$header =
    strpos(
        $view,
        'class="external-directory-header-actions"'
    );

if ($header === false) {
    fwrite(
        STDERR,
        "FAIL: Directory header unavailable.\n"
    );

    exit(1);
}

$window =
    substr(
        $view,
        $header,
        1200
    );

if (
    str_contains(
        $window,
        'count($organizations)'
    )
) {
    fwrite(
        STDERR,
        "FAIL: Solitary organization count still visible.\n"
    );

    exit(1);
}


echo "External organization destination separation checks passed.\n";
