<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceDispatchService.php'
    );

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateAutomationCorrespondenceDispatchFoundation.php'
    );

if (
    !is_string($service)
    || !is_string($migration)
) {
    throw new RuntimeException(
        'Dispatch lifecycle sources unavailable.'
    );
}

if (
    !str_contains(
        $service,
        "'status_code' =>\n                        'pending'"
    )
    &&
    !str_contains(
        $service,
        "'pending'"
    )
) {
    throw new RuntimeException(
        'Request must begin in pending lifecycle state.'
    );
}

foreach ([
    'correspondence_dispatch_attempts',
    'correspondence_dispatch_followups',
    'dispatched_at TIMESTAMP NULL',
    'delivered_at TIMESTAMP NULL',
    'failed_at TIMESTAMP NULL',
] as $required) {
    if (!str_contains(
        $migration,
        $required
    )) {
        throw new RuntimeException(
            'Future lifecycle foundation missing: '
            . $required
        );
    }
}

foreach ([
    'provider_attempted',
    "'correspondence_status_changed' =>",
] as $required) {
    if (!str_contains(
        $service,
        $required
    )) {
        throw new RuntimeException(
            'Request result contract missing: '
            . $required
        );
    }
}

echo "Automation correspondence dispatch lifecycle D2 checks passed.\n";
