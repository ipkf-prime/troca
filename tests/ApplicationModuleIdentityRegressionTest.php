<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'ApplicationModuleRegistryService.php'
    );

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'settings.php'
    );

$web =
    file_get_contents(
        $root
        . '/public_html/routes/web.php'
    );

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


foreach ([
    "'catalog_key'",
    '$catalogKey',
    'array_key_exists($catalogKey, $catalog)',
    '$key = $catalogKey;',
] as $needle) {
    $expect(
        str_contains(
            $service,
            $needle
        ),
        'Immutable catalog identity '
        . 'contract missing: '
        . $needle
    );
}


$expect(
    str_contains(
        $view,
        'name="module_key" required readonly'
    ),
    'Catalog module_key input '
    . 'must be readonly by default.'
);


$expect(
    str_contains(
        $view,
        "keyInput.readOnly ="
    )
    && str_contains(
        $view,
        "key !== 'custom'"
    ),
    'Only custom modules may edit module_key.'
);


$expect(
    str_contains(
        $web,
        'ApplicationModuleRegistryService())->save($request->all())'
    ),
    'Module settings route must pass catalog_key '
    . 'through to the service.'
);


echo "APPLICATION_MODULE_IDENTITY_PASS\n";
