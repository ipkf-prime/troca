<?php

declare(strict_types=1);

$file = file_get_contents(
    dirname(__DIR__)
    . '/public_html/app/Services/'
    . 'ModuleSsoService.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
};

$expect(
    str_contains(
        $file,
        'allActive()'
    ),
    'SSO must use module registry.'
);

$expect(
    str_contains(
        $file,
        "['permission_key']"
    ),
    'SSO must use dynamic base permission.'
);

$expect(
    str_contains(
        $file,
        "['sso_callback_url']"
    ),
    'SSO must use registered callback.'
);

$expect(
    str_contains(
        $file,
        "['base_url']"
    ),
    'SSO must resolve registered hosts.'
);

foreach ([
    "case 'work'",
    "case 'automation'",
    "case 'ticketing'",
    "'work' =>",
    "'ticketing' =>",
] as $staticPattern) {

    $expect(
        !str_contains(
            $file,
            $staticPattern
        ),
        'Static SSO module mapping remains: '
        . $staticPattern
    );
}

$expect(
    !str_contains(
        $file,
        'isWorkHost('
    )
    && !str_contains(
        $file,
        'isTicketingHost('
    )
    && !str_contains(
        $file,
        'isAutomationHost('
    ),
    'SSO audience must not use fixed module host methods.'
);

echo "GENERIC_MODULE_SSO_PASS\n";
