<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'migration' => $root . '/public_html/system/Database/Migrations/CreateWorkExternalSourceBridgeFoundation.php',
    'registry' => $root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php',
    'repository' => $root . '/public_html/app/Repositories/WorkExternalSourceBridgeRepository.php',
    'service' => $root . '/public_html/app/Services/Work/WorkExternalSourceBridgeService.php',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        throw new RuntimeException(
            'Missing ' . $name . ': ' . $path
        );
    }
}

$migration = file_get_contents($files['migration']);
$registry = file_get_contents($files['registry']);
$repository = file_get_contents($files['repository']);
$service = file_get_contents($files['service']);

if (
    !is_string($migration)
    || !is_string($registry)
    || !is_string($repository)
    || !is_string($service)
) {
    throw new RuntimeException('Unable to read bridge source.');
}

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
/*
 * ApplicationMigrationRunner invokes Migration::up() without arguments.
 * ApplicationMigrationRegistry injects PDO into the Migration constructor.
 *
 * Keep this executable contract explicit so a migration cannot accidentally
 * use a legacy up(PDO)/down(PDO) signature that PHP lint alone will not catch.
 */
$expect(
    str_contains(
        $migration,
        'public function up(): void'
    ),
    'Migration runner compatibility missing: zero-argument up().'
);

$expect(
    str_contains(
        $migration,
        'public function down(): void'
    ),
    'Migration runner compatibility missing: zero-argument down().'
);

$expect(
    !str_contains(
        $migration,
        'public function up(\PDO $db): void'
    ),
    'Legacy migration up(PDO) signature is forbidden.'
);

$expect(
    !str_contains(
        $migration,
        'public function down(\PDO $db): void'
    ),
    'Legacy migration down(PDO) signature is forbidden.'
);

$expect(
    substr_count(
        $migration,
        '$this->db->exec('
    ) === 4,
    'Migration must use constructor-injected $this->db for all four DDL operations.'
);

$expect(
    !str_contains(
        $migration,
        '$db->exec('
    ),
    'Local $db DDL handle is forbidden in this migration.'
);


foreach ([
    'work_project_source_bindings',
    'work_item_source_links',
    'work_project_source_bindings_project_fk',
    'work_item_source_links_item_fk',
    'source_module_code',
    'source_resource_type',
    'source_reference',
    'binding_role_code',
    'relation_type_code',
] as $needle) {
    $expect(
        str_contains($migration, $needle),
        'Migration contract missing: ' . $needle
    );
}

$expect(
    str_contains(
        $registry,
        '\\IPKF\\Database\\Migrations\\CreateWorkExternalSourceBridgeFoundation::class,'
    ),
    'Migration registry entry missing.'
);

foreach ([
    "resolve('work.primary')",
    'bindingsForSource',
    'createProjectBinding',
    'createItemSourceLink',
    'linkedItemsForSource',
] as $needle) {
    $expect(
        str_contains($repository, $needle),
        'Repository contract missing: ' . $needle
    );
}

foreach ([
    'bindProject',
    'linkItem',
    'linkedItems',
    'sourceIdentity',
    'newReference',
] as $needle) {
    $expect(
        str_contains($service, $needle),
        'Service contract missing: ' . $needle
    );
}

foreach ([
    'ticketing.primary',
    'core.primary',
    'FOREIGN KEY (source_',
] as $forbidden) {
    $expect(
        !str_contains(
            $migration . $repository . $service,
            $forbidden
        ),
        'Cross-domain coupling detected: ' . $forbidden
    );
}

foreach ([
    'سامانه نپ',
    'سامانه پایش',
    'اتحادیه مرکزی',
    'corc',
    'payesh',
] as $forbidden) {
    $expect(
        !str_contains(
            mb_strtolower(
                $migration . $repository . $service,
                'UTF-8'
            ),
            mb_strtolower($forbidden, 'UTF-8')
        ),
        'Customer-specific hardcode detected: ' . $forbidden
    );
}

$expect(
    !str_contains($service, 'auto_close'),
    'Work bridge must not auto-close external resources.'
);

echo "WORK_EXTERNAL_SOURCE_BRIDGE_FOUNDATION_TEST=PASS\n";
