<?php

declare(strict_types=1);

require __DIR__
    . '/../public_html/vendor/autoload.php';

use App\Services\Infrastructure\ClamAvProcessScanner;
use App\Services\Infrastructure\SharedFileInfrastructureSettingsService;
use App\Services\Infrastructure\SharedPrivateStorageService;


$assert =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            fwrite(
                STDERR,
                "FAIL: {$message}\n"
            );

            exit(1);
        }
    };


/*
 * The target file may live under /tmp even when /tmp is noexec.
 * It is read by the scanner, never executed.
 */
$probe =
    tempnam(
        sys_get_temp_dir(),
        'ipkf-shared-file-probe-'
    );

$assert(
    $probe !== false,
    'probe creation'
);

file_put_contents(
    $probe,
    "IPKF safe scanner contract probe\n"
);


/*
 * Use trusted system executables as deterministic exit-code fixtures.
 *
 * /bin/true  => 0
 * /bin/false => 1
 * /bin/sh with ClamAV arguments => non-0/non-1
 *
 * This avoids executing fixture scripts from a noexec /tmp.
 */
$assert(
    (
        new ClamAvProcessScanner(
            '/bin/true',
            5
        )
    )->scan(
        $probe
    )
    === 'clean',

    'scanner exit code 0 must map to clean'
);


$assert(
    (
        new ClamAvProcessScanner(
            '/bin/false',
            5
        )
    )->scan(
        $probe
    )
    === 'infected',

    'scanner exit code 1 must map to infected'
);


$assert(
    (
        new ClamAvProcessScanner(
            '/bin/sh',
            5
        )
    )->scan(
        $probe
    )
    === 'error',

    'scanner other exit code must map to error'
);


@unlink(
    $probe
);


$reflection =
    new ReflectionClass(
        SharedPrivateStorageService::class
    );

$service =
    $reflection
        ->newInstanceWithoutConstructor();

$safe =
    $reflection
        ->getMethod(
            'safeRelativeKey'
        );


$assert(
    $safe->invoke(
        $service,
        'ticketing/attachments/2026/08/test.png'
    )
    ===
    'ticketing/attachments/2026/08/test.png',

    'relative storage key must remain relative'
);


$unsafeRejected = false;

try {
    $safe->invoke(
        $service,
        '../outside.txt'
    );

} catch (Throwable) {
    $unsafeRejected = true;
}


$assert(
    $unsafeRejected,
    'path traversal must be rejected'
);


$absoluteRejected = false;

try {
    $safe->invoke(
        $service,
        '/etc/passwd'
    );

} catch (Throwable) {
    $absoluteRejected = true;
}


$assert(
    $absoluteRejected,
    'new absolute storage keys must be rejected'
);


/*
 * PRIVATE_FILE_STORAGE_PATH is a legacy module-level value.
 * It must never become the shared platform storage root.
 */
$settingsReflection =
    new ReflectionClass(
        SharedFileInfrastructureSettingsService::class
    );

$settingsService =
    $settingsReflection
        ->newInstanceWithoutConstructor();

$storageFallback =
    $settingsReflection
        ->getMethod(
            'storageFallbackWithoutSetting'
        );

$storageSource =
    $settingsReflection
        ->getMethod(
            'storageRootSource'
        );


$keys = [
    'IPKF_PRIVATE_FILE_STORAGE_PATH',
    'PRIVATE_FILE_STORAGE_PATH',
];

$environmentBackup = [];

foreach ($keys as $key) {
    $environmentBackup[$key] = [
        'env_exists' =>
            array_key_exists(
                $key,
                $_ENV
            ),

        'env_value' =>
            $_ENV[$key]
            ?? null,

        'server_exists' =>
            array_key_exists(
                $key,
                $_SERVER
            ),

        'server_value' =>
            $_SERVER[$key]
            ?? null,
    ];
}


unset(
    $_ENV[
        'IPKF_PRIVATE_FILE_STORAGE_PATH'
    ],
    $_SERVER[
        'IPKF_PRIVATE_FILE_STORAGE_PATH'
    ]
);

$_ENV[
    'PRIVATE_FILE_STORAGE_PATH'
] =
    '/home/troca/storage/private/automation';

$_SERVER[
    'PRIVATE_FILE_STORAGE_PATH'
] =
    '/home/troca/storage/private/automation';


$assert(
    $storageFallback->invoke(
        $settingsService
    )
    === null,

    'legacy module storage must not become shared root'
);


$assert(
    $storageSource->invoke(
        $settingsService,
        []
    )
    === 'module_legacy_default',

    'legacy module storage source must remain module legacy'
);


$_ENV[
    'IPKF_PRIVATE_FILE_STORAGE_PATH'
] =
    '/mnt/ipkf-private';

$_SERVER[
    'IPKF_PRIVATE_FILE_STORAGE_PATH'
] =
    '/mnt/ipkf-private';


$assert(
    $storageFallback->invoke(
        $settingsService
    )
    === '/mnt/ipkf-private',

    'explicit shared ENV must become shared root'
);


$assert(
    $storageSource->invoke(
        $settingsService,
        []
    )
    === 'shared_env',

    'explicit shared ENV source must be reported'
);


foreach (
    $environmentBackup
    as $key => $backup
) {
    if ($backup['env_exists']) {
        $_ENV[$key] =
            $backup['env_value'];

    } else {
        unset(
            $_ENV[$key]
        );
    }

    if ($backup['server_exists']) {
        $_SERVER[$key] =
            $backup['server_value'];

    } else {
        unset(
            $_SERVER[$key]
        );
    }
}


$web =
    file_get_contents(
        __DIR__
        . '/../public_html/routes/web.php'
    );

$assert(
    is_string(
        $web
    )
    && str_contains(
        $web,
        '/admin/settings/file-infrastructure'
    ),

    'general file infrastructure route must exist'
);


$settings =
    file_get_contents(
        __DIR__
        . '/../public_html/resources/views/admin/settings.php'
    );

$assert(
    is_string(
        $settings
    )
    && str_contains(
        $settings,
        'تنظیمات فایل و آنتی‌ویروس'
    ),

    'general settings entry must exist'
);


$env =
    file_get_contents(
        __DIR__
        . '/../public_html/.env.example'
    );

foreach (
    [
        'IPKF_PRIVATE_FILE_STORAGE_PATH=',
        'IPKF_MALWARE_CLAMSCAN_PATH=',
        'IPKF_MALWARE_SCAN_TIMEOUT_SECONDS=45',
    ]
    as $marker
) {
    $assert(
        is_string(
            $env
        )
        && str_contains(
            $env,
            $marker
        ),

        "missing ENV marker: {$marker}"
    );
}


echo "SHARED_SCANNER_EXIT_CODE_CONTRACT=PASS\n";
echo "TMP_NOEXEC_SAFE_SCANNER_TEST=PASS\n";
echo "SHARED_STORAGE_RELATIVE_KEY_CONTRACT=PASS\n";
echo "SHARED_STORAGE_TRAVERSAL_GUARD=PASS\n";
echo "SHARED_STORAGE_ABSOLUTE_NEW_KEY_GUARD=PASS\n";
echo "LEGACY_MODULE_ROOT_ISOLATION=PASS\n";
echo "EXPLICIT_SHARED_ROOT_CONTRACT=PASS\n";
echo "GENERAL_SETTINGS_ENTRY=PASS\n";
echo "SHARED_ENV_FALLBACK_CONTRACT=PASS\n";
