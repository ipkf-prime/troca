<?php

namespace App\Services;

use App\Repositories\AdminPositionRepository;
use Throwable;

class AdminPositionService extends BaseService
{
    private const PER_PAGE = 20;
    private const MAX_QUERY_LENGTH = 80;
    private const MAX_PAGE = 10000;
    private const DESCRIPTION_LENGTH = 120;

    public function __construct(protected ?AdminPositionRepository $positions = null)
    {
        $this->positions ??= new AdminPositionRepository();
    }

    public function index(array $params): array
    {
        $query = $this->normalizeQuery((string) ($params['q'] ?? ''));
        $page = $this->normalizePage($params['page'] ?? 1);

        try {
            $result = $this->positions->paginate($query, $page, self::PER_PAGE);
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
            'items' => array_map(fn (array $row): array => $this->positionViewModel($row), $result['items'] ?? []),
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

    private function positionViewModel(array $row): array
    {
        $description = $this->value($row['description'] ?? null);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => $this->value($row['title'] ?? null),
            'code' => $this->value($row['code'] ?? null),
            'description' => $this->truncate($description),
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
            'inactive' => ['code' => 'inactive', 'label' => 'غیرفعال'],
            default => ['code' => 'unknown', 'label' => $this->value($status)],
        };
    }

    private function truncate(string $value): string
    {
        if ($value === '—') {
            return $value;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') <= self::DESCRIPTION_LENGTH) {
                return $value;
            }

            return rtrim(mb_substr($value, 0, self::DESCRIPTION_LENGTH, 'UTF-8')) . '...';
        }

        if (strlen($value) <= self::DESCRIPTION_LENGTH) {
            return $value;
        }

        return rtrim(substr($value, 0, self::DESCRIPTION_LENGTH)) . '...';
    }

    private function formatDate(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return $this->value(null);
        }

        return $value;
    }

    private function value(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '—' : $value;
    }
}
