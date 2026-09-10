<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use App\Services\ApplicationModuleRegistryService;
use Throwable;


/**
 * Resolution order:
 *
 * 1. explicit caller module;
 * 2. module scope provider with explicit request context;
 * 3. longest active application_modules.route_path;
 * 4. core.
 */
final class UiContentRequestContextService
{
    private ?ApplicationModuleRegistryService $registry;

    private ?array $runtimeRowsOverride;

    private ?array $catalogOverride;


    public function __construct(
        ?ApplicationModuleRegistryService $registry = null,
        ?array $runtimeRowsOverride = null,
        ?array $catalogOverride = null
    ) {
        $this->registry =
            $registry;

        $this->runtimeRowsOverride =
            $runtimeRowsOverride;

        $this->catalogOverride =
            $catalogOverride;
    }


    public function resolve(
        string $uri,
        string $host,
        ?string $explicitModuleKey = null,
        array $explicitScopePath = [],
        string $locale = 'fa'
    ): array {

        $uri =
            $this->normalizeUri(
                $uri
            );

        $runtimeRows =
            $this->runtimeRows();

        $catalog =
            $this->catalog();


        $moduleKey =
            $explicitModuleKey !== null
                ? strtolower(
                    trim(
                        $explicitModuleKey
                    )
                )
                : null;


        $providerEnvelope = null;


        if (
            $moduleKey === null
            ||
            $moduleKey === ''
        ) {

            foreach ($runtimeRows as $row) {

                if (
                    !is_array($row)
                    ||
                    (int) (
                        $row['is_active']
                        ?? 0
                    ) !== 1
                ) {
                    continue;
                }


                $candidateKey =
                    strtolower(
                        trim(
                            (string) (
                                $row['module_key']
                                ?? ''
                            )
                        )
                    );


                if ($candidateKey === '') {
                    continue;
                }


                $provider =
                    $this->providerFor(
                        $candidateKey
                    );


                if ($provider === null) {
                    continue;
                }


                try {

                    $candidate =
                        $provider
                            ->resolveRequestScope(
                                $host,
                                $uri
                            );

                } catch (Throwable) {

                    continue;
                }


                $candidatePath =
                    is_array(
                        $candidate[
                            'scope_path'
                        ]
                        ?? null
                    )
                        ? $candidate[
                            'scope_path'
                        ]
                        : [];


                if ($candidatePath === []) {
                    continue;
                }


                $moduleKey =
                    $candidateKey;

                $providerEnvelope =
                    $candidate;

                break;
            }
        }


        if (
            $moduleKey === null
            ||
            $moduleKey === ''
        ) {
            $moduleKey =
                $this->moduleFromUri(
                    $uri,
                    $runtimeRows
                );
        }


        if (
            $moduleKey === null
            ||
            $moduleKey === ''
        ) {
            $moduleKey = 'core';
        }


        $provider =
            $this->providerFor(
                $moduleKey
            );


        $scopePath = [];
        $scopeMetadata = [];


        if ($explicitScopePath !== []) {

            if ($provider !== null) {

                $scopePath =
                    $provider
                        ->normalizeScopePath(
                            $explicitScopePath
                        );


                /*
                 * Caller authorization precedes this point.
                 * Description is presentation-only.
                 */
                try {

                    $scopeMetadata =
                        $provider
                            ->describeScopePath(
                                $scopePath
                            );

                } catch (Throwable) {

                    $scopeMetadata = [];
                }

            } else {

                $scopePath =
                    (
                        new UiContentContext(
                            $moduleKey,
                            $locale,
                            $explicitScopePath
                        )
                    )->scopePath();
            }

        } elseif ($provider !== null) {

            try {

                $resolved =
                    $providerEnvelope
                    ?? $provider
                        ->resolveRequestScope(
                            $host,
                            $uri
                        );


                $resolvedPath =
                    is_array(
                        $resolved[
                            'scope_path'
                        ]
                        ?? null
                    )
                        ? $resolved[
                            'scope_path'
                        ]
                        : [];


                $scopePath =
                    $provider
                        ->normalizeScopePath(
                            $resolvedPath
                        );


                $scopeMetadata =
                    is_array(
                        $resolved[
                            'metadata'
                        ]
                        ?? null
                    )
                        ? $resolved[
                            'metadata'
                        ]
                        : [];

            } catch (Throwable) {

                $scopePath = [];
                $scopeMetadata = [];
            }
        }


        $context =
            new UiContentContext(
                $moduleKey,
                $locale,
                $scopePath
            );


        return [
            'context' =>
                $context,

            'module' =>
                $this->moduleMetadata(
                    $moduleKey,
                    $runtimeRows,
                    $catalog
                ),

            'scope_metadata' =>
                $scopeMetadata,
        ];
    }


    private function moduleFromUri(
        string $uri,
        array $runtimeRows
    ): ?string {

        $candidates = [];


        foreach ($runtimeRows as $row) {

            if (
                !is_array($row)
                ||
                (int) (
                    $row['is_active']
                    ?? 0
                ) !== 1
            ) {
                continue;
            }


            $moduleKey =
                strtolower(
                    trim(
                        (string) (
                            $row['module_key']
                            ?? ''
                        )
                    )
                );


            $route =
                $this->normalizeRoutePath(
                    (string) (
                        $row['route_path']
                        ?? ''
                    )
                );


            if (
                $moduleKey === ''
                ||
                $route === null
                ||
                $route === '/'
            ) {
                continue;
            }


            if (
                $uri !== $route
                &&
                !str_starts_with(
                    $uri,
                    $route . '/'
                )
            ) {
                continue;
            }


            $candidates[] = [
                'module_key' =>
                    $moduleKey,

                'route_path' =>
                    $route,
            ];
        }


        if ($candidates === []) {
            return null;
        }


        usort(
            $candidates,
            static fn (
                array $left,
                array $right
            ): int =>
                strlen(
                    (string) $right[
                        'route_path'
                    ]
                )
                <=>
                strlen(
                    (string) $left[
                        'route_path'
                    ]
                )
        );


        return
            (string) $candidates[0][
                'module_key'
            ];
    }


    private function moduleMetadata(
        string $moduleKey,
        array $runtimeRows,
        array $catalog
    ): array {

        if ($moduleKey === 'core') {

            return [
                'module_key' =>
                    'core',

                'display_name' =>
                    'سامانه',

                'route_path' =>
                    '/',

                'icon_code' =>
                    'apps',

                'color_code' =>
                    null,

                'source' =>
                    'core',
            ];
        }


        foreach ($runtimeRows as $row) {

            if (
                !is_array($row)
                ||
                strtolower(
                    trim(
                        (string) (
                            $row['module_key']
                            ?? ''
                        )
                    )
                )
                !== $moduleKey
            ) {
                continue;
            }


            return [
                'module_key' =>
                    $moduleKey,

                'display_name' =>
                    trim(
                        (string) (
                            $row['display_name']
                            ?? $moduleKey
                        )
                    ),

                'route_path' =>
                    $this->normalizeRoutePath(
                        (string) (
                            $row['route_path']
                            ?? ''
                        )
                    ),

                'icon_code' =>
                    trim(
                        (string) (
                            $row['icon_code']
                            ?? 'apps'
                        )
                    ),

                'color_code' =>
                    $this->normalizeColor(
                        (string) (
                            $row['color_code']
                            ?? ''
                        )
                    ),

                'source' =>
                    'runtime_registry',
            ];
        }


        $catalogRow =
            is_array(
                $catalog[$moduleKey]
                ?? null
            )
                ? $catalog[$moduleKey]
                : [];


        return [
            'module_key' =>
                $moduleKey,

            'display_name' =>
                trim(
                    (string) (
                        $catalogRow['name']
                        ?? $moduleKey
                    )
                ),

            'route_path' =>
                null,

            'icon_code' =>
                'apps',

            'color_code' =>
                null,

            'source' =>
                'source_catalog',
        ];
    }


    private function providerFor(
        string $moduleKey
    ): ?UiContentScopeProviderInterface {

        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,99}$/D',
                $moduleKey
            )
            !== 1
        ) {
            return null;
        }


        $parts =
            preg_split(
                '/[-_]+/',
                $moduleKey
            )
            ?: [];


        $studly = '';


        foreach ($parts as $part) {

            if ($part === '') {
                continue;
            }

            $studly .=
                ucfirst(
                    strtolower(
                        $part
                    )
                );
        }


        if ($studly === '') {
            return null;
        }


        $class =
            __NAMESPACE__
            . '\\ScopeProviders\\'
            . $studly
            . 'UiContentScopeProvider';


        if (
            !class_exists($class)
            ||
            !is_subclass_of(
                $class,
                UiContentScopeProviderInterface::class
            )
        ) {
            return null;
        }


        try {

            $provider =
                new $class();

        } catch (Throwable) {

            return null;
        }


        return
            $provider instanceof
                UiContentScopeProviderInterface
                ? $provider
                : null;
    }


    private function runtimeRows(): array
    {
        if (
            $this->runtimeRowsOverride
            !== null
        ) {
            return
                $this->runtimeRowsOverride;
        }


        try {

            $index =
                $this->registry()
                    ->index();

            return
                is_array(
                    $index['items']
                    ?? null
                )
                    ? $index['items']
                    : [];

        } catch (Throwable) {

            return [];
        }
    }


    private function catalog(): array
    {
        if (
            $this->catalogOverride
            !== null
        ) {
            return
                $this->catalogOverride;
        }


        try {

            $catalog =
                $this->registry()
                    ->catalog();

            return
                is_array($catalog)
                    ? $catalog
                    : [];

        } catch (Throwable) {

            return [];
        }
    }


    private function registry():
        ApplicationModuleRegistryService
    {
        $this->registry ??=
            new ApplicationModuleRegistryService();

        return $this->registry;
    }


    private function normalizeUri(
        string $uri
    ): string {

        $path =
            parse_url(
                $uri,
                PHP_URL_PATH
            );


        $path =
            is_string($path)
                ? $path
                : '/';


        $path =
            '/'
            . trim(
                $path,
                '/'
            );


        return
            $path === '//'
                ? '/'
                : $path;
    }


    private function normalizeRoutePath(
        string $route
    ): ?string {

        $route =
            trim(
                $route
            );


        if (
            $route === ''
            ||
            !str_starts_with(
                $route,
                '/'
            )
            ||
            str_starts_with(
                $route,
                '//'
            )
            ||
            str_contains(
                $route,
                '://'
            )
            ||
            str_contains(
                $route,
                '\\'
            )
            ||
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $route
            )
        ) {
            return null;
        }


        $route =
            '/'
            . trim(
                $route,
                '/'
            );


        return
            $route === '//'
                ? '/'
                : $route;
    }


    private function normalizeColor(
        string $color
    ): ?string {

        $color =
            trim(
                $color
            );


        return
            preg_match(
                '/^#[0-9a-fA-F]{6}$/D',
                $color
            )
            === 1
                ? strtolower(
                    $color
                )
                : null;
    }
}
