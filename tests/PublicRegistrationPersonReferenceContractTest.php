<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/public_html/app/Services/PublicRegistrationService.php';

$migrationPath =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'RepairPersonPublicReferencesForOnboarding.php';

$registryPath =
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';


foreach ([
    $servicePath,
    $migrationPath,
    $registryPath,
] as $path) {
    if (!is_file($path)) {
        throw new RuntimeException(
            'required_file_missing:'
            . $path
        );
    }
}


$service =
    file_get_contents(
        $servicePath
    );

$migration =
    file_get_contents(
        $migrationPath
    );

$registry =
    file_get_contents(
        $registryPath
    );

if (
    !is_string($service)
    || !is_string($migration)
    || !is_string($registry)
) {
    throw new RuntimeException(
        'contract_source_read_failed'
    );
}


if (
    substr_count(
        $service,
        '$this->ensurePersonPublicReference('
    ) !== 1
) {
    throw new RuntimeException(
        'registration_person_reference_call_invalid'
    );
}


if (
    substr_count(
        $service,
        'private function ensurePersonPublicReference('
    ) !== 1
) {
    throw new RuntimeException(
        'registration_person_reference_method_invalid'
    );
}


if (
    !str_contains(
        $service,
        'public_reference ='
    )
    ||
    !str_contains(
        $service,
        'UUID()'
    )
) {
    throw new RuntimeException(
        'registration_person_reference_generation_missing'
    );
}


$callPosition =
    strpos(
        $service,
        '$this->ensurePersonPublicReference('
    );

$attemptPosition =
    strpos(
        $service,
        '$attempt =',
        $callPosition
    );

if (
    $callPosition === false
    || $attemptPosition === false
    || $callPosition >= $attemptPosition
) {
    throw new RuntimeException(
        'person_reference_must_precede_otp_attempt'
    );
}


foreach ([
    'WHERE public_reference IS NULL',
    'UUID()',
    'persons_public_reference_unique',
    'uniqueSingleColumnIndexExists',
] as $required) {
    if (
        !str_contains(
            $migration,
            $required
        )
    ) {
        throw new RuntimeException(
            'migration_contract_missing:'
            . $required
        );
    }
}


$registryTarget =
    '\\IPKF\\Database\\Migrations\\RepairPersonPublicReferencesForOnboarding::class';

if (
    substr_count(
        $registry,
        $registryTarget
    ) !== 1
) {
    throw new RuntimeException(
        'migration_registry_contract_invalid'
    );
}


echo
    "PUBLIC_REGISTRATION_PERSON_REFERENCE_CONTRACT=PASS\n";
