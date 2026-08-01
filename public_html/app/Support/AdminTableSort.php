<?php

namespace App\Support;

final class AdminTableSort
{
    public static function resolve(
        mixed $sort,
        mixed $direction = null,
        mixed $allowed = null,
        mixed $defaultSort = null,
        string $defaultDirection = 'asc'
    ): array {
        if (
            is_array($sort)
            && is_array($direction)
            && is_string($allowed)
        ) {
            $query = $sort;
            $allowedMap = $direction;
            $resolvedDefaultSort = $allowed;
            $resolvedDefaultDirection =
                is_string($defaultSort)
                && $defaultSort !== ''
                    ? $defaultSort
                    : 'asc';

            $sortValue = $query['sort'] ?? '';
            $directionValue = $query['dir']
                ?? $query['direction']
                ?? '';
        } else {
            $query = [];
            $allowedMap = is_array($allowed)
                ? $allowed
                : [];
            $resolvedDefaultSort =
                is_string($defaultSort)
                    ? $defaultSort
                    : '';
            $resolvedDefaultDirection =
                $defaultDirection;

            $sortValue = $sort;
            $directionValue = $direction;
        }

        $sortValue = trim((string) $sortValue);
        $directionValue = strtolower(
            trim((string) $directionValue)
        );

        if (
            $resolvedDefaultSort === ''
            || !array_key_exists(
                $resolvedDefaultSort,
                $allowedMap
            )
        ) {
            $resolvedDefaultSort = (string) (
                array_key_first($allowedMap) ?? ''
            );
        }

        if (
            $sortValue === ''
            || !array_key_exists(
                $sortValue,
                $allowedMap
            )
        ) {
            $sortValue = $resolvedDefaultSort;
        }

        $resolvedDefaultDirection =
            strtolower(
                trim($resolvedDefaultDirection)
            ) === 'desc'
                ? 'desc'
                : 'asc';

        if (
            !in_array(
                $directionValue,
                ['asc', 'desc'],
                true
            )
        ) {
            $directionValue =
                $resolvedDefaultDirection;
        }

        $sql = $allowedMap[$sortValue]
            ?? $sortValue;

        return [
            'sort' => $sortValue,
            'dir' => $directionValue,

            // Compatibility aliases for the previous
            // Work UI contract.
            'column' => $sortValue,
            'direction' => $directionValue,
            'sql' => $sql,
        ];
    }

    public static function directionFor(
        string $column,
        string $activeSort,
        string $activeDirection,
        string $defaultDirection = 'asc'
    ): string {
        if ($column !== $activeSort) {
            return strtolower(
                $defaultDirection
            ) === 'desc'
                ? 'desc'
                : 'asc';
        }

        return strtolower(
            $activeDirection
        ) === 'asc'
            ? 'desc'
            : 'asc';
    }

    public static function url(
        string $basePath,
        mixed $columnOrQuery,
        mixed $activeSortOrColumn,
        mixed $activeDirectionOrSort,
        mixed $queryOrDirection = [],
        string $defaultDirectionOrAnchor = 'asc'
    ): string {
        $anchor = '';

        if (is_array($columnOrQuery)) {
            // Previous contract:
            // url(
            //   basePath,
            //   query,
            //   column,
            //   activeSort,
            //   activeDirection,
            //   anchor?
            // )
            $query = $columnOrQuery;
            $column = trim(
                (string) $activeSortOrColumn
            );
            $activeSort = trim(
                (string) $activeDirectionOrSort
            );
            $activeDirection = strtolower(
                trim((string) $queryOrDirection)
            );
            $defaultDirection = 'asc';

            if (
                func_num_args() >= 6
                && !in_array(
                    strtolower(
                        $defaultDirectionOrAnchor
                    ),
                    ['asc', 'desc'],
                    true
                )
            ) {
                $anchor = ltrim(
                    trim(
                        $defaultDirectionOrAnchor
                    ),
                    '#'
                );
            }
        } else {
            // Current contract:
            // url(
            //   basePath,
            //   column,
            //   activeSort,
            //   activeDirection,
            //   query,
            //   defaultDirection
            // )
            $column = trim(
                (string) $columnOrQuery
            );
            $activeSort = trim(
                (string) $activeSortOrColumn
            );
            $activeDirection = strtolower(
                trim(
                    (string) $activeDirectionOrSort
                )
            );
            $query = is_array($queryOrDirection)
                ? $queryOrDirection
                : [];
            $defaultDirection = strtolower(
                trim($defaultDirectionOrAnchor)
            ) === 'desc'
                ? 'desc'
                : 'asc';
        }

        unset($query['page']);

        $query['sort'] = $column;
        $query['dir'] = self::directionFor(
            $column,
            $activeSort,
            $activeDirection,
            $defaultDirection
        );

        $queryString = http_build_query(
            array_filter(
                $query,
                static fn ($value): bool =>
                    $value !== null
                    && $value !== ''
            ),
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $url = $basePath;

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        if ($anchor !== '') {
            $url .= '#' . rawurlencode($anchor);
        }

        return $url;
    }

    public static function indicator(
        string $column,
        string $activeSort,
        string $activeDirection
    ): string {
        if ($column !== $activeSort) {
            return '↕';
        }

        return strtolower(
            $activeDirection
        ) === 'asc'
            ? '↑'
            : '↓';
    }

    public static function ariaSort(
        string $column,
        string $activeSort,
        string $activeDirection
    ): string {
        if ($column !== $activeSort) {
            return 'none';
        }

        return strtolower(
            $activeDirection
        ) === 'desc'
            ? 'descending'
            : 'ascending';
    }
}
