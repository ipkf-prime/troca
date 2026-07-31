<?php

namespace App\Support;

final class AdminTableSort
{
    public static function resolve(
        mixed $sort,
        mixed $direction,
        array $allowed,
        string $defaultSort,
        string $defaultDirection = 'asc'
    ): array {
        $sort = trim((string) $sort);
        $direction = strtolower(trim((string) $direction));

        if (!array_key_exists($sort, $allowed)) {
            $sort = $defaultSort;
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = strtolower($defaultDirection) === 'desc'
                ? 'desc'
                : 'asc';
        }

        return [
            'sort' => $sort,
            'dir' => $direction,
        ];
    }

    public static function directionFor(
        string $column,
        string $activeSort,
        string $activeDirection,
        string $defaultDirection = 'asc'
    ): string {
        if ($column !== $activeSort) {
            return strtolower($defaultDirection) === 'desc'
                ? 'desc'
                : 'asc';
        }

        return strtolower($activeDirection) === 'asc'
            ? 'desc'
            : 'asc';
    }

    public static function url(
        string $basePath,
        string $column,
        string $activeSort,
        string $activeDirection,
        array $query = [],
        string $defaultDirection = 'asc'
    ): string {
        unset($query['page']);

        $query['sort'] = $column;
        $query['dir'] = self::directionFor(
            $column,
            $activeSort,
            $activeDirection,
            $defaultDirection
        );

        return $basePath . '?' . http_build_query(
            array_filter(
                $query,
                static fn ($value): bool =>
                    $value !== null && $value !== ''
            )
        );
    }

    public static function indicator(
        string $column,
        string $activeSort,
        string $activeDirection
    ): string {
        if ($column !== $activeSort) {
            return '↕';
        }

        return strtolower($activeDirection) === 'asc'
            ? '↑'
            : '↓';
    }
}
