<?php

declare(strict_types=1);

require_once __DIR__
    . '/../public_html/vendor/autoload.php';

require_once __DIR__
    . '/../public_html/system/Support/helpers.php';

use IPKF\Support\ApplicationUrlRegistry;

$keys = [
    'IPKF_MODULE',
    'IPKF_MODULE_RUNTIME_OVERRIDE',
    'APP_HOST_GUARD_ENABLED',
    'ALLOWED_APP_HOSTS',
    'CORE_APP_URL',
    'TICKETING_APP_URL',
];

$envBackup = [];
$serverBackup = [];

foreach ($keys as $key) {
    $envBackup[$key] =
        array_key_exists(
            $key,
            $_ENV
        )
            ? [
                true,
                $_ENV[$key],
            ]
            : [
                false,
                null,
            ];

    $serverBackup[$key] =
        array_key_exists(
            $key,
            $_SERVER
        )
            ? [
                true,
                $_SERVER[$key],
            ]
            : [
                false,
                null,
            ];
}

$set =
    static function (
        string $key,
        string $value
    ): void {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    };

$restore =
    static function (
        array $backup,
        array &$target
    ): void {
        foreach (
            $backup
            as $key => $state
        ) {
            if ($state[0]) {
                $target[$key] =
                    $state[1];
            } else {
                unset(
                    $target[$key]
                );
            }
        }
    };

try {
    $set(
        'IPKF_MODULE',
        'ticketing'
    );

    $set(
        'IPKF_MODULE_RUNTIME_OVERRIDE',
        'true'
    );

    $set(
        'APP_HOST_GUARD_ENABLED',
        'true'
    );

    $set(
        'ALLOWED_APP_HOSTS',
        ''
    );

    $set(
        'CORE_APP_URL',
        'https://core.example.invalid'
    );

    $set(
        'TICKETING_APP_URL',
        'https://ticketing.example.invalid'
    );

    $urls =
        new ApplicationUrlRegistry();

    if (!$urls->guardEnabled()) {
        throw new RuntimeException(
            'host_guard_not_enabled'
        );
    }

    if (
        $urls->ticketingHost()
        !== 'ticketing.example.invalid'
    ) {
        throw new RuntimeException(
            'runtime_override_ticketing_host_not_resolved'
        );
    }

    if (
        !$urls->isTicketingHost(
            'ticketing.example.invalid'
        )
    ) {
        throw new RuntimeException(
            'runtime_override_ticketing_host_identity_failed'
        );
    }

    if (
        !$urls->allowed(
            'ticketing.example.invalid'
        )
    ) {
        throw new RuntimeException(
            'runtime_override_ticketing_host_rejected_by_guard'
        );
    }

    if (
        $urls->allowed(
            'untrusted.example.invalid'
        )
    ) {
        throw new RuntimeException(
            'untrusted_host_unexpectedly_allowed'
        );
    }

    $target =
        $urls->redirectTarget(
            'ticketing.example.invalid',
            '/'
        );

    if (
        $target !==
        'https://ticketing.example.invalid/admin/ticketing'
    ) {
        throw new RuntimeException(
            'ticketing_root_redirect_contract_failed: '
            . var_export(
                $target,
                true
            )
        );
    }

    echo
        "MODULE_HOST_RUNTIME_OVERRIDE_ALLOWLIST_CONTRACT=PASS"
        . PHP_EOL;

    echo
        "RUNTIME_OVERRIDE_HOST=ticketing.example.invalid"
        . PHP_EOL;

    echo
        "UNTRUSTED_HOST=DENIED"
        . PHP_EOL;

    echo
        "ROOT_REDIRECT=https://ticketing.example.invalid/admin/ticketing"
        . PHP_EOL;
} finally {
    $restore(
        $envBackup,
        $_ENV
    );

    $restore(
        $serverBackup,
        $_SERVER
    );
}
