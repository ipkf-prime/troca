<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$form = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'automation-correspondence-form.php'
);

$detail = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'automation-correspondence-detail.php'
);

$command = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/'
    . 'Correspondence/CorrespondenceCommandService.php'
);

$seeder = file_get_contents(
    $root
    . '/public_html/system/Database/Seeds/'
    . 'AutomationCorrespondenceSeeder.php'
);

$dispatchMigration = file_get_contents(
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateAutomationCorrespondenceDispatchFoundation.php'
);

foreach (
    [
        'form' => $form,
        'detail' => $detail,
        'command' => $command,
        'seeder' => $seeder,
        'dispatchMigration' => $dispatchMigration,
    ]
    as $label => $source
) {
    if (!is_string($source)) {
        fwrite(
            STDERR,
            "FAIL: {$label} source unavailable.\n"
        );
        exit(1);
    }
}

$checks = [
    [
        $form,
        '<span>روش دریافت</span>',
        'Incoming form must call the field روش دریافت.',
    ],
    [
        $form,
        '$initialDirection === \'incoming\'',
        'Receive method must be direction-aware.',
    ],
    [
        $form,
        "'fax'",
        'Fax must be allowed as a receive method.',
    ],
    [
        $form,
        "'email'",
        'Email must be allowed as a receive method.',
    ],
    [
        $command,
        "\$direction === 'incoming'",
        'Only incoming correspondence may accept a receive channel.',
    ],
    [
        $command,
        ": 'manual';",
        'Outgoing/internal must use a neutral technical channel value.',
    ],
    [
        $detail,
        "'روش دریافت' => \$c['channel']",
        'Detail must label incoming channel as receive method.',
    ],
    [
        $seeder,
        "'correspondence_channel' => 'روش دریافت مکاتبه'",
        'Correspondence channel domain must mean receive method.',
    ],
    [
        $seeder,
        "'correspondence_dispatch_channel' => 'روش ارسال خارج از کارتابل'",
        'External dispatch channel must have a separate domain.',
    ],
    [
        $seeder,
        "['fax', 'فاکس']",
        'Fax must exist in channel catalogs.',
    ],
    [
        $seeder,
        "['email', 'ایمیل']",
        'Email must exist in channel catalogs.',
    ],
    [
        $seeder,
        "lv.code = 'internal'",
        'Legacy internal receive channel must be retired.',
    ],
    [
        $seeder,
        "lv.status = 'inactive'",
        'Obsolete receive channel must become inactive.',
    ],
    [
        $dispatchMigration,
        'source_snapshot_json LONGTEXT NOT NULL',
        'Dispatch must snapshot sender/source data.',
    ],
];

foreach ($checks as [$source, $needle, $message]) {
    if (!str_contains($source, $needle)) {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );
        exit(1);
    }
}

echo "Automation correspondence channel semantics checks passed.\n";
