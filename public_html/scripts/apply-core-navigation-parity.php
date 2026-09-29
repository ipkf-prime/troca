<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

$db = \IPKF\Database\Database::connect();

if (
    !\IPKF\Database\Database::tableExists(
        'admin_navigation_items'
    )
    ||
    !\IPKF\Database\Database::tableExists(
        'application_modules'
    )
) {
    throw new RuntimeException(
        'navigation_registry_unavailable'
    );
}

$rootId = static function (
    PDO $db,
    string $key
): int {
    $statement =
        $db->prepare("
            SELECT id
            FROM admin_navigation_items
            WHERE shell_key = 'core'
              AND parent_id IS NULL
              AND item_key = ?
            LIMIT 1
        ");

    $statement->execute([$key]);

    return (int) $statement->fetchColumn();
};

$permissionsJson =
    static fn (array $permissions): string =>
        json_encode(
            array_values($permissions),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

$pathsJson =
    static fn (array $paths): string =>
        json_encode(
            array_values($paths),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

/*
 * CORE_NAVIGATION_PARITY_DATA_V1
 *
 * These are navigable section entry points.
 * Detail/edit/action routes intentionally are not menu entries.
 */
$children = [
    'users' => [
        [
            'users-list',
            'کاربران',
            'مشاهده و مدیریت حساب‌های کاربری',
            '/admin/users',
            'user-group',
            'blue',
            ['users.view'],
            ['/admin/users', '/admin/users/*'],
            10,
        ],
    ],

    'organization' => [
        [
            'organization-setup',
            'راه‌اندازی ساختار',
            'ثبت سازمان، واحد، پست و اتصال کاربر به شخص',
            '/admin/organization-setup',
            'building',
            'teal',
            ['organizations.manage'],
            ['/admin/organization-setup'],
            10,
        ],
        [
            'organization-chart',
            'چارت سازمانی',
            'نمایش سلسله‌مراتب واحدها، پست‌ها و متصدیان',
            '/admin/organization-chart',
            'organization',
            'teal',
            ['org_units.manage'],
            ['/admin/organization-chart'],
            20,
        ],
        [
            'appointments',
            'پست و انتصاب',
            'ثبت و مدیریت انتصاب اشخاص در جایگاه‌های سازمانی',
            '/admin/appointments',
            'id-badge',
            'cyan',
            ['appointments.manage'],
            ['/admin/appointments', '/admin/appointments/*'],
            30,
        ],
        [
            'organization-affiliations',
            'وابستگی‌ها و عضویت‌ها',
            'بررسی درخواست عضویت سازمانی، سمت و تخصیص دسترسی',
            '/admin/organization-affiliations',
            'users',
            'indigo',
            ['organizations.manage'],
            [
                '/admin/organization-affiliations',
                '/admin/organization-affiliations/*',
            ],
            35,
        ],
        [
            'org-units',
            'فهرست واحدها',
            'مشاهده واحدها و سلسله‌مراتب',
            '/admin/org-units',
            'building',
            'teal',
            ['org_units.view'],
            ['/admin/org-units', '/admin/org-units/*'],
            40,
        ],
        [
            'positions',
            'فهرست عناوین پست',
            'مشاهده عناوین پایه پست‌ها',
            '/admin/positions',
            'id-badge',
            'cyan',
            ['positions.view'],
            ['/admin/positions', '/admin/positions/*'],
            50,
        ],
    ],

    'system' => [
        [
            'theme',
            'پوسته پنل',
            'مدیریت ظاهر و هویت بصری پنل',
            '/admin/theme',
            'palette',
            'purple',
            ['admin.theme.manage'],
            ['/admin/theme'],
            10,
        ],
        [
            'settings',
            'تنظیمات',
            'تنظیمات عمومی سامانه',
            '/admin/settings',
            'sliders',
            'violet',
            ['admin.settings.manage'],
            ['/admin/settings'],
            20,
        ],
        [
            'core-feature-settings',
            'بخش‌های پنل',
            'ظاهر و نحوه نمایش بخش‌های داخلی پنل',
            '/admin/settings/core-features',
            'palette',
            'purple',
            ['admin.settings.manage'],
            ['/admin/settings/core-features'],
            25,
        ],
        [
            'access-control',
            'سطوح و نقش‌های دسترسی',
            'مرکز یکپارچه نقش‌ها، مجوزها، انتساب‌ها، حوزه‌ها و دسترسی اختصاصی کاربران',
            '/admin/access-control',
            'user-shield',
            'indigo',
            [
                'access.manage',
                'access.roles.manage',
                'access.users.search',
                'access.users.manage',
                'access.audit.view',
            ],
            ['/admin/access-control', '/admin/access-control/*'],
            30,
        ],
        [
            'public-page',
            'مدیریت صفحات',
            'مدیریت منو، اسلایدر، اطلاعیه‌ها، کارت‌ها و فوتر صفحه عمومی',
            '/admin/public-page',
            'file-lines',
            'fuchsia',
            ['admin.settings.manage'],
            ['/admin/public-page', '/admin/public-page/*'],
            35,
        ],
        [
            'help-texts',
            'مدیریت راهنما، اعلان و خطا',
            'مدیریت متن‌های راهنما، اعلان‌ها و پیام‌های خطای سامانه',
            '/admin/system/help-texts',
            'book-open',
            'teal',
            ['admin.ui_content.manage'],
            [
                '/admin/system/help-texts',
                '/admin/system/help-texts/*',
            ],
            40,
        ],
        [
            'system-scheduler',
            'مدیریت اجرای خودکار',
            'مدیریت Jobها، Scopeها، زمان‌بندی و تاریخچه اجرا',
            '/admin/system/scheduler',
            'settings',
            null,
            ['access.manage'],
            [
                '/admin/system/scheduler',
                '/admin/system/scheduler/*',
            ],
            45,
        ],
    ],
];

$db->beginTransaction();

try {

    /*
     * Communications is a first-class Core section and therefore
     * must have a dashboard card as well as its sidebar group.
     */
    $statement =
        $db->prepare("
            UPDATE admin_navigation_items
            SET dashboard_enabled = 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE shell_key = 'core'
              AND parent_id IS NULL
              AND item_key = 'communications'
              AND target_application = 'core'
        ");

    $statement->execute();


    /*
     * Repair stale application-module roots from the authoritative
     * application_modules flags.
     */
    $db->exec("
        UPDATE admin_navigation_items n
        INNER JOIN application_modules m
            ON m.module_key = n.item_key
        SET
            n.is_active =
                CASE
                    WHEN m.is_active = 1
                     AND m.sidebar_enabled = 1
                    THEN 1
                    ELSE 0
                END,
            n.updated_at = CURRENT_TIMESTAMP
        WHERE n.shell_key = 'core'
          AND n.parent_id IS NULL
          AND n.target_application = m.module_key
    ");


    $upsert =
        $db->prepare("
            INSERT INTO admin_navigation_items
            (
                parent_id,
                shell_key,
                item_key,
                item_type,
                placement_code,
                hide_when_badge_empty,
                title,
                description,
                route_path,
                target_application,
                icon_code,
                color_code,
                permission_mode,
                permission_codes_json,
                badge_source,
                active_paths_json,
                sort_order,
                is_active,
                dashboard_enabled,
                created_at,
                updated_at
            )
            VALUES
            (
                :parent_id,
                'core',
                :item_key,
                'link',
                'sidebar',
                0,
                :title,
                :description,
                :route_path,
                'core',
                :icon_code,
                :color_code,
                'any',
                :permission_codes_json,
                NULL,
                :active_paths_json,
                :sort_order,
                1,
                0,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                item_type = 'link',
                placement_code = 'sidebar',
                title = VALUES(title),
                description = VALUES(description),
                route_path = VALUES(route_path),
                target_application = 'core',
                icon_code = VALUES(icon_code),
                color_code = VALUES(color_code),
                permission_mode = 'any',
                permission_codes_json =
                    VALUES(permission_codes_json),
                active_paths_json =
                    VALUES(active_paths_json),
                sort_order = VALUES(sort_order),
                is_active = 1,
                dashboard_enabled = 0,
                updated_at = CURRENT_TIMESTAMP
        ");

    foreach ($children as $parentKey => $items) {

        $parentId = $rootId(
            $db,
            $parentKey
        );

        if ($parentId < 1) {
            throw new RuntimeException(
                'missing_core_parent:' . $parentKey
            );
        }

        foreach ($items as $item) {

            [
                $key,
                $title,
                $description,
                $route,
                $icon,
                $color,
                $permissions,
                $activePaths,
                $sort,
            ] = $item;

            $upsert->execute([
                'parent_id' =>
                    $parentId,

                'item_key' =>
                    $key,

                'title' =>
                    $title,

                'description' =>
                    $description,

                'route_path' =>
                    $route,

                'icon_code' =>
                    $icon,

                'color_code' =>
                    $color,

                'permission_codes_json' =>
                    $permissionsJson(
                        $permissions
                    ),

                'active_paths_json' =>
                    $pathsJson(
                        $activePaths
                    ),

                'sort_order' =>
                    $sort,
            ]);
        }
    }

    $db->commit();

} catch (Throwable $exception) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    throw $exception;
}


/*
 * Output proof.
 */
echo "CORE_NAVIGATION_PARITY_DATA=APPLIED\n";

$statement =
    $db->query("
        SELECT
            item_key,
            target_application,
            is_active,
            dashboard_enabled
        FROM admin_navigation_items
        WHERE shell_key = 'core'
          AND parent_id IS NULL
          AND item_key IN
          (
              'users',
              'organization',
              'system',
              'communications',
              'automation',
              'work',
              'ticketing'
          )
        ORDER BY sort_order, id
    ");

foreach (
    $statement->fetchAll(PDO::FETCH_ASSOC)
    as $row
) {
    echo json_encode(
        $row,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
}

foreach (
    ['users', 'organization', 'system', 'communications']
    as $parentKey
) {
    $parentId =
        $rootId(
            $db,
            $parentKey
        );

    $statement =
        $db->prepare("
            SELECT COUNT(*)
            FROM admin_navigation_items
            WHERE parent_id = ?
              AND shell_key = 'core'
              AND placement_code = 'sidebar'
              AND is_active = 1
        ");

    $statement->execute([
        $parentId,
    ]);

    echo
        "CHILDREN_"
        . strtoupper(
            str_replace('-', '_', $parentKey)
        )
        . "="
        . (int) $statement->fetchColumn()
        . PHP_EOL;
}
