<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$repository =
    file_get_contents(
        $root
        . '/public_html/app/Repositories/'
        . 'ExternalOrganizationDirectoryRepository.php'
    );

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'ExternalOrganizationDirectoryService.php'
    );

if (
    !is_string($repository)
    || !is_string($service)
) {
    fwrite(
        STDERR,
        "FAIL: Source unavailable.\n"
    );

    exit(1);
}

foreach ([
    'methods.*',
    'area_code',
    'extension',
    'UTC_TIMESTAMP()',
    '$statement->rowCount() > 0',
] as $marker) {
    if (
        !str_contains(
            $repository,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Repository marker missing: {$marker}\n"
        );

        exit(1);
    }
}

foreach ([
    'phoneAreaCode(',
    'phoneNumber(',
    'phoneExtension(',
    'normalizePhone(',
    'asciiDigits(',
    "'area_code'",
    "'extension'",
    'داخلی باید همراه همان تلفن ثابت ثبت شود.',
] as $marker) {
    if (
        !str_contains(
            $service,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Service marker missing: {$marker}\n"
        );

        exit(1);
    }
}

$saveStart =
    strpos(
        $service,
        'public function saveContactMethod('
    );

$saveEnd =
    strpos(
        $service,
        'public function saveAddress('
    );

$save =
    substr(
        $service,
        $saveStart,
        $saveEnd - $saveStart
    );

if (
    !str_contains(
        $save,
        "\$typeCode === 'phone'"
    )
    ||
    !str_contains(
        $save,
        "\$typeCode === 'extension'"
    )
    ||
    !str_contains(
        $save,
        "'area_code'"
    )
    ||
    !str_contains(
        $save,
        "'extension'"
    )
) {
    fwrite(
        STDERR,
        "FAIL: Structured phone save semantics incomplete.\n"
    );

    exit(1);
}

$normalizeStart =
    strpos(
        $service,
        'private function normalizeContact('
    );

$normalizeEnd =
    strpos(
        $service,
        'private function reference('
    );

$normalize =
    substr(
        $service,
        $normalizeStart,
        $normalizeEnd - $normalizeStart
    );

if (
    str_contains(
        $normalize,
        "'extension'"
    )
) {
    fwrite(
        STDERR,
        "FAIL: Extension remains an independent normalized contact type.\n"
    );

    exit(1);
}

echo "External organization structured phone service checks passed.\n";
