<?php

namespace App\Support;

final class AdminTableSort
{
    public static function resolve(
        array $input,
        array $allowedColumns,
        string $defaultColumn,
        string $defaultDirection = 'asc'
    ): array {
        $source = is_array($_GET ?? null) ? array_merge($_GET, $input) : $input;
        $column = trim((string) ($source['sort'] ?? $defaultColumn));

        if (!array_key_exists($column, $allowedColumns)) {
            $column = $defaultColumn;
        }

        $direction = strtolower(trim((string) ($source['dir'] ?? $defaultDirection)));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = strtolower($defaultDirection) === 'desc' ? 'desc' : 'asc';
        }

        return [
            'column' => $column,
            'direction' => $direction,
            'expression' => (string) $allowedColumns[$column],
            'sql' => (string) $allowedColumns[$column] . ' ' . strtoupper($direction),
        ];
    }

    public static function url(
        string $basePath,
        array $query,
        string $column,
        string $currentColumn,
        string $currentDirection,
        string $fragment = ''
    ): string {
        $query = self::cleanQuery($query);
        $query['sort'] = $column;
        $query['dir'] = $currentColumn === $column && strtolower($currentDirection) === 'asc'
            ? 'desc'
            : 'asc';

        $encoded = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $url = $basePath . ($encoded === '' ? '' : '?' . $encoded);

        return $fragment === '' ? $url : $url . '#' . ltrim($fragment, '#');
    }

    public static function indicator(
        string $column,
        string $currentColumn,
        string $currentDirection
    ): string {
        if ($column !== $currentColumn) {
            return '↕';
        }

        return strtolower($currentDirection) === 'desc' ? '↓' : '↑';
    }

    public static function ariaSort(
        string $column,
        string $currentColumn,
        string $currentDirection
    ): string {
        if ($column !== $currentColumn) {
            return 'none';
        }

        return strtolower($currentDirection) === 'desc'
            ? 'descending'
            : 'ascending';
    }

    private static function cleanQuery(array $query): array
    {
        unset($query['page']);

        foreach ($query as $key => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                unset($query[$key]);
                continue;
            }

            $query[$key] = (string) $value;
        }

        return $query;
    }
}
