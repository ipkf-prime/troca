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
        /*
         * access_surface_capabilities
         *
         * access.manage is the umbrella permission.
         * Granular permissions remain independently usable.
         */
        $capabilities = [
            'roles' =>
                $this->allowedAny(
                    $actorUserId,
                    [
                        'access.manage',
                        'access.roles.manage',
                    ]
                ),

            'users' =>
                $this->allowedAny(
                    $actorUserId,
                    [
                        'access.manage',
                        'access.users.search',
                        'access.users.manage',
                    ]
                ),

            'users_manage' =>
                $this->allowedAny(
                    $actorUserId,
                    [
                        'access.manage',
                        'access.users.manage',
                    ]
                ),

            'audit' =>
                $this->allowedAny(
                    $actorUserId,
                    [
                        'access.manage',
                        'access.audit.view',
                    ]
                ),

            'scopes' =>
                $this->allowedAny(
                    $actorUserId,
                    [
                        'access.manage',
                        'access.scopes.manage',
                        'access.users.manage',
                    ]
                ),
        ];

        if (
            !$capabilities['roles']
            && !$capabilities['users']
            && !$capabilities['audit']
        ) {
            throw new RuntimeException(
                'access_management_forbidden'
            );
        }

        $availableTabs = [];

        foreach (
            ['roles', 'users', 'audit']
            as $candidate
        ) {
            if (!empty($capabilities[$candidate])) {
                $availableTabs[] = $candidate;
            }
        }

        $tab =
            strtolower(
                trim(
                    (string) (
                        $filters['tab']
                        ?? 'roles'
                    )
                )
            );

        if (
            !in_array(
                $tab,
                $availableTabs,
                true
            )
        ) {
            $tab =
                (string) (
                    $availableTabs[0]
                    ?? 'roles'
                );
        }

        $query =
            $capabilities['users']
                ? trim(
                    (string) (
                        $filters['q']
                        ?? ''
                    )
                )
                : '';

        $userId =
            $capabilities['users']
                ? max(
                    0,
                    (int) (
                        $filters['user_id']
                        ?? 0
                    )
                )
                : 0;

        $assignmentId =
            $capabilities['users']
                ? max(
                    0,
                    (int) (
                        $filters[
                            'assignment_id'
                        ]
                        ?? 0
                    )
                )
                : 0;

        $data =
            $this->repository->page(
                $query,
                $userId
            );

        if (!$capabilities['roles']) {
            $data['roles'] = [];
            $data['permissions'] = [];
            $data['groups'] = [];
            $data['role_map'] = [];
        }

        if (!$capabilities['users']) {
            $data['users'] = [];
            $data['selected_user'] = null;
            $data['assignments'] = [];
        }

        if (!$capabilities['audit']) {
            $data['audit'] = [];
        }

        $assignments =
            $data['assignments']
            ?? [];

        if (
            $assignmentId > 0
            && !in_array(
                $assignmentId,
                array_map(
                    static fn (array $row): int =>
                        (int) $row['id'],
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

        $data['overrides'] =
            $userId > 0
            && $capabilities['users']
                ? $this->repository
                    ->overrideMap(
                        $userId,
                        $assignmentId
                    )
                : [];

        $data['inherited'] =
            $userId > 0
            && $capabilities['users']
                ? $this->repository
                    ->inheritedMap(
                        $userId,
                        $assignmentId
                    )
                : [];

        $data['notification_policy'] =
            $userId > 0
            && $capabilities['users']
                ? $this->repository
                    ->notificationPolicy(
                        $userId,
                        $assignmentId
                    )
                : 'none';

        $data['assignable_roles'] = [];
        $data['selected_role_ids'] = [];
        $data['role_states'] = [];

        if (
            $userId > 0
            && $capabilities['users_manage']
        ) {
            $roleForm =
                (new AdminUserManagementService())
                    ->form(
                        $actorUserId,
                        $userId
                    );

            if (($roleForm['ok'] ?? false) === true) {
                $data['assignable_roles'] =
                    is_array(
                        $roleForm['roles']
                        ?? null
                    )
                        ? $roleForm['roles']
                        : [];

                $data['selected_role_ids'] =
                    array_map(
                        'intval',
                        is_array(
                            $roleForm['form']['role_ids']
                            ?? null
                        )
                            ? $roleForm['form']['role_ids']
                            : []
                    );

                $data['role_states'] =
                    is_array(
                        $roleForm['role_states']
                        ?? null
                    )
                        ? $roleForm['role_states']
                        : [];
            }
        }

        $data['capabilities'] =
            $capabilities;

        $data['available_tabs'] =
            $availableTabs;

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

    public function saveUserRoles(
        int $actorUserId,
        array $input
    ): array {
        $this->authorize(
            $actorUserId,
            [
                'access.manage',
                'access.users.manage',
            ]
        );

        $userId =
            max(
                0,
                (int) (
                    $input['user_id']
                    ?? 0
                )
            );

        if ($userId < 1) {
            throw new RuntimeException(
                'access_user_not_found'
            );
        }

        $result =
            (new AdminUserManagementService())
                ->updateRoles(
                    $actorUserId,
                    $userId,
                    [
                        'role_ids' =>
                            is_array(
                                $input['role_ids']
                                ?? null
                            )
                                ? $input['role_ids']
                                : [],
                    ]
                );

        if (($result['ok'] ?? false) !== true) {
            if (
                ($result['not_found'] ?? false)
                === true
            ) {
                throw new RuntimeException(
                    'access_user_not_found'
                );
            }

            if (
                ($result['forbidden'] ?? false)
                === true
            ) {
                throw new RuntimeException(
                    'access_management_forbidden'
                );
            }

            throw new RuntimeException(
                'access_user_roles_update_failed'
            );
        }

        return [
            'user_id' => $userId,
        ];
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

    public function saveDefaultRole(
        int $actorUserId,
        array $input,
        string $ip
    ): array {
        $this->authorize(
            $actorUserId,
            [
                'access.manage',
                'access.users.manage',
            ]
        );

        $userId =
            max(
                0,
                (int) (
                    $input['user_id']
                    ?? 0
                )
            );

        $assignmentId =
            max(
                0,
                (int) (
                    $input[
                        'default_role_assignment_id'
                    ]
                    ?? 0
                )
            );

        if ($userId < 1) {
            throw new RuntimeException(
                'access_user_not_found'
            );
        }

        $this->repository
            ->saveDefaultRoleAssignment(
                $userId,
                $assignmentId,
                $actorUserId,
                $ip
            );

        return [
            'user_id' => $userId,
            'assignment_id' =>
                $assignmentId,
        ];
    }


    private function allowedAny(
        int $userId,
        array $permissions
    ): bool {
        foreach ($permissions as $permission) {
            if (
                $this->authorization
                    ->hasPermission(
                        $userId,
                        (string) $permission
                    )
            ) {
                return true;
            }
        }

        return false;
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
