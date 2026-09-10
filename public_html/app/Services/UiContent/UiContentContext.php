<?php

declare(strict_types=1);

namespace App\Services\UiContent;

use InvalidArgumentException;


/**
 * Shared hierarchical content scope context.
 *
 * The shared engine deliberately has no knowledge of
 * Project, Portal, Workspace, Team, Province, etc.
 */
final class UiContentContext
{
    private string $moduleKey;
    private string $locale;

    /**
     * @var array<int,array{type:string,reference:string}>
     */
    private array $scopePath;


    public function __construct(
        string $moduleKey = 'core',
        string $locale = 'fa',
        array $scopePath = []
    ) {
        $this->moduleKey =
            $this->normalizeModuleKey(
                $moduleKey
            );

        $this->locale =
            $this->normalizeLocale(
                $locale
            );

        $this->scopePath =
            $this->normalizeScopePath(
                $scopePath
            );
    }


    public function moduleKey(): string
    {
        return $this->moduleKey;
    }


    public function locale(): string
    {
        return $this->locale;
    }


    public function scopePath(): array
    {
        return $this->scopePath;
    }


    /**
     * Broadest -> most specific.
     */
    public function cascadeScopeKeys(): array
    {
        $keys = [
            'global',
            'module:' . $this->moduleKey,
        ];

        $parts = [
            'scope',
            $this->moduleKey,
        ];

        foreach ($this->scopePath as $scope) {

            $parts[] =
                $scope['type'];

            $parts[] =
                $scope['reference'];

            $keys[] =
                implode(
                    ':',
                    $parts
                );
        }

        return $keys;
    }


    /**
     * Most specific -> broadest.
     */
    public function candidateScopeKeys(): array
    {
        return
            array_reverse(
                $this->cascadeScopeKeys()
            );
    }


    public function descriptors(): array
    {
        $result = [
            [
                'type' =>
                    'global',

                'reference' =>
                    null,

                'key' =>
                    'global',

                'depth' =>
                    0,
            ],

            [
                'type' =>
                    'module',

                'reference' =>
                    $this->moduleKey,

                'key' =>
                    'module:'
                    . $this->moduleKey,

                'depth' =>
                    1,
            ],
        ];

        $parts = [
            'scope',
            $this->moduleKey,
        ];

        $depth = 2;

        foreach ($this->scopePath as $scope) {

            $parts[] =
                $scope['type'];

            $parts[] =
                $scope['reference'];

            $result[] = [
                'type' =>
                    $scope['type'],

                'reference' =>
                    $scope['reference'],

                'key' =>
                    implode(
                        ':',
                        $parts
                    ),

                'depth' =>
                    $depth,
            ];

            $depth++;
        }

        return $result;
    }


    public function toArray(): array
    {
        return [
            'module_key' =>
                $this->moduleKey,

            'locale' =>
                $this->locale,

            'scope_path' =>
                $this->scopePath,

            'cascade_scope_keys' =>
                $this->cascadeScopeKeys(),

            'candidate_scope_keys' =>
                $this->candidateScopeKeys(),
        ];
    }


    private function normalizeModuleKey(
        string $moduleKey
    ): string {

        $moduleKey =
            strtolower(
                trim(
                    $moduleKey
                )
            );

        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,99}$/D',
                $moduleKey
            )
            !== 1
        ) {
            throw new InvalidArgumentException(
                'ui_content_module_key_invalid'
            );
        }

        return $moduleKey;
    }


    private function normalizeLocale(
        string $locale
    ): string {

        $locale =
            strtolower(
                trim(
                    $locale
                )
            );

        if (
            preg_match(
                '/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/D',
                $locale
            )
            !== 1
        ) {
            throw new InvalidArgumentException(
                'ui_content_locale_invalid'
            );
        }

        return $locale;
    }


    private function normalizeScopePath(
        array $scopePath
    ): array {

        $result = [];

        foreach ($scopePath as $scope) {

            if (!is_array($scope)) {
                throw new InvalidArgumentException(
                    'ui_content_scope_invalid'
                );
            }

            $type =
                strtolower(
                    trim(
                        (string) (
                            $scope['type']
                            ?? ''
                        )
                    )
                );

            $reference =
                trim(
                    (string) (
                        $scope['reference']
                        ?? ''
                    )
                );

            if (
                preg_match(
                    '/^[a-z][a-z0-9_-]{1,39}$/D',
                    $type
                )
                !== 1
            ) {
                throw new InvalidArgumentException(
                    'ui_content_scope_type_invalid'
                );
            }

            if (
                preg_match(
                    '/^[A-Za-z0-9][A-Za-z0-9_-]{1,189}$/D',
                    $reference
                )
                !== 1
            ) {
                throw new InvalidArgumentException(
                    'ui_content_scope_reference_invalid'
                );
            }

            $result[] = [
                'type' =>
                    $type,

                'reference' =>
                    $reference,
            ];
        }

        return $result;
    }
}
