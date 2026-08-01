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

$repository = $read(
    'public_html/app/Repositories/AdminUserManagementRepository.php'
);
$adminService = $read(
    'public_html/app/Services/AdminUserManagementService.php'
);
$selfService = $read(
    'public_html/app/Services/SelfProfileService.php'
);
$adminView = $read(
    'public_html/resources/views/admin/admin-user-form.php'
);
$selfView = $read(
    'public_html/resources/views/admin/self-profile-edit.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/RepairPersonAddressReferenceDataAndGeography.php'
);
$registry = $read(
    'public_html/system/Database/Application/ApplicationMigrationRegistry.php'
);
$migrate = $read('public_html/public/migrate.php');

$expect(
    str_contains(
        $repository,
        'geographic_locations'
    )
    && str_contains(
        $repository,
        'geographic_location_relations'
    )
    && str_contains(
        $repository,
        'dynamicGeographyOptions'
    ),
    'Dynamic geography source is not implemented.'
);

$expect(
    str_contains(
        $repository,
        'geographic_location_id'
    )
    && str_contains(
        $repository,
        'syncPrimaryAddress'
    ),
    'Dynamic location persistence is incomplete.'
);

$expect(
    str_contains(
        $adminService,
        'province_location_id'
    )
    && str_contains(
        $selfService,
        'city_location_id'
    )
    && str_contains(
        $adminService,
        'validGeographicSelection'
    ),
    'Services still use legacy geography IDs.'
);

$expect(
    str_contains(
        $adminView,
        'name="province_location_id"'
    )
    && str_contains(
        $selfView,
        'name="county_location_id"'
    ),
    'Address forms are not wired to dynamic geography.'
);

$expect(
    str_contains($migration, "'home'")
    && str_contains($migration, "'work'")
    && str_contains(
        $migration,
        "'correspondence'"
    ),
    'Address types are not seeded.'
);

$expect(
    str_contains(
        $registry,
        'RepairPersonAddressReferenceDataAndGeography'
    )
    && str_contains(
        $migrate,
        'RepairPersonAddressReferenceDataAndGeography'
    ),
    'Address repair migration is not registered.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $repository . $migration
    ),
    'Destructive SQL is present.'
);

echo "Address dynamic geography fix checks passed.\n";
