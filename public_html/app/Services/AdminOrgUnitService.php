<?php

namespace App\Services;

use App\Repositories\AdminOrgUnitRepository;
use App\Support\AdminFormat;
use Throwable;

class AdminOrgUnitService extends BaseService
{
    private const PER_PAGE = 20;
    private const MAX_QUERY_LENGTH = 80;
    private const MAX_PAGE = 10000;
    private const MAX_DISPLAY_DEPTH = 12;
    private const MAX_INDENT_DEPTH = 6;

    public function __construct(protected ?AdminOrgUnitRepository $orgUnits = null)
    {
        $this->orgUnits ??= new AdminOrgUnitRepository();
    }

    public function index(array $params): array
    {
        $query = $this->normalizeQuery((string) ($params['q'] ?? ''));
        $page = $this->normalizePage($params['page'] ?? 1);

        try {
            $result = $this->orgUnits->paginate($query, $page, self::PER_PAGE);
        } catch (Throwable) {
            return [
                'ok' => false,
                'error' => 'database_unavailable',
                'q' => $query,
                'items' => [],
                'pagination' => $this->pagination(0, $page),
            ];
        }

        $total = (int) ($result['total'] ?? 0);

        return [
            'ok' => true,
            'error' => null,
            'q' => $query,
            'items' => array_map(fn (array $row): array => $this->orgUnitViewModel($row), $result['items'] ?? []),
            'pagination' => $this->pagination($total, $page),
        ];
    }

    private function normalizeQuery(string $query): string
    {
        $query = trim($query);
        $query = preg_replace('/\s+/u', ' ', $query) ?? $query;

        if (function_exists('mb_substr')) {
            return mb_substr($query, 0, self::MAX_QUERY_LENGTH, 'UTF-8');
        }

        return substr($query, 0, self::MAX_QUERY_LENGTH);
    }

    private function normalizePage(mixed $page): int
    {
        $page = filter_var($page, FILTER_VALIDATE_INT, [
            'options' => [
                'default' => 1,
                'min_range' => 1,
                'max_range' => self::MAX_PAGE,
            ],
        ]);

        return (int) $page;
    }

    private function pagination(int $total, int $page): array
    {
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $lastPage);

        return [
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'total' => $total,
            'last_page' => $lastPage,
            'has_previous' => $page > 1,
            'has_next' => $page < $lastPage,
            'previous_page' => max(1, $page - 1),
            'next_page' => min($lastPage, $page + 1),
        ];
    }

    private function orgUnitViewModel(array $row): array
    {
        $depth = max(0, min(self::MAX_DISPLAY_DEPTH, (int) ($row['depth'] ?? 0)));
        $indentDepth = min($depth, self::MAX_INDENT_DEPTH);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => $this->value($row['title'] ?? null),
            'code' => $this->value($row['code'] ?? null),
            'type' => $this->value($row['type'] ?? null),
            'parent_title' => $this->value($row['parent_title'] ?? null),
            'depth' => $depth,
            'indent' => $indentDepth * 18,
            'status' => $this->status((string) ($row['status'] ?? '')),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'created_at' => $this->formatDate($row['created_at'] ?? null),
        ];
    }

    private function status(string $status): array
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'active' => ['code' => 'active', 'label' => 'فعال'],
            'inactive', 'disabled' => ['code' => 'inactive', 'label' => 'غیرفعال'],
            default => ['code' => 'unknown', 'label' => $this->value($status)],
        };
    }

    private function formatDate(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return $this->value(null);
        }

        return AdminFormat::jalaliDateTime($value);
    }

    private function value(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '—' : $value;
    }
}
