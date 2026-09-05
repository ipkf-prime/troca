<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'EnsureTransactionalIdentityAccessGovernanceWriteSet.php'
    );

$registry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

if (
    !is_string($migration)
    || !is_string($registry)
) {
    fwrite(
        STDERR,
        "FAIL: transactional migration source unavailable.\n"
    );

    exit(1);
}

$tables = [
    'persons',
    'person_profiles',
    'users',
    'person_contacts',
    'person_addresses',
    'identity_change_requests',
    'user_org_assignments',
    'organization_appointments',

    'roles',
    'role_permissions',
    'role_scope_policies',
    'role_identity_requirements',

    'user_role_assignments',
    'role_assignment_scopes',
    'role_assignment_constraints',
    'user_permission_overrides',

    'access_control_change_logs',
];

foreach ($tables as $table) {
    if (
        !str_contains(
            $migration,
            "'{$table}'"
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: migration table missing: {$table}\n"
        );

        exit(1);
    }
}

$transactional =
    'EnsureTransactionalIdentityAccessGovernanceWriteSet::class';

$lifecycle =
    'AddRoleAssignmentLifecycleGovernance::class';

$transactionalPosition =
    strpos(
        $registry,
        $transactional
    );

$lifecyclePosition =
    strpos(
        $registry,
        $lifecycle
    );

if (
    $transactionalPosition === false
    || $lifecyclePosition === false
    || $transactionalPosition >= $lifecyclePosition
) {
    fwrite(
        STDERR,
        "FAIL: transactional migration must precede lifecycle migration.\n"
    );

    exit(1);
}

foreach (
    [
        'ENGINE=InnoDB',
        'FULLTEXT',
        'SPATIAL',
        'information_schema.TRIGGERS',
        'information_schema.PARTITIONS',
        'information_schema.KEY_COLUMN_USAGE',
        'innodb_not_supported',
        'unsafe_transactional_engine_conversion',
        'non_transactional_governance_table',
    ]
    as $marker
) {
    if (
        !str_contains(
            $migration,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: migration safety marker missing: {$marker}\n"
        );

        exit(1);
    }
}

foreach (
    [
        'SET GLOBAL',
        'SET @@GLOBAL',
        'default_storage_engine =',
    ]
    as $forbidden
) {
    if (
        str_contains(
            strtoupper($migration),
            strtoupper($forbidden)
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: global database setting mutation forbidden.\n"
        );

        exit(1);
    }
}

echo "TRANSACTIONAL_IDENTITY_WRITE_SET=PASS\n";
echo "TRANSACTIONAL_ROLE_GOVERNANCE_WRITE_SET=PASS\n";
echo "TRANSACTIONAL_SCOPE_GOVERNANCE_WRITE_SET=PASS\n";
echo "GLOBAL_STORAGE_ENGINE_UNCHANGED=PASS\n";
echo "TRANSACTIONAL_MIGRATION_ORDER=PASS\n";
echo "TRANSACTIONAL_GOVERNANCE_WRITE_SET_FOUNDATION=PASS\n";
