<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static fn (
        string $path
    ): string =>
        file_get_contents(
            $root . '/' . $path
        );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'EnableTicketingProjectManagement.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'SupportProjectAdminRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'SupportProjectAdminService.php'
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

$list =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-projects.php'
    );

$form =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-project-form.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
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
    'ticketing.project.manage',
    "'super_admin'",
    "'system_admin'",
    'ticketing-projects',
    '/admin/ticketing/projects',
] as $needle) {
    $expect(
        str_contains(
            $migration,
            $needle
        ),
        'RBAC/navigation contract missing: '
        . $needle
    );
}


foreach ([
    'public function index(',
    'public function findByReference(',
    'public function codeExists(',
    'public function create(',
    'public function update(',
] as $needle) {
    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Admin repository contract missing: '
        . $needle
    );
}


foreach ([
    'public function createForm(',
    'public function editForm(',
    'public function create(',
    'public function update(',
    "'TSP-'",
    'AdminIcon::supports',
] as $needle) {
    $expect(
        str_contains(
            $service,
            $needle
        ),
        'Admin service contract missing: '
        . $needle
    );
}


foreach ([
    "/admin/ticketing/projects'",
    "/admin/ticketing/projects/create'",
    "/admin/ticketing/projects/{public_reference}/edit'",
    "/admin/ticketing/projects/{public_reference}'",
    'SupportProjectAdminService',
    'new \IPKF\Security\Csrf()',
] as $needle) {
    $expect(
        str_contains(
            $routes,
            $needle
        ),
        'Project route contract missing: '
        . $needle
    );
}


$expect(
    str_contains(
        $rbac,
        "'/admin/ticketing/projects' => 'ticketing.project.manage'"
    ),
    'Project management RBAC route missing.'
);


$expect(
    substr_count(
        $routes,
        "\$request->route("
    ) >= 3,
    'Parameterized Ticketing routes must read route parameters from Request.'
);

$expect(
    !str_contains(
        $routes,
        '\$parameters'
    ),
    'Router dispatch provides only Request and Response; route closures must not require a third parameters argument.'
);


$expect(
    str_contains(
        $list,
        'پروژه‌های پشتیبانی'
    )
    && str_contains(
        $list,
        'پروژه جدید'
    ),
    'Project list UI missing.'
);


$expect(
    str_contains(
        $form,
        'کد پروژه پس از ایجاد'
    )
    && str_contains(
        $form,
        'ذخیره پروژه'
    ),
    'Project form UI missing.'
);


$expect(
    str_contains(
        $registry,
        'EnableTicketingProjectManagement::class'
    ),
    'Core project-management migration not registered.'
);


foreach ([
    'TSP-NEP',
    'TSVC-NEP',
    "'nep'",
    'نهاده پخش',
] as $forbidden) {

    $expect(
        !str_contains(
            $repository,
            $forbidden
        )
        && !str_contains(
            $service,
            $forbidden
        )
        && !str_contains(
            $routes,
            $forbidden
        )
        && !str_contains(
            $migration,
            $forbidden
        ),
        'Business project hardcode leaked into project administration: '
        . $forbidden
    );
}


echo "TICKETING_PROJECT_MANAGEMENT_FOUNDATION_PASS\n";
