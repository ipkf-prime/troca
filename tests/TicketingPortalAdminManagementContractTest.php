<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


$paths = [
    'service' =>
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'PortalManagementService.php',

    'route' =>
        $root
        . '/public_html/routes/'
        . 'ticketing-portal-management.php',

    'view' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'ticketing-portal-management.php',

    'rbac' =>
        $root
        . '/public_html/app/Services/'
        . 'AdminNavigationRbacService.php',

    'navigation' =>
        $root
        . '/public_html/app/Services/'
        . 'DynamicAdminNavigationService.php',

    'loader' =>
        $root
        . '/public_html/system/Routing/'
        . 'RouteLoader.php',
];


$content = [];


foreach (
    $paths
    as $key => $path
) {

    if (!is_file($path)) {
        throw new RuntimeException(
            'portal_admin_file_missing:'
            . $key
        );
    }


    $value =
        file_get_contents(
            $path
        );


    if (!is_string($value)) {
        throw new RuntimeException(
            'portal_admin_file_unreadable:'
            . $key
        );
    }


    $content[$key] =
        $value;
}


foreach ([
    'TICKETING_PORTAL_ADMIN_MANAGEMENT_READ_V1',
    'ticketing.project.manage',
    'ticketing_support_portal_hosts',
    'ticketing_support_project_brand_settings',
    'ticketing_support_portal_brand_settings',
    'ticketing_support_portal_landing_settings',
    'ticketing_support_portal_landing_items',
] as $needle) {

    if (
        !str_contains(
            $content[
                'service'
            ],
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_admin_service_marker_missing:'
            . $needle
        );
    }
}


/*
 * D2A1 service must remain read-only.
 */
$serviceWithoutComments =
    preg_replace(
        '#/\*.*?\*/|//[^\r\n]*#s',
        '',
        $content[
            'service'
        ]
    )
    ?? '';


if (
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i',
        $serviceWithoutComments
    ) === 1
) {
    throw new RuntimeException(
        'd2a1_service_write_sql_forbidden'
    );
}


if (
    substr_count(
        $content[
            'route'
        ],
        '$router->get('
    ) !== 1
) {
    throw new RuntimeException(
        'd2a1_get_route_count_invalid'
    );
}


/*
 * TICKETING_PORTAL_ADMIN_PHASE_TRANSITION_CONTRACT_V1
 *
 * D2A1 read-only phase allowed no POST routes.
 * Once the explicit B3 write-route marker exists, the
 * evolved contract requires the exact five protected POST
 * endpoints instead of forbidding POST globally.
 */
$routeWriteSurfaceActive =
    str_contains(
        $content[
            'route'
        ],
        'TICKETING_PORTAL_ADMIN_ROUTES_WRITE_V1'
    );


if (
    !$routeWriteSurfaceActive
    &&
    str_contains(
        $content[
            'route'
        ],
        '$router->post('
    )
) {
    throw new RuntimeException(
        'd2a1_post_route_forbidden'
    );
}


if (
    $routeWriteSurfaceActive
    &&
    substr_count(
        $content[
            'route'
        ],
        '$router->post('
    ) !== 5
) {
    throw new RuntimeException(
        'portal_write_route_transition_count_invalid'
    );
}


foreach ([
    'TICKETING_PORTAL_ADMIN_RBAC_V1',
    "'/admin/ticketing/portals'",
    'ticketing.project.manage',
] as $needle) {

    if (
        !str_contains(
            $content[
                'rbac'
            ],
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_admin_rbac_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'TICKETING_PORTAL_ADMIN_NAVIGATION_V1',
    'withTicketingPortalManagement',
    'ticketing-portals',
    '/admin/ticketing/portals',
    'ticketing.project.manage',
] as $needle) {

    if (
        !str_contains(
            $content[
                'navigation'
            ],
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_admin_navigation_marker_missing:'
            . $needle
        );
    }
}


if (
    !str_contains(
        $content[
            'loader'
        ],
        "BASE_PATH . '/routes/ticketing-portal-management.php'"
    )
) {
    throw new RuntimeException(
        'portal_admin_route_loader_missing'
    );
}


foreach ([
    'ticketing-dev.troca.ir',
    'ticketing.troca.ir',
] as $host) {

    foreach ([
        'service',
        'route',
        'view',
    ] as $target) {

        if (
            str_contains(
                $content[$target],
                $host
            )
        ) {
            throw new RuntimeException(
                'portal_admin_environment_host_hardcode:'
                . $target
            );
        }
    }
}


echo
    'TICKETING_PORTAL_ADMIN_MANAGEMENT_READ_CONTRACT_PASS'
    . PHP_EOL;


/*
 * TICKETING_PORTAL_ADMIN_LAYOUT_CONTRACT_V1
 *
 * Admin feature views must compose through the shared
 * Admin layout; otherwise shell CSS/JS/sidebar/header are
 * absent and SVG icons render at intrinsic size.
 */
foreach ([
    'TICKETING_PORTAL_ADMIN_LAYOUT_COMPOSITION_V1',
    'ob_start();',
    '$content =',
    'ob_get_clean();',
    "require __DIR__ . '/layout.php';",
] as $needle) {

    if (
        !str_contains(
            $content['view'],
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_admin_layout_marker_missing:'
            . $needle
        );
    }
}


echo
    'TICKETING_PORTAL_ADMIN_LAYOUT_CONTRACT_PASS'
    . PHP_EOL;


/*
 * TICKETING_PORTAL_ADMIN_WRITER_CONTRACT_V1
 */
$writerPath =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'PortalManagementWriteService.php';


$uploadPath =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'PortalMediaUploadService.php';


$writer =
    file_get_contents(
        $writerPath
    );


$upload =
    file_get_contents(
        $uploadPath
    );


if (
    !is_string($writer)
    || !is_string($upload)
) {
    throw new RuntimeException(
        'portal_writer_source_unreadable'
    );
}


foreach ([
    'TICKETING_PORTAL_ADMIN_WRITER_V1',
    'TICKETING_PORTAL_WRITER_CALLER_TRANSACTION_V1',
    'ticketing.project.manage',
    'saveHost',
    'saveBrand',
    'saveLandingSettings',
    'saveItem',
    'deleteItem',
    'ticketing_support_portal_hosts',
    'ticketing_support_project_brand_settings',
    'ticketing_support_portal_brand_settings',
    'ticketing_support_portal_landing_settings',
    'ticketing_support_portal_landing_items',
    'active_preset',
] as $needle) {

    if (
        !str_contains(
            $writer,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_writer_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'TICKETING_PORTAL_MEDIA_UPLOAD_V1',
    'ClamAvProcessScanner',
    'RESULT_CLEAN',
    'RESULT_INFECTED',
    'storage/quarantine/ticketing-portal',
    '/uploads/ticketing/portal/',
    'image/jpeg',
    'image/png',
    'image/webp',
    'is_uploaded_file',
    'getimagesize',
] as $needle) {

    if (
        !str_contains(
            $upload,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_upload_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'ticketing-dev.troca.ir',
    'ticketing.troca.ir',
] as $host) {

    if (
        str_contains(
            $writer,
            $host
        )
        ||
        str_contains(
            $upload,
            $host
        )
    ) {
        throw new RuntimeException(
            'portal_writer_host_hardcode_forbidden'
        );
    }
}


echo
    'TICKETING_PORTAL_ADMIN_WRITER_CONTRACT_PASS'
    . PHP_EOL;


/*
 * TICKETING_PORTAL_ADMIN_HTTP_WRITE_CONTRACT_V1
 */
$routePath =
    $root
    . '/public_html/routes/'
    . 'ticketing-portal-management.php';


$viewPath =
    $root
    . '/public_html/resources/views/admin/'
    . 'ticketing-portal-management.php';


$readPath =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'PortalManagementService.php';


$rbacPath =
    $root
    . '/public_html/app/Services/'
    . 'AdminNavigationRbacService.php';


$route =
    file_get_contents(
        $routePath
    );


$view =
    file_get_contents(
        $viewPath
    );


$read =
    file_get_contents(
        $readPath
    );


$rbac =
    file_get_contents(
        $rbacPath
    );


if (
    !is_string($route)
    || !is_string($view)
    || !is_string($read)
    || !is_string($rbac)
) {
    throw new RuntimeException(
        'portal_http_write_contract_source_unreadable'
    );
}


if (
    substr_count(
        $route,
        '$router->post('
    ) !== 5
) {
    throw new RuntimeException(
        'portal_post_route_count_invalid'
    );
}


foreach ([
    'TICKETING_PORTAL_ADMIN_ROUTES_WRITE_V1',
    '/admin/ticketing/portals/host',
    '/admin/ticketing/portals/brand',
    '/admin/ticketing/portals/settings',
    '/admin/ticketing/portals/items',
    '/admin/ticketing/portals/items/delete',
    'new \IPKF\Security\Csrf()',
    'PortalManagementWriteService',
    '$_FILES',
] as $needle) {

    if (
        !str_contains(
            $route,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_http_route_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'TICKETING_PORTAL_ADMIN_FORMS_V1',
    '/admin/ticketing/portals/host',
    '/admin/ticketing/portals/brand',
    '/admin/ticketing/portals/settings',
    '/admin/ticketing/portals/items',
    '/admin/ticketing/portals/items/delete',
    'multipart/form-data',
    'override_keys[]',
    'active_preset',
    'ob_get_clean();',
    "require __DIR__ . '/layout.php';",
] as $needle) {

    if (
        !str_contains(
            $view,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_http_view_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'TICKETING_PORTAL_ADMIN_FORM_READ_V1',
    'AdminThemeService',
    'theme_presets',
    'editing_item',
    'private function landingItem(',
] as $needle) {

    if (
        !str_contains(
            $read,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_form_read_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'TICKETING_PORTAL_ADMIN_POST_RBAC_V1',
    "'/admin/ticketing/portals/host'",
    "'/admin/ticketing/portals/brand'",
    "'/admin/ticketing/portals/settings'",
    "'/admin/ticketing/portals/items'",
    "'/admin/ticketing/portals/items/delete'",
] as $needle) {

    if (
        !str_contains(
            $rbac,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_post_rbac_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'ticketing-dev.troca.ir',
    'ticketing.troca.ir',
] as $host) {

    foreach ([
        $route,
        $view,
        $read,
    ] as $source) {

        if (
            str_contains(
                $source,
                $host
            )
        ) {
            throw new RuntimeException(
                'portal_http_environment_host_hardcode'
            );
        }
    }
}


echo
    'TICKETING_PORTAL_ADMIN_HTTP_WRITE_CONTRACT_PASS'
    . PHP_EOL;


/*
 * TICKETING_PORTAL_ADMIN_LOCALIZATION_CONTRACT_V1
 */
$localizedViewPath =
    $root
    . '/public_html/resources/views/admin/'
    . 'ticketing-portal-management.php';


$localizedWriterPath =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'PortalManagementWriteService.php';


$localizedView =
    file_get_contents(
        $localizedViewPath
    );


$localizedWriter =
    file_get_contents(
        $localizedWriterPath
    );


if (
    !is_string($localizedView)
    || !is_string($localizedWriter)
) {
    throw new RuntimeException(
        'portal_localization_source_unreadable'
    );
}


foreach ([
    'TICKETING_PORTAL_ADMIN_FA_LOCALIZATION_V1',
    'ticketing_portal_admin_digits',
    'ticketing_portal_admin_jalali_datetime',
    'ticketing_portal_admin_scope_label',
    'AdminFormat::digits',
    'AdminFormat::jalaliDateTime',
    'قلمرو',
    'دامنه و میزبان',
    'سراسری',
    'تنظیم اختصاصی',
    'فایلی انتخاب نشده است',
    'انتخاب فایل',
    'تاریخ شمسی',
    '۱۴۰۵/۰۶/۱۷',
] as $needle) {

    if (
        !str_contains(
            $localizedView,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_localization_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'TICKETING_PORTAL_ADMIN_JALALI_INPUT_V1',
    'JalaliDateInput::englishDigits',
    'JalaliDateInput::toGregorian',
    'integerInput',
] as $needle) {

    if (
        !str_contains(
            $localizedWriter,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_writer_localization_marker_missing:'
            . $needle
        );
    }
}


/*
 * These user-visible remnants must be gone.
 */
foreach ([
    '<span>Realm</span>',
    'دامنه و Host',
    'نام Host',
    'اسلات Host اصلی',
    'Override جدید',
    'ویرایش Override',
    'حالت Override',
    'ذخیره Override',
    'Overrideهای محتوای صفحه',
    'No file selected',
    'type="datetime-local"',
    'mm/dd/yyyy',
] as $forbidden) {

    if (
        str_contains(
            $localizedView,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'portal_visible_english_remnant:'
            . $forbidden
        );
    }
}


echo
    'TICKETING_PORTAL_ADMIN_LOCALIZATION_CONTRACT_PASS'
    . PHP_EOL;
