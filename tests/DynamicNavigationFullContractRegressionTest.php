<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once
    $root
    . '/public_html/vendor/autoload.php';

$class =
    \App\Services\DynamicAdminNavigationService::class;

$reflection =
    new ReflectionClass($class);

$contracts = [
    'navigation' => 2,
    'topbar' => 2,
    'account' => 2,
    'children' => 3,
    'sync' => 0,
];

foreach ($contracts as $method => $required) {

    if (!$reflection->hasMethod($method)) {
        throw new RuntimeException(
            'Missing navigation method: '
            . $method
        );
    }

    if (
        $reflection
            ->getMethod($method)
            ->getNumberOfRequiredParameters()
        !== $required
    ) {
        throw new RuntimeException(
            'Navigation signature mismatch: '
            . $method
        );
    }
}

$source = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'DynamicAdminNavigationService.php'
);

foreach ([
    'placement_code',
    'permission_codes_json',
    'parent_id',
    'application_modules',
    'public function sync(): void',
] as $token) {

    if (!str_contains($source, $token)) {
        throw new RuntimeException(
            'Navigation contract token missing: '
            . $token
        );
    }
}

/*
 * shell_key is a repository/database concern.
 * The service contract is expressed through the
 * required $shellKey parameter passed to items().
 */
if (
    !str_contains(
        $source,
        'function navigation(int $userId, string $shellKey)'
    )
    || !str_contains(
        $source,
        'function topbar(int $userId, string $shellKey)'
    )
    || !str_contains(
        $source,
        '$this->items($shellKey)'
    )
) {
    throw new RuntimeException(
        'Shell-scoped navigation contract missing.'
    );
}

$layout = file_get_contents(
    $root
    . '/public_html/resources/views/admin/layout.php'
);

foreach ([
    '->navigation(',
    '->topbar(',
] as $call) {

    if (!str_contains($layout, $call)) {
        throw new RuntimeException(
            'Layout call missing: '
            . $call
        );
    }
}

echo "DYNAMIC_NAVIGATION_FULL_CONTRACT_PASS\n";
