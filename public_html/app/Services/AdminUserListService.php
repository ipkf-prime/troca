<?php

namespace App\Services;

use App\Repositories\AdminUserListRepository;
use App\Support\AdminFormat;
use App\Support\AdminLookup;
use App\Support\AdminTableSort;
use Throwable;

class AdminUserListService extends BaseService
{
    private const PER_PAGE = 20;

    private const MAX_QUERY_LENGTH = 80;

    private const MAX_PAGE = 10000;

    private const SORTS = [
        'name' => true,
        'username' => true,
        'mobile' => true,
        'email' => true,
        'status' => true,
        'role' => true,
        'org_unit' => true,
        'created_at' => true,
    ];

    public function __construct(
        private ?AdminUserListRepository $users = null
    ) {
        $this->users ??= new AdminUserListRepository();
    }

    public function index(array $params): array
    {
        $query = $this->normalizeQuery(
            (string) ($params['q'] ?? '')
        );
        $page = $this->normalizePage(
            $params['page'] ?? 1
        );
        $sort = AdminTableSort::resolve(
            $params['sort'] ?? '',
            $params['dir'] ?? '',
            self::SORTS,
            'created_at',
            'desc'
        );

        try {
            $result = $this->users->paginate(
                $query,
                $page,
                self::PER_PAGE,
                $sort['sort'],
                $sort['dir']
            );
        } catch (Throwable) {
            return [
                'ok' => false,
                'error' => 'database_unavailable',
                'q' => $query,
                'sort' => $sort['sort'],
                'dir' => $sort['dir'],
                'items' => [],
                'pagination' => $this->pagination(0, $page),
            ];
        }

        $total = (int) ($result['total'] ?? 0);

        return [
            'ok' => true,
            'error' => null,
            'q' => $query,
            'sort' => $sort['sort'],
            'dir' => $sort['dir'],
            'items' => array_map(
                fn (array $row): array =>
                    $this->userViewModel($row),
                $result['items'] ?? []
            ),
            'pagination' => $this->pagination(
                $total,
                $page
            ),
        ];
    }

    private function userViewModel(array $row): array
    {
        $highestRole = AdminLookup::value(
            $row['highest_role_title'] ?? null
        );

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => AdminLookup::value(
                $row['full_name'] ?? null
            ),
            'username' => AdminLookup::value(
                $row['username'] ?? null
            ),
            'mobile' => AdminLookup::value(
                $row['mobile'] ?? null
            ),
            'email' => AdminLookup::value(
                $row['email'] ?? null
            ),
            'status' => AdminLookup::status(
                (string) ($row['status'] ?? '')
            ),
            'role_count' => (int) (
                $row['active_role_count'] ?? 0
            ),
            'highest_role' => $highestRole,
            'primary_org_unit' => AdminLookup::value(
                $row['primary_org_unit_title'] ?? null
            ),
            'created_at' => $this->formatDate(
                $row['created_at'] ?? null
            ),
            'detail_url' => '/admin/users/'
                . (int) ($row['id'] ?? 0),
        ];
    }

    private function normalizeQuery(string $query): string
    {
        $query = trim($query);
        $query = preg_replace('/\s+/u', ' ', $query)
            ?? $query;

        return function_exists('mb_substr')
            ? mb_substr(
                $query,
                0,
                self::MAX_QUERY_LENGTH,
                'UTF-8'
            )
            : substr(
                $query,
                0,
                self::MAX_QUERY_LENGTH
            );
    }

    private function normalizePage(mixed $page): int
    {
        return (int) filter_var(
            $page,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default' => 1,
                    'min_range' => 1,
                    'max_range' => self::MAX_PAGE,
                ],
            ]
        );
    }

    private function pagination(
        int $total,
        int $page
    ): array {
        $lastPage = max(
            1,
            (int) ceil($total / self::PER_PAGE)
        );
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

    private function formatDate(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === ''
            ? AdminLookup::value(null)
            : AdminFormat::jalaliDateTime($value);
    }
}
