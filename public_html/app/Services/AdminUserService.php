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
            'detail_url' => '/admin/users/' . (int) ($row['id'] ?? 0),
        ];
    }

    public function detail(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        try {
            $user = $this->users->findDetail($userId);

            if ($user === null) {
                return null;
            }

            return [
                'user' => $this->detailUserViewModel($user),
                'roles' => array_map(
                    fn (array $row): array => $this->roleAssignmentViewModel($row),
                    $this->users->roleAssignmentsForDetail($userId)
                ),
                'organization_assignments' => array_map(
                    fn (array $row): array => $this->organizationAssignmentViewModel($row),
                    $this->users->organizationAssignmentsForDetail($userId)
                ),
                'security' => $this->securityViewModel($this->users->securitySummaryForDetail($userId)),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function detailUserViewModel(array $row): array
    {
        $roles = $this->splitRoleTitles((string) ($row['active_role_titles'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'person_id' => $row['person_id'] === null ? $this->value(null) : \App\Support\AdminFormat::digits((int) $row['person_id']),
            'full_name' => $this->value($row['full_name'] ?? null),
            'first_name' => $this->value($row['first_name'] ?? null),
            'last_name' => $this->value($row['last_name'] ?? null),
            'display_name' => $this->value($row['full_name'] ?? $row['username'] ?? null),
            'username' => $this->value($row['username'] ?? null),
            'mobile' => $this->value($row['mobile'] ?? null),
            'email' => $this->value($row['email'] ?? null),
            'status' => $this->status((string) ($row['status'] ?? '')),
            'person_status' => $this->status((string) ($row['person_status'] ?? '')),
            'email_verified' => $this->verified((string) ($row['email_verified_at'] ?? '')),
            'mobile_verified' => $this->verified((string) ($row['mobile_verified_at'] ?? '')),
            'last_login_at' => $this->formatDate($row['last_login_at'] ?? null),
            'created_at' => $this->formatDate($row['created_at'] ?? null),
            'updated_at' => $this->formatDate($row['updated_at'] ?? null),
            'avatar_url' => $this->safeAvatarPath($row['avatar'] ?? null),
            'active_role_count' => (int) ($row['active_role_count'] ?? 0),
            'active_role_count_label' => \App\Support\AdminFormat::digits((int) ($row['active_role_count'] ?? 0)),
            'active_role_summary' => $roles !== [] ? implode('، ', $roles) : $this->value(null),
            'primary_org_unit' => $this->value($row['primary_org_unit_title'] ?? null),
        ];
    }

    private function roleAssignmentViewModel(array $row): array
    {
        return [
            'role_title' => $this->value($row['role_title'] ?? null),
            'role_code' => $this->value($row['role_code'] ?? null),
            'priority' => \App\Support\AdminFormat::digits((int) ($row['priority'] ?? 100)),
            'status' => $this->assignmentStatus($row),
            'scope_summary' => $this->scopeSummary((string) ($row['scope_type'] ?? '')),
            'include_children' => ((int) ($row['include_children'] ?? 0)) === 1 ? 'بله' : 'خیر',
            'starts_at' => $this->formatDate($row['starts_at'] ?? null),
            'ends_at' => $this->formatDate($row['ends_at'] ?? null),
        ];
    }

    private function organizationAssignmentViewModel(array $row): array
    {
        return [
            'org_unit_title' => $this->value($row['org_unit_title'] ?? null),
            'org_unit_code' => $this->value($row['org_unit_code'] ?? null),
            'position_title' => $this->value($row['position_title'] ?? null),
            'position_code' => $this->value($row['position_code'] ?? null),
            'is_primary' => ((int) ($row['is_primary'] ?? 0)) === 1 ? 'بله' : 'خیر',
            'status' => $this->status((string) ($row['status'] ?? '')),
            'started_at' => $this->formatDate($row['started_at'] ?? null),
            'ended_at' => $this->formatDate($row['ended_at'] ?? null),
        ];
    }

    private function securityViewModel(array $summary): array
    {
        $enabledMethods = (int) ($summary['enabled_methods_count'] ?? 0);
        $totpMethods = (int) ($summary['totp_methods_count'] ?? 0);
        $recoveryCodes = (int) ($summary['recovery_codes_count'] ?? 0);
        $trustedDevices = (int) ($summary['trusted_devices_count'] ?? 0);

        return [
            'mfa_enabled' => $enabledMethods > 0 ? 'بله' : 'خیر',
            'totp_enabled' => $totpMethods > 0 ? 'بله' : 'خیر',
            'recovery_codes_available' => $recoveryCodes > 0 ? 'بله' : 'خیر',
            'recovery_codes_count' => \App\Support\AdminFormat::digits($recoveryCodes),
            'trusted_devices_available' => $trustedDevices > 0 ? 'بله' : 'خیر',
            'trusted_devices_count' => \App\Support\AdminFormat::digits($trustedDevices),
        ];
    }

    private function verified(string $value): array
    {
        return trim($value) === ''
            ? ['code' => 'inactive', 'label' => 'تأیید نشده']
            : ['code' => 'active', 'label' => 'تأیید شده'];
    }

    private function assignmentStatus(array $row): array
    {
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            return ['code' => 'inactive', 'label' => 'غیرفعال'];
        }

        $now = time();
        $startsAt = trim((string) ($row['starts_at'] ?? ''));
        $endsAt = trim((string) ($row['ends_at'] ?? ''));

        if ($startsAt !== '' && strtotime($startsAt) > $now) {
            return ['code' => 'pending', 'label' => 'در انتظار'];
        }

        if ($endsAt !== '' && strtotime($endsAt) < $now) {
            return ['code' => 'inactive', 'label' => 'منقضی'];
        }

        return ['code' => 'active', 'label' => 'فعال'];
    }

    private function scopeSummary(string $scopeType): string
    {
        return match (strtolower(trim($scopeType))) {
            '', 'global' => 'سراسری',
            default => 'محدوده ' . $this->value($scopeType),
        };
    }

    private function safeAvatarPath(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if (preg_match('/^\/(?:assets\/admin\/images\/|uploads\/admin\/avatars\/)[A-Za-z0-9_\-\/\.]+\.(?:svg|png|jpg|jpeg|webp|gif)$/i', $value) === 1) {
            return $value;
        }

        return '/assets/admin/images/avatars/default-avatar.svg';
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
