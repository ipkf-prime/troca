<?php

namespace App\Services;

use App\Repositories\AccessControlRepository;
use RuntimeException;

class AccessControlService extends BaseService
{
    public function __construct(
        private ?AccessControlRepository $repository = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??= new AccessControlRepository();
        $this->authorization ??= new AuthorizationService();
    }

    public function page(int $actorUserId, array $filters): array
    {
        $this->authorize(
            $actorUserId,
            ['access.manage', 'access.roles.manage',
                'access.users.search', 'access.audit.view']
        );

        $tab = strtolower(trim((string) ($filters['tab'] ?? 'roles')));

        if (!in_array($tab, ['roles', 'users', 'audit'], true)) {
            $tab = 'roles';
        }

        $query = trim((string) ($filters['q'] ?? ''));
        $userId = max(0, (int) ($filters['user_id'] ?? 0));
        $assignmentId = max(
            0,
            (int) ($filters['assignment_id'] ?? 0)
        );
        $data = $this->repository->page($query, $userId);
        $assignments = $data['assignments'] ?? [];

        if (
            $assignmentId > 0
            && !in_array(
                $assignmentId,
                array_map(
                    static fn (array $row): int => (int) $row['id'],
                    $assignments
                ),
                true
            )
        ) {
            $assignmentId = 0;
        }

        $data['tab'] = $tab;
        $data['query'] = $query;
        $data['selected_user_id'] = $userId;
        $data['assignment_id'] = $assignmentId;
        $data['overrides'] = $userId > 0
            ? $this->repository->overrideMap($userId, $assignmentId)
            : [];
        $data['inherited'] = $userId > 0
            ? $this->repository->inheritedMap($userId, $assignmentId)
            : [];
        $data['notification_policy'] = $userId > 0
            ? $this->repository->notificationPolicy(
                $userId,
                $assignmentId
            )
            : 'none';

        return $data;
    }

    public function saveRole(
        int $actorUserId,
        array $input,
        string $ip
    ): int {
        $this->authorize(
            $actorUserId,
            ['access.manage', 'access.roles.manage']
        );

        $roleId = max(0, (int) ($input['role_id'] ?? 0));
        $codes = is_array($input['permissions'] ?? null)
            ? $input['permissions']
            : [];

        $this->repository->saveRolePermissions(
            $roleId,
            $codes,
            $actorUserId,
            trim((string) ($input['reason'] ?? '')),
            $ip
        );

        return $roleId;
    }

    public function saveUser(
        int $actorUserId,
        array $input,
        string $ip
    ): array {
        $this->authorize(
            $actorUserId,
            ['access.manage', 'access.users.manage']
        );

        $userId = max(0, (int) ($input['user_id'] ?? 0));
        $assignmentId = max(
            0,
            (int) ($input['role_assignment_id'] ?? 0)
        );
        $reason = trim((string) ($input['reason'] ?? ''));

        if (mb_strlen($reason, 'UTF-8') < 3) {
            throw new RuntimeException('access_reason_required');
        }

        $this->repository->saveUserPolicy(
            $userId,
            $assignmentId,
            strtolower(trim((string) (
                $input['notification_policy'] ?? 'inherit'
            ))),
            !empty($input['can_search_recipients']),
            !empty($input['can_view_recipient_details']),
            !empty($input['can_use_manual_targets']),
            $actorUserId,
            $reason,
            $ip
        );

        return [
            'user_id' => $userId,
            'assignment_id' => $assignmentId,
        ];
    }

    private function authorize(int $userId, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->authorization->hasPermission($userId, $permission)) {
                return;
            }
        }

        throw new RuntimeException('access_management_forbidden');
    }
}
