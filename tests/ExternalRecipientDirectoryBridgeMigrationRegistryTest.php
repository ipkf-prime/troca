<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$registryPath =
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';

$migrationPath =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'AddExternalDirectoryReferencesToCorrespondenceParties.php';

$registry =
    file_get_contents(
        $registryPath
    );

$migration =
    file_get_contents(
        $migrationPath
    );

if (
    !is_string($registry)
    || !is_string($migration)
) {
    fwrite(
        STDERR,
        "FAIL: Registry source unavailable.\n"
    );

    exit(1);
}

$bridge =
    'AddExternalDirectoryReferencesToCorrespondenceParties::class';

if (
    substr_count(
        $registry,
        $bridge
    ) !== 1
) {
    fwrite(
        STDERR,
        "FAIL: Bridge migration must appear exactly once.\n"
    );

    exit(1);
}

$automationStart =
    strpos(
        $registry,
        "'automation' => ["
    );

$workStart =
    strpos(
        $registry,
        "'work' => [",
        $automationStart !== false
            ? $automationStart
            : 0
    );

if (
    $automationStart === false
    || $workStart === false
    || $workStart <= $automationStart
) {
    fwrite(
        STDERR,
        "FAIL: Automation registry boundary unavailable.\n"
    );

    exit(1);
}

$automation =
    substr(
        $registry,
        $automationStart,
        $workStart - $automationStart
    );

if (
    !str_contains(
        $automation,
        "'connection' => 'automation.primary'"
    )
    ||
    !str_contains(
        $automation,
        $bridge
    )
) {
    fwrite(
        STDERR,
        "FAIL: Bridge migration not registered on automation.primary.\n"
    );

    exit(1);
}

$core =
    substr(
        $registry,
        0,
        $automationStart
    );

if (
    str_contains(
        $core,
        $bridge
    )
) {
    fwrite(
        STDERR,
        "FAIL: Bridge migration leaked into Core group.\n"
    );

    exit(1);
}

$bridgePosition =
    strpos(
        $automation,
        $bridge
    );

$dispatchPosition =
    strpos(
        $automation,
        'CreateAutomationCorrespondenceDispatchFoundation::class'
    );

if (
    $bridgePosition === false
    || $dispatchPosition === false
    || $bridgePosition > $dispatchPosition
) {
    fwrite(
        STDERR,
        "FAIL: Bridge migration must precede dispatch foundation.\n"
    );

    exit(1);
}

foreach ([
    'external_organization_public_reference',
    'external_contact_point_public_reference',
] as $required) {
    if (
        !str_contains(
            $migration,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Migration contract missing {$required}.\n"
        );

        exit(1);
    }
}

echo "External recipient bridge migration registry checks passed.\n";
