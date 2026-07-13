<?php

namespace App\Services;

use App\Repositories\AdminUserRepository;
use App\Support\AdminFormat;
use Throwable;

class AdminUserService extends BaseService
{
    private const PER_PAGE = 20;
    private const MAX_QUERY_LENGTH = 80;
    private const MAX_PAGE = 10000;

    public function __construct(protected ?AdminUserRepository $users = null)
    {
        $this->users ??= new AdminUserRepository();
    }

    public function index(array $params): array
    {
        $query = $this->normalizeQuery((string) ($params['q'] ?? ''));
        $page = $this->normalizePage($params['page'] ?? 1);

        try {
            $result = $this->users->paginate($query, $page, self::PER_PAGE);
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
            'items' => array_map(fn (array $row): array => $this->userViewModel($row), $result['items'] ?? []),
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

    private function userViewModel(array $row): array
    {
        $roles = $this->splitRoleTitles((string) ($row['active_role_titles'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $this->value($row['full_name'] ?? null),
            'username' => $this->value($row['username'] ?? null),
            'mobile' => $this->value($row['mobile'] ?? null),
            'email' => $this->value($row['email'] ?? null),
            'status' => $this->status((string) ($row['status'] ?? '')),
            'role_count' => (int) ($row['active_role_count'] ?? 0),
            'roles' => $roles,
            'role_summary' => $roles !== [] ? implode('، ', $roles) : $this->value(null),
            'primary_org_unit' => $this->value($row['primary_org_unit_title'] ?? null),
            'created_at' => $this->formatDate($row['created_at'] ?? null),
        ];
    }

    private function splitRoleTitles(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('، ', $value))));
    }

    private function status(string $status): array
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'active' => ['code' => 'active', 'label' => 'فعال'],
            'inactive', 'disabled' => ['code' => 'inactive', 'label' => 'غیرفعال'],
            'blocked', 'locked' => ['code' => 'blocked', 'label' => 'مسدود'],
            'pending' => ['code' => 'pending', 'label' => 'در انتظار'],
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
