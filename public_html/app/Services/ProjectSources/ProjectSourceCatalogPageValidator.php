<?php

declare(strict_types=1);

namespace App\Services\ProjectSources;

use InvalidArgumentException;

/**
 * PROJECT_SOURCE_CATALOG_PAGE_VALIDATOR_V1
 *
 * Connector output is treated as untrusted input.
 */
final class ProjectSourceCatalogPageValidator
{
    public function normalize(
        array $page
    ): array {
        $items =
            $page['items']
            ?? null;

        if (!is_array($items)) {
            throw new InvalidArgumentException(
                'Catalog items must be an array.'
            );
        }

        if (count($items) > 1000) {
            throw new InvalidArgumentException(
                'Catalog page is too large.'
            );
        }

        $normalized = [];
        $seen = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException(
                    'Catalog item must be an array.'
                );
            }

            $reference =
                trim(
                    (string) (
                        $item[
                            'source_reference'
                        ]
                        ?? ''
                    )
                );

            $title =
                trim(
                    (string) (
                        $item['title']
                        ?? ''
                    )
                );

            if (
                $reference === ''
                || $title === ''
            ) {
                throw new InvalidArgumentException(
                    'Catalog item identity is incomplete.'
                );
            }

            if (
                isset(
                    $seen[$reference]
                )
            ) {
                throw new InvalidArgumentException(
                    'Duplicate source reference.'
                );
            }

            $parentRaw =
                $item[
                    'parent_source_reference'
                ]
                ?? null;

            $parent =
                $parentRaw === null
                    ? null
                    : trim(
                        (string) $parentRaw
                    );

            if ($parent === '') {
                $parent = null;
            }

            if (
                $parent !== null
                && $parent === $reference
            ) {
                throw new InvalidArgumentException(
                    'Catalog item cannot parent itself.'
                );
            }

            $attributes =
                $item['attributes']
                ?? [];

            if (!is_array($attributes)) {
                throw new InvalidArgumentException(
                    'Catalog attributes must be an array.'
                );
            }

            $status =
                trim(
                    (string) (
                        $item['status']
                        ?? 'active'
                    )
                );

            if ($status === '') {
                $status = 'active';
            }

            $seen[$reference] =
                true;

            $normalized[] = [
                'source_reference' =>
                    $reference,

                'title' =>
                    $title,

                'parent_source_reference' =>
                    $parent,

                'status' =>
                    $status,

                'attributes' =>
                    $attributes,
            ];
        }

        return [
            'items' =>
                $normalized,

            'next_cursor' =>
                $this->nullableScalarString(
                    $page[
                        'next_cursor'
                    ]
                    ?? null
                ),

            'snapshot_token' =>
                $this->nullableScalarString(
                    $page[
                        'snapshot_token'
                    ]
                    ?? null
                ),
        ];
    }

    private function nullableScalarString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException(
                'Cursor/token must be scalar.'
            );
        }

        $value =
            trim(
                (string) $value
            );

        return
            $value !== ''
                ? $value
                : null;
    }
}
