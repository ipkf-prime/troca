<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-external-organizations.php'
    );

$seeder =
    file_get_contents(
        $root
        . '/public_html/system/Database/Seeds/'
        . 'ExternalOrganizationContactCatalogSeeder.php'
    );

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'ExternalOrganizationDirectoryService.php'
    );

if (
    !is_string($view)
    || !is_string($seeder)
    || !is_string($service)
) {
    fwrite(
        STDERR,
        "FAIL: Structured phone source unavailable.\n"
    );

    exit(1);
}


preg_match_all(
    '#<form\b.*?'
    . 'action="/admin/automation/'
    . 'external-organizations/contact-methods/save"'
    . '.*?</form>#s',
    $view,
    $matches
);

$forms =
    $matches[0]
    ?? [];

if (count($forms) !== 2) {
    fwrite(
        STDERR,
        "FAIL: Expected two contact method forms.\n"
    );

    exit(1);
}


foreach (
    $forms
    as $index => $form
) {
    foreach ([
        'name="area_code"',
        'name="value"',
        'name="extension"',
        'data-contact-type-selector',
        'data-contact-value-label',
        'data-phone-contact-field',
    ] as $marker) {
        if (
            !str_contains(
                $form,
                $marker
            )
        ) {
            fwrite(
                STDERR,
                'FAIL: Form '
                . ($index + 1)
                . " missing {$marker}\n"
            );

            exit(1);
        }
    }
}


foreach ([
    'structured-phone-contact-types-v1',
    "!== 'extension'",
    'data-structured-phone-ui',
    'شماره تلفن *',
    'پیش‌شماره',
    'داخلی',
    'اختیاری؛ بدون صفر، مثال ۲۱',
    'اختیاری؛ متعلق به همین تلفن',
] as $marker) {
    if (
        !str_contains(
            $view,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: UI marker missing: {$marker}\n"
        );

        exit(1);
    }
}


foreach ([
    'structured-phone-retire-standalone-extension-v1',
    "WHERE code = 'extension'",
    "status = 'inactive'",
] as $marker) {
    if (
        !str_contains(
            $seeder,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Seeder marker missing: {$marker}\n"
        );

        exit(1);
    }
}


if (
    !str_contains(
        $service,
        'catch (\\Throwable $exception)'
    )
) {
    fwrite(
        STDERR,
        "FAIL: Qualified Throwable missing.\n"
    );

    exit(1);
}


echo "External organization structured phone UI checks passed.\n";
