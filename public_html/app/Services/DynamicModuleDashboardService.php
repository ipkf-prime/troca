<?php

namespace App\Services;

use IPKF\Support\ModuleRuntimeConfig;

class DynamicModuleDashboardService extends BaseService
{
    public function cards(): array
    {
        $runtime = new ModuleRuntimeConfig();
        $cards = [];

        foreach ($runtime->allActive() as $module) {

            if (
                (int) (
                    $module['dashboard_enabled']
                    ?? 1
                ) !== 1
            ) {
                continue;
            }

            $baseUrl = rtrim(
                trim(
                    (string) (
                        $module['base_url']
                        ?? ''
                    )
                ),
                '/'
            );

            $route = trim(
                (string) (
                    $module['route_path']
                    ?? '/'
                )
            );

            if ($route === '') {
                $route = '/';
            }

            if (!str_starts_with($route, '/')) {
                $route = '/' . $route;
            }

            $cards[] = [
                'key' =>
                    (string) $module['module_key'],

                'title' =>
                    (string) $module['display_name'],

                'description' =>
                    (string) (
                        $module['dashboard_description']
                        ?? ''
                    ),

                'url' =>
                    $baseUrl . $route,

                'route' =>
                    $route,

                'permission' =>
                    (string) (
                        $module['permission_key']
                        ?? ''
                    ),

                'icon' =>
                    (string) (
                        $module['icon_code']
                        ?? 'apps'
                    ),

                'color' =>
                    $module['color_code']
                    ?? null,

                'sort' =>
                    (int) (
                        $module['sort_order']
                        ?? 0
                    ),
            ];
        }

        return $cards;
    }
}
