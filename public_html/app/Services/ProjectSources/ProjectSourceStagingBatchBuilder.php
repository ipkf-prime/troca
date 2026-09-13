<?php

declare(strict_types=1);

namespace App\Services\ProjectSources;

use InvalidArgumentException;
use JsonException;

/**
 * PROJECT_SOURCE_STAGING_BATCH_BUILDER_V1
 *
 * Converts an already-normalized A8 catalog page into a deterministic
 * staging envelope. It performs no database write and no materialization
 * into canonical dimension values.
 */
final class ProjectSourceStagingBatchBuilder
{
    public function build(
        array $sourceConfig,
        array $page
    ): array {
        $items =
            $page['items']
            ?? null;

        if (!is_array($items)) {
            throw new InvalidArgumentException(
                'Normalized catalog items are required.'
            );
        }

        if (count($items) > 1000) {
            throw new InvalidArgumentException(
                'Staging page is too large.'
            );
        }

        $rows = [];
        $rowNumber = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException(
                    'Staging item must be an array.'
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
                    'Staging item identity is incomplete.'
                );
            }

            $attributes =
                $item['attributes']
                ?? [];

            if (!is_array($attributes)) {
                throw new InvalidArgumentException(
                    'Staging attributes must be an array.'
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

            $rowNumber++;

            $payload = [
                'source_reference' =>
                    $reference,

                'parent_source_reference' =>
                    $parent,

                'title' =>
                    $title,

                'source_status' =>
                    $status,

                'attributes' =>
                    $attributes,
            ];

            $rows[] = [
                'row_number' =>
                    $rowNumber,

                'source_reference' =>
                    $reference,

                'parent_source_reference' =>
                    $parent,

                'title' =>
                    $title,

                'source_status' =>
                    $status,

                'attributes_json' =>
                    $this->json(
                        $attributes
                    ),

                'payload_hash' =>
                    hash(
                        'sha256',
                        $this->json(
                            $this->canonicalize(
                                $payload
                            )
                        )
                    ),

                'validation_status' =>
                    'pending',

                'validation_errors_json' =>
                    null,
            ];
        }

        return [
            'source_code' =>
                (string) (
                    $sourceConfig['code']
                    ?? ''
                ),

            'driver_code' =>
                (string) (
                    $sourceConfig[
                        'driver_code'
                    ]
                    ?? ''
                ),

            'catalog_code' =>
                (string) (
                    $sourceConfig[
                        'catalog_code'
                    ]
                    ?? ''
                ),

            'dimension_code' =>
                (string) (
                    $sourceConfig[
                        'dimension_code'
                    ]
                    ?? ''
                ),

            'snapshot_token' =>
                $page[
                    'snapshot_token'
                ]
                ?? null,

            'next_cursor' =>
                $page[
                    'next_cursor'
                ]
                ?? null,

            'item_count' =>
                count($rows),

            'rows' =>
                $rows,
        ];
    }

    private function canonicalize(
        mixed $value
    ): mixed {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return
                array_map(
                    fn (
                        mixed $item
                    ): mixed =>
                        $this->canonicalize(
                            $item
                        ),
                    $value
                );
        }

        ksort(
            $value,
            SORT_STRING
        );

        foreach ($value as $key => $item) {
            $value[$key] =
                $this->canonicalize(
                    $item
                );
        }

        return $value;
    }

    private function json(
        mixed $value
    ): string {
        try {
            return
                json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Unable to encode staging payload.',
                0,
                $exception
            );
        }
    }
}
