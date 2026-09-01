<?php

namespace App\Services\Infrastructure;

use IPKF\Support\Env;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared private storage resolver.
 *
 * New records use relative module-scoped storage keys.
 *
 * Physical storage root is resolved at runtime.
 *
 * Existing absolute Automation storage keys remain readable.
 */
final class SharedPrivateStorageService
{
    public function __construct(
        private ?SharedFileInfrastructureSettingsService $settings = null
    ) {
        $this->settings ??=
            new SharedFileInfrastructureSettingsService();
    }


    public function sharedRoot(): ?string
    {
        return
            $this->settings
                ->effectiveStorageRoot();
    }


    public function rootFor(
        string $module
    ): string {
        $module =
            $this->module(
                $module
            );

        $shared =
            $this->sharedRoot();

        if (
            is_string(
                $shared
            )
            && trim(
                $shared
            )
            !== ''
        ) {
            return
                rtrim(
                    $shared,
                    '/\\'
                );
        }

        $legacy =
            $this->legacyRoots(
                $module
            );

        if ($legacy === []) {
            throw new RuntimeException(
                'Private storage root is unavailable.'
            );
        }

        return
            $legacy[0];
    }


    public function pathForNew(
        string $module,
        string $storageKey
    ): string {
        $module =
            $this->module(
                $module
            );

        $storageKey =
            $this->safeRelativeKey(
                $storageKey
            );

        if (
            !str_starts_with(
                $storageKey,
                $module
                . '/'
            )
        ) {
            throw new InvalidArgumentException(
                'Storage key must be module-scoped.'
            );
        }

        return
            $this->rootFor(
                $module
            )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $storageKey
            );
    }


    public function prepareDirectoryForNew(
        string $module,
        string $storageKey
    ): string {
        $path =
            $this->pathForNew(
                $module,
                $storageKey
            );

        $directory =
            dirname(
                $path
            );

        if (
            !is_dir(
                $directory
            )
            && !mkdir(
                $directory,
                0750,
                true
            )
            && !is_dir(
                $directory
            )
        ) {
            throw new RuntimeException(
                'Private storage directory is unavailable.'
            );
        }

        return $path;
    }


    public function resolveExisting(
        string $module,
        string $storageKey
    ): ?string {
        $module =
            $this->module(
                $module
            );

        $storageKey =
            trim(
                str_replace(
                    '\\',
                    '/',
                    $storageKey
                )
            );

        if (
            $storageKey === ''
            || str_contains(
                $storageKey,
                "\0"
            )
        ) {
            return null;
        }

        /*
         * Compatibility with old Automation absolute paths.
         */
        if (
            str_starts_with(
                $storageKey,
                '/'
            )
        ) {
            $real =
                realpath(
                    $storageKey
                );

            if (
                $real === false
                || !is_file(
                    $real
                )
            ) {
                return null;
            }

            foreach (
                $this->allowedRoots(
                    $module
                )
                as $root
            ) {
                if (
                    $this->insideRoot(
                        $real,
                        $root
                    )
                ) {
                    return $real;
                }
            }

            return null;
        }

        try {
            $relative =
                $this->safeRelativeKey(
                    $storageKey
                );

        } catch (InvalidArgumentException) {
            return null;
        }

        foreach (
            $this->allowedRoots(
                $module
            )
            as $root
        ) {
            $candidate =
                $root
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $relative
                );

            $real =
                realpath(
                    $candidate
                );

            if (
                $real !== false
                && is_file(
                    $real
                )
                && $this->insideRoot(
                    $real,
                    $root
                )
            ) {
                return $real;
            }
        }

        return null;
    }


    public function safeRelativeKey(
        string $storageKey
    ): string {
        $storageKey =
            trim(
                str_replace(
                    '\\',
                    '/',
                    $storageKey
                )
            );

        if (
            $storageKey === ''
            || str_contains(
                $storageKey,
                "\0"
            )
            || str_starts_with(
                $storageKey,
                '/'
            )
            || preg_match(
                '#(^|/)\.\.(/|$)#',
                $storageKey
            )
        ) {
            throw new InvalidArgumentException(
                'Unsafe private storage key.'
            );
        }

        return
            ltrim(
                preg_replace(
                    '#/+#',
                    '/',
                    $storageKey
                )
                ?? $storageKey,
                '/'
            );
    }


    private function allowedRoots(
        string $module
    ): array {
        $roots = [];

        $shared =
            $this->sharedRoot();

        if (
            is_string(
                $shared
            )
            && trim(
                $shared
            )
            !== ''
        ) {
            $roots[] =
                rtrim(
                    $shared,
                    '/\\'
                );
        }

        foreach (
            $this->legacyRoots(
                $module
            )
            as $legacy
        ) {
            $roots[] =
                $legacy;
        }

        $resolved = [];

        foreach (
            array_values(
                array_unique(
                    $roots
                )
            )
            as $root
        ) {
            $real =
                realpath(
                    $root
                );

            if (
                is_string(
                    $real
                )
                && is_dir(
                    $real
                )
            ) {
                $resolved[] =
                    rtrim(
                        $real,
                        '/\\'
                    );
            }
        }

        return
            array_values(
                array_unique(
                    $resolved
                )
            );
    }


    private function legacyRoots(
        string $module
    ): array {
        $roots = [];

        switch ($module) {
            case 'ticketing':

                $roots[] =
                    BASE_PATH
                    . '/storage/uploads';

                break;


            case 'automation':

                $configured =
                    trim(
                        (string) Env::get(
                            'PRIVATE_FILE_STORAGE_PATH',
                            ''
                        )
                    );

                if ($configured !== '') {
                    $roots[] =
                        rtrim(
                            $configured,
                            '/\\'
                        );
                }

                $roots[] =
                    dirname(
                        BASE_PATH
                    )
                    . '/storage/private/automation';

                break;


            case 'work':

                $work =
                    trim(
                        (string) Env::get(
                            'WORK_PRIVATE_FILE_STORAGE_PATH',
                            ''
                        )
                    );

                if ($work !== '') {
                    $roots[] =
                        rtrim(
                            $work,
                            '/\\'
                        );
                }

                $legacyShared =
                    trim(
                        (string) Env::get(
                            'PRIVATE_FILE_STORAGE_PATH',
                            ''
                        )
                    );

                if ($legacyShared !== '') {
                    $roots[] =
                        rtrim(
                            $legacyShared,
                            '/\\'
                        );
                }

                $roots[] =
                    dirname(
                        BASE_PATH
                    )
                    . '/storage/private/work';

                break;


            default:

                $roots[] =
                    dirname(
                        BASE_PATH
                    )
                    . '/storage/private/'
                    . $module;

                break;
        }

        return
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn (
                                string $path
                            ): string =>
                                rtrim(
                                    trim(
                                        $path
                                    ),
                                    '/\\'
                                ),
                            $roots
                        ),
                        static fn (
                            string $path
                        ): bool =>
                            $path !== ''
                    )
                )
            );
    }


    private function insideRoot(
        string $path,
        string $root
    ): bool {
        $root =
            rtrim(
                $root,
                '/\\'
            );

        return
            $path === $root
            || str_starts_with(
                $path,
                $root
                . DIRECTORY_SEPARATOR
            );
    }


    private function module(
        string $module
    ): string {
        $module =
            strtolower(
                trim(
                    $module
                )
            );

        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,49}$/',
                $module
            )
            !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid storage module.'
            );
        }

        return $module;
    }
}
