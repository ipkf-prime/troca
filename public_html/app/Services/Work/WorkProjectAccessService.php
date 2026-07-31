<?php

namespace App\Services\Work;

use App\Repositories\WorkProjectAccessRepository;
use App\Services\AuthorizationService;
use App\Services\BaseService;
use App\Support\AdminTableSort;

class WorkProjectAccessService extends BaseService
{
    private const PROJECT_ROLES = ['owner', 'manager', 'member', 'observer'];

    public function __construct(
        private ?WorkProjectAccessRepository $projects = null,
        private ?AuthorizationService $authorization = null,
        private ?WorkReferenceDataService $references = null
    ) {
        $this->projects ??= new WorkProjectAccessRepository();
        $this->authorization ??= new AuthorizationService();
        $this->references ??= new WorkReferenceDataService();
    }

    public function isPlatformAdmin(int $userId): bool
    {
        return $userId > 0
            && $this->authorization->hasPermission($userId, 'work.project.admin');
    }

    public function projectIndex(int $userId, array $filters = []): array
    {
        $statusOptions = $this->indexStatusOptions();
        $q = $this->limit(trim((string) ($filters['q'] ?? '')), 120);
        $status = trim((string) ($filters['status'] ?? ''));
        $sort = AdminTableSort::resolve(
            $filters,
            [
                'title' => 'title',
                'status' => 'status',
                'visibility' => 'visibility',
                'owner' => 'owner',
                'members' => 'members',
                'items' => 'items',
                'open_items' => 'open_items',
                'created_at' => 'created_at',
                'target_date' => 'target_date',
                'updated_at' => 'updated_at',
            ],
            'updated_at',
            'desc'
        );

        if (!array_key_exists($status, $statusOptions)) {
            $status = '';
        }

        $normalizedFilters = [
            'q' => $q,
            'status' => $status,
            'sort' => $sort['column'],
            'dir' => $sort['direction'],
        ];

        $rows = $this->projects->index(
            $normalizedFilters,
            $this->userReference($userId),
            $this->isPlatformAdmin($userId)
        );

        $statuses = $this->references->projectStatuses();
        $visibilities = $this->references->projectVisibilities();

        foreach ($rows as &$row) {
            $row['status_title'] = !empty($row['archived_at'])
                ? 'بایگانی‌شده'
                : ($statuses[(string) ($row['status_code'] ?? '')] ?? (string) ($row['status_code'] ?? ''));
            $row['visibility_title'] = $visibilities[(string) ($row['visibility_code'] ?? '')]
                ?? 'خصوصی';
            $row['my_role_title'] = $this->roleTitle((string) ($row['my_role_code'] ?? ''));
        }
        unset($row);

        return [
            'items' => $rows,
            'total' => count($rows),
            'q' => $q,
            'status' => $status,
            'sort' => $sort['column'],
            'dir' => $sort['direction'],
            'status_options' => $statusOptions,
            'can_create' => $this->authorization->hasPermission($userId, 'work.project.manage'),
            'all_projects' => $this->isPlatformAdmin($userId),
        ];
    }

    public function projectAccess(string $publicReference, int $userId): array
    {
        $project = $this->projects->project(
            trim($publicReference),
            $this->userReference($userId)
        );

        if ($project === null) {
            return ['found' => false, 'can_view' => false];
        }

        $admin = $this->isPlatformAdmin($userId);
        $role = (string) ($project['my_role_code'] ?? '');
        $activeMember = in_array($role, self::PROJECT_ROLES, true);
        $publicProject = (string) ($project['visibility_code'] ?? '') === 'public';

        $canView = $admin || (
            $this->authorization->hasPermission($userId, 'work.project.view')
            && ($activeMember || $publicProject)
        );

        $managerRole = in_array($role, ['owner', 'manager'], true);
        $contributorRole = in_array($role, ['owner', 'manager', 'member'], true);

        return [
            'found' => true,
            'project' => $project,
            'role_code' => $role,
            'role_title' => $this->roleTitle($role),
            'is_platform_admin' => $admin,
            'can_view' => $canView,
            'can_manage_project' => $admin || (
                $managerRole
                && $this->authorization->hasPermission($userId, 'work.project.manage')
            ),
            'can_manage_members' => $admin || (
                $managerRole
                && $this->authorization->hasPermission($userId, 'work.project.manage')
            ),
            'can_create_item' => $admin || (
                $contributorRole
                && $this->authorization->hasPermission($userId, 'work.item.create')
            ),
            'can_assign_item' => $admin || (
                $managerRole
                && $this->authorization->hasPermission($userId, 'work.item.assign')
            ),
            'can_contribute_item' => $admin || (
                $contributorRole
                && $this->authorization->hasPermission($userId, 'work.item.update')
            ),
            'can_view_audit' => $admin || (
                $managerRole
                && $this->authorization->hasPermission($userId, 'work.audit.view')
            ),
        ];
    }

    public function itemAccess(
        string $projectReference,
        string $itemReference,
        int $userId
    ): array {
        $item = $this->projects->item(
            trim($projectReference),
            trim($itemReference),
            $this->userReference($userId)
        );

        if ($item === null) {
            return ['found' => false, 'can_view_item' => false];
        }

        $projectAccess = $this->projectAccess($projectReference, $userId);
        if (($projectAccess['found'] ?? false) !== true) {
            return ['found' => false, 'can_view_item' => false];
        }

        $admin = (bool) ($projectAccess['is_platform_admin'] ?? false);
        $role = (string) ($projectAccess['role_code'] ?? '');
        $managerRole = in_array($role, ['owner', 'manager'], true);
        $memberRole = $role === 'member';
        $userReference = $this->userReference($userId);
        $isAssignee = (string) ($item['assignee_reference'] ?? '') === $userReference;
        $isCreator = (string) ($item['created_by_user_reference'] ?? '') === $userReference;

        $canEdit = $admin || (
            $this->authorization->hasPermission($userId, 'work.item.update')
            && (
                $managerRole
                || ($memberRole && ($isAssignee || $isCreator))
            )
        );

        return $projectAccess + [
            'found' => true,
            'item' => $item,
            'can_view_item' => (bool) ($projectAccess['can_view'] ?? false)
                && $this->authorization->hasPermission($userId, 'work.item.view'),
            'can_edit_item' => $canEdit,
            'can_archive_item' => $admin || (
                $managerRole
                && $this->authorization->hasPermission($userId, 'work.item.update')
            ),
            'is_assignee' => $isAssignee,
            'is_creator' => $isCreator,
        ];
    }

    private function indexStatusOptions(): array
    {
        return [
            '' => 'همه پروژه‌ها',
            'current' => 'پروژه‌های جاری',
        ] + $this->references->projectStatuses() + [
            'archived' => 'بایگانی‌شده',
        ];
    }

    private function roleTitle(string $role): string
    {
        return match ($role) {
            'owner' => 'مالک پروژه',
            'manager' => 'مدیر پروژه',
            'member' => 'عضو پروژه',
            'observer' => 'ناظر',
            default => 'دسترسی عمومی',
        };
    }

    private function userReference(int $userId): string
    {
        return 'user:' . max(0, $userId);
    }

    private function limit(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}
