<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$seeder = file_get_contents(
    $root
    . '/public_html/system/Database/Seeds/'
    . 'CommunicationCenterSeeder.php'
);

$expected = <<<TEXT
['core', 'support', 'link', 'راهنما و پشتیبانی سامانه'
TEXT;

if (!str_contains($seeder, $expected)) {
    throw new RuntimeException(
        'Core support sidebar seed title is not normalized.'
    );
}

$old = <<<TEXT
['core', 'support', 'link', 'پشتیبانی'
TEXT;

if (str_contains($seeder, $old)) {
    throw new RuntimeException(
        'Legacy core support sidebar title still exists in seeder.'
    );
}

$repository = file_get_contents(
    $root
    . '/public_html/app/Repositories/'
    . 'AdminNavigationRegistryRepository.php'
);

if (
    !str_contains(
        $repository,
        'FROM admin_navigation_items'
    )
    || !str_contains(
        $repository,
        'WHERE shell_key = ?'
    )
) {
    throw new RuntimeException(
        'Sidebar registry repository contract changed.'
    );
}

$service = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'DynamicAdminNavigationService.php'
);

if (
    !str_contains(
        $service,
        "'title' => (string) \$item['title']"
    )
) {
    throw new RuntimeException(
        'Dynamic sidebar title presentation contract changed.'
    );
}

echo "CORE_SUPPORT_SIDEBAR_REGISTRY_PASS\n";
