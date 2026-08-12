<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$servicePath =
    $root
    . '/public_html/app/Services/Automation/'
    . 'Correspondence/'
    . 'CorrespondenceRegistrationService.php';

$enterpriseMigrationPath =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateEnterpriseAutomationSecretariatFoundation.php';

$foundationMigrationPath =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateAutomationCorrespondenceFoundationTables.php';

$service =
    file_get_contents(
        $servicePath
    );

$enterprise =
    file_get_contents(
        $enterpriseMigrationPath
    );

$foundation =
    file_get_contents(
        $foundationMigrationPath
    );

$expect = static function (
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

$expect(
    is_string($service)
    && is_string($enterprise)
    && is_string($foundation),
    'Registration sources must be readable.'
);

$expect(
    str_contains(
        $foundation,
        'correspondence_registrations'
    )
    && str_contains(
        $foundation,
        'registered_at TIMESTAMP NULL'
    ),
    'Correspondence foundation must own official registration persistence.'
);

$expect(
    str_contains(
        $enterprise,
        'registry_number_reservations'
    )
    && str_contains(
        $enterprise,
        'number_reservation_id'
    )
    && str_contains(
        $enterprise,
        'registry_number_sequences'
    ),
    'Enterprise secretariat foundation must own sequence reservation linkage.'
);

foreach ([
    'FOR UPDATE',
    'registry_book_directions',
    'registry_number_sequences',
    'registry_number_reservations',
    'correspondence_registrations',
    "'official'",
    "'registered'",
    'registered_at',
    "'registered',",
    'next_sequence_number',
] as $needle) {
    $expect(
        str_contains(
            $service,
            $needle
        ),
        "Missing registration contract: {$needle}"
    );
}

$expect(
    str_contains(
        $service,
        'secretariat_desk_appointments'
    )
    && str_contains(
        $service,
        'secretariat_desk_organizations'
    ),
    'Official registration must enforce desk membership and serviced organization.'
);

$expect(
    str_contains(
        $service,
        'reservationHasIdempotency'
    ),
    'Number reservation must remain rolling-deploy compatible with idempotency migration.'
);

echo "Automation correspondence registration service checks passed.\n";
