<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateAutomationCorrespondenceDispatchFoundation.php'
    );

$contract =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'AutomationSchemaParityContract.php'
    );

if (
    !is_string($migration)
    || !is_string($contract)
) {
    fwrite(
        STDERR,
        "FAIL: Dispatch foundation sources unreadable.\n"
    );
    exit(1);
}

foreach ([
    'correspondence_dispatches',
    'correspondence_dispatch_attempts',
    'correspondence_dispatch_followups',
    'external_organization_public_reference',
    'external_contact_point_public_reference',
    'target_snapshot_json',
    'destination_snapshot_json',
    'tracking_code',
    'provider_reference',
    'destination_registration_number',
    'destination_registration_date',
    'corr_dispatch_corr_fk',
    'corr_dispatch_party_fk',
    'corr_dispatch_attempt_dispatch_fk',
    'corr_dispatch_followup_dispatch_fk',
] as $needle) {
    if (!str_contains($migration, $needle)) {
        fwrite(
            STDERR,
            "FAIL: Missing dispatch schema contract: {$needle}\n"
        );
        exit(1);
    }
}

foreach ([
    "'correspondence_dispatches'",
    "'correspondence_dispatch_attempts'",
    "'correspondence_dispatch_followups'",
    "'corr_dispatch_corr_fk'",
    "'corr_dispatch_party_fk'",
    "'corr_dispatch_attempt_dispatch_fk'",
    "'corr_dispatch_followup_dispatch_fk'",
] as $needle) {
    if (!str_contains($contract, $needle)) {
        fwrite(
            STDERR,
            "FAIL: Missing schema parity contract: {$needle}\n"
        );
        exit(1);
    }
}

echo "Automation correspondence dispatch foundation checks passed.\n";
