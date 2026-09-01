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
                'Unreadable: '
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

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketStatusTitleManagementService.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-statuses.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$rbac =
    $read(
        'public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'EnableTicketingStatusTitleManagement.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$projects =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-projects.php'
    );


$expect(
    str_contains(
        $service,
        'UPDATE ticketing_statuses'
    )
    &&
    str_contains(
        $service,
        'SET title = ?'
    ),
    'Title-only update contract missing.'
);

$updatePosition =
    strpos(
        $service,
        'UPDATE ticketing_statuses'
    );

$update =
    substr(
        $service,
        $updatePosition !== false
            ? $updatePosition
            : 0,
        400
    );

foreach ([
    'SET code',
    'SET category',
    'SET sort_order',
    'SET is_closed',
    'SET is_system',
    'SET is_active',
] as $forbidden) {
    $expect(
        !str_contains(
            $update,
            $forbidden
        ),
        'Forbidden mutation: '
        . $forbidden
    );
}

foreach ([
    'name="category"',
    'name="sort_order"',
    'name="is_closed"',
    'name="is_system"',
    'name="is_active"',
] as $forbidden) {
    $expect(
        !str_contains(
            $view,
            $forbidden
        ),
        'Forbidden editable UI field: '
        . $forbidden
    );
}

$expect(
    str_contains(
        $view,
        'name="title"'
    )
    &&
    str_contains(
        $view,
        'name="code"'
    ),
    'Status title form incomplete.'
);

$expect(
    str_contains(
        $routes,
        'TICKETING_STATUS_TITLE_MANAGEMENT'
    )
    &&
    substr_count(
        $routes,
        "'/admin/ticketing/statuses'"
    ) >= 2
    &&
    str_contains(
        $routes,
        'new \App\Services\Ticketing\TicketStatusTitleManagementService();'
    ),
    'Status runtime contract incomplete.'
);

$expect(
    str_contains(
        $rbac,
        "'/admin/ticketing/statuses' "
        . "=> 'ticketing.project.manage'"
    ),
    'Static RBAC missing.'
);

foreach ([
    'EnableTicketingStatusTitleManagement',
    '/admin/ticketing/statuses',
    'ticketing.project.manage',
    'admin_route_permissions',
    "'GET'",
    "'POST'",
] as $marker) {
    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'Migration marker missing: '
        . $marker
    );
}

$expect(
    str_contains(
        $registry,
        'EnableTicketingStatusTitleManagement::class'
    ),
    'Migration registry missing.'
);

$expect(
    str_contains(
        $projects,
        '/admin/ticketing/statuses'
    )
    &&
    str_contains(
        $projects,
        'عنوان وضعیت‌ها'
    ),
    'Management link missing.'
);

echo
    "TICKETING_STATUS_TITLE_MANAGEMENT_PASS\n";
