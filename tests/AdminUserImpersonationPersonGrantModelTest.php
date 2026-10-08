<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $value =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'read_failed:'
                . $relative
            );
        }

        return $value;
    };

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CorrectAdminUserImpersonationOperateGrantModel.php'
    );

$access =
    $read(
        'public_html/app/Repositories/'
        . 'AccessControlRepository.php'
    );

$authorization =
    $read(
        'public_html/app/Repositories/'
        . 'ImpersonationAuthorizationRepository.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


foreach ([
    'users.impersonate.operate',
    'users.impersonate.operate.assign',
    'DELETE FROM role_permissions',
    'roles.is_system = 1',
    'roles.can_manage_other_users = 1',
    'count(',
] as $token) {
    $expect(
        str_contains(
            $migration,
            $token
        ),
        'migration_contract_missing:'
        . $token
    );
}

echo "PERSON_GRANT_CORRECTION_MIGRATION=PASS\n";


foreach ([
    'super_admin',
    'system_admin',
    'central_admin',
    'province_admin',
] as $roleName) {
    $expect(
        !str_contains(
            $migration,
            $roleName
        ),
        'role_name_hardcode_found:'
        . $roleName
    );
}

echo "ROLE_NAME_AUTHORIZATION=NO\n";


$expect(
    substr_count(
        $access,
        "'users.impersonate.operate'"
    ) >= 2
    &&
    substr_count(
        $access,
        "'users.impersonate.operate.assign'"
    ) >= 2
    &&
    str_contains(
        $access,
        '$protectedExistingCodes'
    ),
    'generic_role_editor_protection_missing'
);

echo "GENERIC_ROLE_EDITOR_SPECIAL_PERMISSION_PROTECTION=PASS\n";


$overridePosition =
    strpos(
        $authorization,
        '$this->permissionOverride('
    );

$roleFallbackPosition =
    strpos(
        $authorization,
        'INNER JOIN role_permissions'
    );

$expect(
    $overridePosition !== false
    && $roleFallbackPosition !== false
    && $overridePosition
        < $roleFallbackPosition,
    'person_override_must_precede_role_fallback'
);

$expect(
    str_contains(
        $authorization,
        'overrides.role_assignment_id'
    )
    &&
    str_contains(
        $authorization,
        "IN (0, ?)"
    ),
    'person_assignment_override_contract_missing'
);

echo "PERSON_OVERRIDE_PRECEDES_ROLE_FALLBACK=PASS\n";


$expect(
    substr_count(
        $registry,
        'CorrectAdminUserImpersonationOperateGrantModel::class'
    ) === 1,
    'correction_registry_binding_invalid'
);

echo "CORRECTIVE_MIGRATION_REGISTERED=PASS\n";
echo "ADMIN_USER_IMPERSONATION_PERSON_GRANT_MODEL=PASS\n";
