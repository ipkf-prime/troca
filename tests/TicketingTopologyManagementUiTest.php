<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$repository =
    file_get_contents(
        $root
        . '/public_html/app/Repositories/'
        . 'SupportTopologyAdminRepository.php'
    );

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'SupportTopologyAdminService.php'
    );

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'ticketing-topology.php'
    );

$projectView =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'ticketing-projects.php'
    );

$routes =
    file_get_contents(
        $root
        . '/public_html/routes/'
        . 'ticketing-runtime.php'
    );

$rbac =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'EnableTicketingTopologyManagementRoutes.php'
    );

$registry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$publicMigrate =
    file_get_contents(
        $root
        . '/public_html/public/migrate.php'
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
    'wouldCreateCycle',
    'relationExists',
    'teamNodeBindingExists',
    'ticketing_support_layers',
    'ticketing_support_nodes',
    'ticketing_support_node_relations',
    'ticketing_support_teams',
    'ticketing_support_queues',
    'ticketing_support_team_members',
] as $needle) {

    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Repository contract missing: '
        . $needle
    );
}


foreach ([
    'layer.create',
    'node.create',
    'relation.create',
    'team.create',
    'queue.create',
    'team_node.bind',
    'team_queue.bind',
    'team_member.add',
    'wouldCreateCycle',
    'rank_order',
    'کارشناس پشتیبانی باید به حساب Core متصل باشد',
] as $needle) {

    $expect(
        str_contains(
            $service,
            $needle
        ),
        'Service contract missing: '
        . $needle
    );
}


foreach ([
    'لایه‌های پشتیبانی',
    'گره‌های پشتیبانی',
    'ارتباط بین گره‌ها',
    'تیم‌های پشتیبانی',
    'صف‌های پشتیبانی',
    'کارشناسان تیم',
    'Take Over',
] as $needle) {

    $expect(
        str_contains(
            $view,
            $needle
        ),
        'Topology UI contract missing: '
        . $needle
    );
}


$route =
    '/admin/ticketing/projects/'
    . '{public_reference}/topology';

$expect(
    substr_count(
        $routes,
        $route
    ) >= 2,
    'GET/POST topology routes missing.'
);


$expect(
    str_contains(
        $rbac,
        'ticketing.project.manage'
    ),
    'Topology RBAC permission missing.'
);


$expect(
    str_contains(
        $registry,
        'EnableTicketingTopologyManagementRoutes::class'
    ),
    'Topology Core migration not registered.'
);


$expect(
    !str_contains(
        $publicMigrate,
        'EnableTicketingTopologyManagementRoutes()'
    ),
    'Topology RBAC migration must not be inserted '
    . 'into the legacy public migration sequence.'
);


$expect(
    str_contains(
        $projectView,
        "\$topologyUrl"
    ),
    'Project topology URL variable missing.'
);


$expect(
    str_contains(
        $projectView,
        ". '/topology';"
    ),
    'Topology URL slash contract missing.'
);


$expect(
    !str_contains(
        $projectView,
        ". 'topology';"
    ),
    'Broken topology URL concatenation remains.'
);


foreach ([
    'payesh',
    'np_',
    'rural',
    'cooperative',
    'تعاونی',
    'اتحادیه',
    'استان',
    'شهرستان',
    'نهاده',
] as $forbidden) {

    $combined =
        $repository
        . $service
        . $routes
        . $rbac;

    $expect(
        !str_contains(
            mb_strtolower(
                $combined,
                'UTF-8'
            ),
            mb_strtolower(
                $forbidden,
                'UTF-8'
            )
        ),
        'Business-specific hardcode leaked: '
        . $forbidden
    );
}


$expect(
    !str_contains(
        $repository,
        'core.primary'
    ),
    'Topology repository must not query Core DB.'
);


echo "TICKETING_TOPOLOGY_MANAGEMENT_UI_PASS\n";
