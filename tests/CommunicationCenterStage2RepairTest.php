<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$apply = $read(
    'tools/apply-communication-center-stage2.php'
);
$checker = $read(
    'public_html/scripts/check-communication-center.php'
);
$workChecker = $read(
    'public_html/scripts/check-work-runtime.php'
);

$expect(
    str_contains(
        $apply,
        'CreateCommunicationCenterFoundationTables'
    )
    && str_contains(
        $apply,
        'CommunicationCenterSeeder'
    )
    && str_contains(
        $apply,
        "routes/communication-center.php"
    ),
    'Stage 2 registry repair is incomplete.'
);

$expect(
    str_contains(
        $apply,
        '$adminUserVerificationRedirect'
    )
    && str_contains(
        $apply,
        "admin-users-manage.php"
    ),
    'Admin user route closure repair is missing.'
);

$expect(
    str_contains(
        $apply,
        'seenSeeders'
    ),
    'Duplicate seeder cleanup is missing.'
);

$expect(
    str_contains(
        $checker,
        'migration_required'
    )
    && str_contains(
        $checker,
        'missing_tables'
    ),
    'Communication checker is not migration-safe.'
);

$expect(
    str_contains(
        $workChecker,
        'service_projects'
    )
    && str_contains(
        $workChecker,
        'connected_database'
    ),
    'Work runtime checker is incomplete.'
);

echo "Communication Center Stage 2 R1 repair checks passed.\n";
