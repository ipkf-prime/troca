<?php

namespace App\Services;

use IPKF\Database\Database;
use IPKF\Support\ModuleRuntimeConfig;

class DynamicAdminNavigationService extends BaseService
{
    public function navigation(): array
    {
        $runtime =
            new ModuleRuntimeConfig();

        $items = [];

        foreach (
            $runtime->allActive()
            as $module
        ) {
            if (
                (int) (
                    $module['sidebar_enabled']
                    ?? 1
                ) !== 1
            ) {
                continue;
            }

            $moduleKey = trim(
                (string) (
                    $module['module_key']
                    ?? ''
                )
            );

            $route = trim(
                (string) (
                    $module['route_path']
                    ?? ''
                )
            );

            if (
                $moduleKey === ''
                || $route === ''
            ) {
                continue;
            }

            if (!str_starts_with($route, '/')) {
                $route = '/' . $route;
            }

            $permission = trim(
                (string) (
                    $module['permission_key']
                    ?? ''
                )
            );

            $items[] = [
                'key' =>
                    'module-' . $moduleKey,

                'module_key' =>
                    $moduleKey,

                'title' =>
                    (string) (
                        $module['display_name']
                        ?? $moduleKey
                    ),

                'url' =>
                    '/auth/module-sso/start?return_path='
                    . rawurlencode($route),

                'route_path' =>
                    $route,

                'icon' =>
                    (string) (
                        $module['icon_code']
                        ?? 'apps'
                    ),

                'permission' =>
                    $permission !== ''
                        ? $permission
                        : null,

                'sort_order' =>
                    (int) (
                        $module['sort_order']
                        ?? 0
                    ),

                'active_paths' => [
                    $route,
                    rtrim($route, '/') . '/*',
                ],

                'target_application' =>
                    $moduleKey,
            ];
        }

        usort(
            $items,
            static fn (
                array $a,
                array $b
            ): int =>
                (int) (
                    $a['sort_order']
                    ?? 0
                )
                <=>
                (int) (
                    $b['sort_order']
                    ?? 0
                )
        );

        return $items;
    }


    public function sync(): void
    {
        if (
            !Database::tableExists(
                'admin_navigation_items'
            )
            || !Database::tableExists(
                'application_modules'
            )
        ) {
            return;
        }

        $db = Database::connect();

        /*
         * This synchronizer owns ONLY the root navigation row
         * whose item_key equals application_modules.module_key.
         *
         * Child navigation belonging to modules
         * (work-projects, automation-correspondences, ...)
         * must never be modified here.
         */
        $db->exec("
            UPDATE admin_navigation_items
            SET is_active = 0
            WHERE item_key IN
            (
                SELECT module_key
                FROM application_modules
            )
        ");

        $runtime =
            new ModuleRuntimeConfig();

        foreach (
            $runtime->allActive()
            as $module
        ) {
            if (
                (int) (
                    $module['sidebar_enabled']
                    ?? 1
                ) !== 1
            ) {
                continue;
            }

            $moduleKey = trim(
                (string) (
                    $module['module_key']
                    ?? ''
                )
            );

            $route = trim(
                (string) (
                    $module['route_path']
                    ?? ''
                )
            );

            if (
                $moduleKey === ''
                || $route === ''
            ) {
                continue;
            }

            $statement = $db->prepare("
                INSERT INTO admin_navigation_items
                (
                    item_key,
                    title,
                    description,
                    route_path,
                    target_application,
                    icon_code,
                    is_active
                )
                VALUES
                (
                    :key,
                    :title,
                    :description,
                    :route,
                    :application,
                    :icon,
                    1
                )
                ON DUPLICATE KEY UPDATE
                    title =
                        VALUES(title),

                    description =
                        VALUES(description),

                    route_path =
                        VALUES(route_path),

                    target_application =
                        VALUES(target_application),

                    icon_code =
                        VALUES(icon_code),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

            $statement->execute([
                'key' =>
                    $moduleKey,

                'title' =>
                    (string) (
                        $module['display_name']
                        ?? $moduleKey
                    ),

                'description' =>
                    (string) (
                        $module[
                            'dashboard_description'
                        ]
                        ?? ''
                    ),

                'route' =>
                    $route,

                'application' =>
                    $moduleKey,

                'icon' =>
                    (string) (
                        $module['icon_code']
                        ?? 'apps'
                    ),
            ]);
        }
    }
}
