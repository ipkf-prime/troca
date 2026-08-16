<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $source =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($source)) {
            throw new RuntimeException(
                'Unreadable source: '
                . $relative
            );
        }

        return $source;
    };

$service =
    $read(
        'public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceDispatchService.php'
    );

$target =
    $read(
        'public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceDispatchTargetResolver.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateAutomationCorrespondenceDispatchFoundation.php'
    );

$seeder =
    $read(
        'public_html/system/Database/Seeds/'
        . 'AutomationCorrespondenceSeeder.php'
    );

foreach ([
    'public function request(',
    'dispatch-request-lifecycle-d2',
    'INSERT INTO correspondence_dispatches',
    "'pending'",
    'target_snapshot_json',
    'source_snapshot_json',
    'destination_snapshot_json',
    'activeRequest(',
    'officialRegistration(',
    'primaryRecipients(',
] as $required) {
    if (!str_contains(
        $service,
        $required
    )) {
        throw new RuntimeException(
            'Dispatch request service missing: '
            . $required
        );
    }
}

foreach ([
    "'postal'",
    "'courier'",
    "'hand_delivery'",
    "'fax'",
    "'email'",
    "'system'",
] as $channel) {
    if (!str_contains(
        $service,
        $channel
    )) {
        throw new RuntimeException(
            'Dispatch channel missing: '
            . $channel
        );
    }
}

foreach ([
    'ExternalOrganizationDirectoryRepository',
    '->organization(',
    '->contactPoint(',
    '->contactMethods(',
    '->addresses(',
    "'supports_dispatch'",
    'external_directory_binding_required',
    'dispatch_destination_unavailable',
] as $required) {
    if (!str_contains(
        $target,
        $required
    )) {
        throw new RuntimeException(
            'Target resolver missing: '
            . $required
        );
    }
}

foreach ([
    'UPDATE correspondences',
    "status_code = 'dispatched'",
    "->append(",
    'INSERT INTO correspondence_dispatch_attempts',
    'INSERT INTO correspondence_dispatch_followups',
] as $forbidden) {
    if (str_contains(
        $service,
        $forbidden
    )) {
        throw new RuntimeException(
            'Request service contains forbidden completion behavior: '
            . $forbidden
        );
    }
}

foreach ([
    'correspondence_dispatches',
    'correspondence_dispatch_attempts',
    'correspondence_dispatch_followups',
    'source_snapshot_json LONGTEXT NOT NULL',
    'destination_snapshot_json LONGTEXT NOT NULL',
] as $required) {
    if (!str_contains(
        $migration,
        $required
    )) {
        throw new RuntimeException(
            'Dispatch foundation missing: '
            . $required
        );
    }
}

if (
    !str_contains(
        $seeder,
        "['fax', 'فاکس']"
    )
) {
    throw new RuntimeException(
        'Fax dispatch lookup missing.'
    );
}

echo "Automation correspondence dispatch request service checks passed.\n";
