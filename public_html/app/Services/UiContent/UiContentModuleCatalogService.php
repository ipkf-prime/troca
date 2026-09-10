<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use App\Services\ApplicationModuleRegistryService;
use Throwable;


/**
 * Administrative module discovery.
 *
 * Runtime content resolution never requires this list.
 *
 * Discovery union:
 *
 *   core
 *   + installed source catalog
 *   + application_modules runtime rows, when available
 *
 * Therefore Core/Base remains manageable even before the
 * application_modules table is repaired/applied.
 */
final class UiContentModuleCatalogService
{
    public function modules(): array
    {
        $result = [
            'core' => [
                'module_key' =>
                    'core',

                'display_name' =>
                    'هسته / Base',

                'is_active' =>
                    1,

                'sort_order' =>
                    -1000,

                'source' =>
                    'core',
            ],
        ];


        $service =
            new ApplicationModuleRegistryService();


        try {

            foreach (
                $service->catalog()
                as $key => $module
            ) {

                $key =
                    strtolower(
                        trim(
                            (string) $key
                        )
                    );

                if (
                    preg_match(
                        '/^[a-z][a-z0-9_-]{1,99}$/D',
                        $key
                    )
                    !== 1
                ) {
                    continue;
                }

                $result[$key] = [
                    'module_key' =>
                        $key,

                    'display_name' =>
                        (string) (
                            $module['name']
                            ?? $key
                        ),

                    'is_active' =>
                        null,

                    'sort_order' =>
                        100,

                    'source' =>
                        'source_catalog',
                ];
            }

        } catch (Throwable) {
            /*
             * Core remains available even if catalog
             * construction encounters an environment issue.
             */
        }


        try {

            $index =
                $service->index();

            $items =
                is_array(
                    $index['items']
                    ?? null
                )
                    ? $index['items']
                    : [];

            foreach ($items as $module) {

                if (!is_array($module)) {
                    continue;
                }

                $key =
                    strtolower(
                        trim(
                            (string) (
                                $module[
                                    'module_key'
                                ]
                                ?? ''
                            )
                        )
                    );

                if (
                    preg_match(
                        '/^[a-z][a-z0-9_-]{1,99}$/D',
                        $key
                    )
                    !== 1
                ) {
                    continue;
                }

                $result[$key] = [
                    'module_key' =>
                        $key,

                    'display_name' =>
                        (string) (
                            $module[
                                'display_name'
                            ]
                            ?? $key
                        ),

                    'is_active' =>
                        (int) (
                            $module[
                                'is_active'
                            ]
                            ?? 0
                        ),

                    'sort_order' =>
                        (int) (
                            $module[
                                'sort_order'
                            ]
                            ?? 100
                        ),

                    'source' =>
                        'runtime_registry',
                ];
            }

        } catch (Throwable) {
            /*
             * application_modules may legitimately not have
             * been applied yet. This is not fatal.
             */
        }


        $modules =
            array_values(
                $result
            );

        usort(
            $modules,
            static function (
                array $left,
                array $right
            ): int {

                $sort =
                    ((int) $left[
                        'sort_order'
                    ])
                    <=>
                    ((int) $right[
                        'sort_order'
                    ]);

                if ($sort !== 0) {
                    return $sort;
                }

                return
                    strcmp(
                        (string) $left[
                            'display_name'
                        ],
                        (string) $right[
                            'display_name'
                        ]
                    );
            }
        );

        return $modules;
    }
}
