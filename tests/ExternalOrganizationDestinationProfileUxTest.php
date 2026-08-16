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
        "FAIL: Required source unavailable.\n"
    );

    exit(1);
}

$start =
    strpos(
        $service,
        'public function saveContactPoint('
    );

$end =
    strpos(
        $service,
        'public function saveContactMethod('
    );

if (
    $start === false
    || $end === false
) {
    fwrite(
        STDERR,
        "FAIL: Service boundaries unavailable.\n"
    );

    exit(1);
}

$pointSave =
    substr(
        $service,
        $start,
        $end - $start
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
            "FAIL: Destination save still owns {$forbidden}.\n"
        );

        exit(1);
    }
}

foreach ([
    'saveContactMethod(',
    'saveAddress(',
    'contact-methods/save',
    'addresses/save',
    '+ راه ارتباطی',
    '+ نشانی',
] as $required) {
    if (
        !str_contains(
            $service . $view,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Independent contact/address path missing: {$required}\n"
        );

        exit(1);
    }
}

echo "External organization destination profile UX checks passed.\n";
