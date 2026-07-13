<?php

namespace App\Services;

use App\Repositories\AdminUserRepository;
use App\Support\AdminFormat;
use App\Support\AdminLookup;
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

    private function detailUserViewModel(array $row): array
    {
        $roles = $this->splitRoleTitles((string) ($row['active_role_titles'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'full_name' => $this->value($row['full_name'] ?? null),
            'first_name' => $this->value($row['first_name'] ?? null),
            'last_name' => $this->value($row['last_name'] ?? null),
            'person_type' => AdminLookup::personType($row['person_type_title'] ?? null, $row['person_type_code'] ?? null),
            'province' => AdminLookup::title($row['province_title'] ?? null, (bool) ($row['province_reference_exists'] ?? false)),
            'county' => AdminLookup::title($row['county_title'] ?? null, (bool) ($row['county_reference_exists'] ?? false)),
            'city' => AdminLookup::title($row['city_title'] ?? null, (bool) ($row['city_reference_exists'] ?? false)),
            'display_name' => $this->value($row['full_name'] ?? $row['username'] ?? null),
            'national_code' => $this->maskedNationalCode($row['national_code'] ?? null),
            'father_name' => $this->value($row['father_name'] ?? null),
            'birth_date' => $this->formatDateOnly($row['birth_date'] ?? null),
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
            'active_role_count_label' => AdminFormat::digits((int) ($row['active_role_count'] ?? 0)),
            'active_role_summary' => $roles !== [] ? implode('، ', $roles) : $this->value(null),
            'primary_org_unit' => $this->value($row['primary_org_unit_title'] ?? null),
        ];
    }

    private function roleAssignmentViewModel(array $row): array
    {
        return [
            'role_title' => $this->value($row['role_title'] ?? null),
            'role_code' => $this->value($row['role_code'] ?? null),
            'priority' => AdminFormat::digits((int) ($row['priority'] ?? 100)),
            'status' => $this->assignmentStatus($row),
            'scope_summary' => AdminLookup::scopeSummary((string) ($row['scope_type'] ?? ''), $row),
            'organization_title' => AdminLookup::title($row['organization_title'] ?? null, (bool) ($row['organization_reference_exists'] ?? false)),
            'organization_type_title' => AdminLookup::title($row['organization_type_title'] ?? null, (bool) ($row['organization_type_reference_exists'] ?? false)),
            'organization_level_title' => AdminLookup::title($row['organization_level_title'] ?? null, (bool) ($row['organization_level_reference_exists'] ?? false)),
            'include_children' => AdminLookup::booleanYesNo($row['include_children'] ?? 0),
            'starts_at' => $this->formatDate($row['starts_at'] ?? null),
            'ends_at' => $this->formatDate($row['ends_at'] ?? null),
        ];
    }

    private function organizationAssignmentViewModel(array $row): array
    {
        return [
            'org_unit_title' => AdminLookup::title($row['org_unit_title'] ?? null, (bool) ($row['org_unit_reference_exists'] ?? false)),
            'org_unit_code' => $this->value($row['org_unit_code'] ?? null),
            'position_title' => AdminLookup::title($row['position_title'] ?? null, (bool) ($row['position_reference_exists'] ?? false)),
            'position_code' => $this->value($row['position_code'] ?? null),
            'is_primary' => AdminLookup::booleanYesNo($row['is_primary'] ?? 0),
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
            'mfa_enabled' => AdminLookup::booleanYesNo($enabledMethods > 0 ? 1 : 0),
            'totp_enabled' => AdminLookup::booleanYesNo($totpMethods > 0 ? 1 : 0),
            'recovery_codes_available' => AdminLookup::booleanYesNo($recoveryCodes > 0 ? 1 : 0),
            'recovery_codes_count' => AdminFormat::digits($recoveryCodes),
            'trusted_devices_available' => AdminLookup::booleanYesNo($trustedDevices > 0 ? 1 : 0),
            'trusted_devices_count' => AdminFormat::digits($trustedDevices),
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

    private function verified(string $value): array
    {
        return trim($value) === ''
            ? ['code' => 'inactive', 'label' => 'تأیید نشده']
            : ['code' => 'active', 'label' => 'تأیید شده'];
    }

    private function assignmentStatus(array $row): array
    {
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            return AdminLookup::status('inactive');
        }

        $now = time();
        $startsAt = trim((string) ($row['starts_at'] ?? ''));
        $endsAt = trim((string) ($row['ends_at'] ?? ''));

        if ($startsAt !== '' && strtotime($startsAt) > $now) {
            return AdminLookup::status('pending');
        }

        if ($endsAt !== '' && strtotime($endsAt) < $now) {
            return AdminLookup::status('expired');
        }

        return AdminLookup::status('active');
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
        return AdminLookup::status($status);
    }

    private function formatDate(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return $this->value(null);
        }

        return AdminFormat::jalaliDateTime($value);
    }

    private function formatDateOnly(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return $this->value(null);
        }

        return AdminFormat::jalaliDate($value);
    }

    private function maskedNationalCode(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return $this->value(null);
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

        if ($length <= 4) {
            return str_repeat('*', max(3, $length));
        }

        $prefix = function_exists('mb_substr') ? mb_substr($value, 0, 3, 'UTF-8') : substr($value, 0, 3);
        $suffix = function_exists('mb_substr') ? mb_substr($value, -3, null, 'UTF-8') : substr($value, -3);

        return $prefix . '****' . $suffix;
    }

    private function value(mixed $value): string
    {
        return AdminLookup::value($value);
    }
}
