<?php

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $path
    );

    if (!is_string($content)) {
        throw new RuntimeException(
            'Unable to read ' . $path
        );
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$layout = $read(
    'public_html/resources/views/admin/layout.php'
);

$dashboard = $read(
    'public_html/app/Services/Work/'
    . 'WorkDashboardService.php'
);

$myItems = $read(
    'public_html/app/Services/Work/'
    . 'WorkMyItemsService.php'
);

$routeLoader = $read(
    'public_html/system/Routing/RouteLoader.php'
);

$runtimeRoutes = $read(
    'public_html/routes/work-runtime.php'
);

$compose = $read(
    'public_html/resources/views/admin/'
    . 'messages-compose.php'
);

$style = $read(
    'public_html/resources/views/admin/partials/'
    . 'communication-style.php'
);

$expect(
    !str_contains($layout, 'â–¾'),
    'Broken sidebar symbol is still present.'
);

$expect(
    str_contains(
        $layout,
        'aria-expanded="<?='
    )
    && str_contains(
        $layout,
        'data-admin-nav-children'
    )
    && str_contains(
        $layout,
        'activeChild'
    ),
    'Active accordion state is missing.'
);

$expect(
    str_contains(
        $dashboard,
        '$this->myItems->view('
    )
    && str_contains(
        $dashboard,
        '$userId'
    ),
    'Dashboard does not pass explicit user context.'
);

$expect(
    str_contains(
        $myItems,
        '?int $userId = null'
    )
    && str_contains(
        $myItems,
        '$userId ??='
    ),
    'My Work still depends only on session lookup.'
);

$expect(
    str_contains(
        $routeLoader,
        '/routes/work-runtime.php'
    )
    && str_contains(
        $runtimeRoutes,
        "'project_index'"
    ),
    'Work runtime route override is missing.'
);

$expect(
    str_contains(
        $compose,
        '$messageStatus'
    )
    && !str_contains(
        $compose,
        '$status = (string)'
    ),
    'Compose status collision is not fixed.'
);

$expect(
    str_contains(
        $style,
        '@media (max-width: 760px)'
    )
    && str_contains(
        $style,
        'overflow-x: auto'
    )
    && str_contains(
        $style,
        'communication-form__wide'
    ),
    'Responsive minimal communication styles are missing.'
);

echo "Work runtime and communication UI file checks passed.\n";
