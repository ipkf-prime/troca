<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'DynamicPermissionRegistryService.php'
);

$registry = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'ApplicationModuleRegistryService.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(
    str_contains(
        $service,
        'INSERT INTO permissions'
    ),
    'Dynamic module permissions must use existing permissions table.'
);

$expect(
    !str_contains(
        $service,
        'UPDATE permissions'
        . PHP_EOL
        . '                SET'
        . PHP_EOL
        . '                    is_active = 0'
    ),
    'Module disable must not bulk-deactivate permission catalog rows.'
);

$expect(
    str_contains(
        $service,
        'Module availability and permission-catalog state'
    ),
    'Module/permission lifecycle separation contract is missing.'
);

$expect(
    !str_contains(
        $service,
        'DELETE FROM role_permissions'
    ),
    'Module disable must preserve role assignments.'
);

$expect(
    str_contains(
        $registry,
        'DynamicPermissionRegistryService'
    )
    && str_contains(
        $registry,
        '->syncModule($runtimeModule)'
    ),
    'Module save must trigger RBAC synchronization.'
);

echo "DYNAMIC_MODULE_EXISTING_RBAC_PASS\n";
