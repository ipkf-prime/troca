<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);

$public =
    $root
    . '/public_html';


$failures = [];


$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $public,
            FilesystemIterator::SKIP_DOTS
        )
    );


foreach (
    $iterator
    as $file
) {
    if (!$file->isFile()) {
        continue;
    }


    $path =
        $file->getPathname();

    $normalized =
        str_replace(
            '\\',
            '/',
            $path
        );


    if (
        str_contains(
            $normalized,
            '/vendor/'
        )
        ||
        str_contains(
            $normalized,
            '/node_modules/'
        )
        ||
        str_contains(
            $normalized,
            '/storage/'
        )
    ) {
        continue;
    }


    $extension =
        strtolower(
            $file->getExtension()
        );


    if (
        !in_array(
            $extension,
            [
                'php',
                'css',
                'js',
                'html',
                'htm',
            ],
            true
        )
    ) {
        continue;
    }


    $contents =
        file_get_contents(
            $path
        );


    if (!is_string($contents)) {
        continue;
    }


    /*
     * No external runtime styles/scripts.
     */
    if (
        preg_match(
            '#<(?:script|link)\b[^>]*?(?:src|href)\s*=\s*["\']https?://#i',
            $contents
        ) === 1
    ) {
        $failures[] =
            'External runtime asset: '
            . $normalized;
    }


    /*
     * No raw Persian-page browser date control.
     */
    if (
        preg_match(
            '/type\s*=\s*["\']date["\']/i',
            $contents
        ) === 1
    ) {
        $failures[] =
            'Raw input[type=date]: '
            . $normalized;
    }


    /*
     * Debug helper definition itself is legacy debt.
     * Runtime use elsewhere is prohibited.
     */
    if (
        !str_ends_with(
            $normalized,
            '/system/Support/Debug.php'
        )
        &&
        preg_match(
            '/\bDebug::dd\s*\(/',
            $contents
        ) === 1
    ) {
        $failures[] =
            'Runtime Debug::dd usage: '
            . $normalized;
    }


    if (
        !str_ends_with(
            $normalized,
            '/system/Support/Debug.php'
        )
        &&
        preg_match(
            '/\b(?:var_dump|print_r)\s*\(/',
            $contents
        ) === 1
    ) {
        $failures[] =
            'Runtime debug dump usage: '
            . $normalized;
    }
}


$foundationFiles = [
    $public
        . '/public/assets/admin/css/foundation.css',

    $public
        . '/public/assets/admin/js/foundation.js',
];


foreach (
    $foundationFiles
    as $path
) {
    $contents =
        file_get_contents(
            $path
        );

    if (
        !is_string($contents)
        ||
        preg_match(
            '#https?://#i',
            $contents
        ) === 1
    ) {
        $failures[] =
            'Foundation external network dependency: '
            . $path;
    }
}


if ($failures !== []) {
    foreach (
        $failures
        as $failure
    ) {
        fwrite(
            STDERR,
            $failure
            . PHP_EOL
        );
    }

    exit(1);
}


/*
 * Dynamic zero-debt / ratchet enforcement.
 *
 * The main Foundation guard invokes the debt guard so future feature work
 * cannot bypass it by running only the historic Foundation guard.
 */
require
    __DIR__
    . '/PlatformFoundationDebtGuardTest.php';


echo
    "PLATFORM_FOUNDATION_GUARD_PASS"
    . PHP_EOL;
