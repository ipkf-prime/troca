<?php

namespace App\Services;

use App\Repositories\RoleRepository;
use IPKF\Support\Session;

class AccessService extends BaseService
{
    public function __construct(protected ?RoleRepository $roles = null)
    {
        $this->roles ??= new RoleRepository();
    }

    public function ensureDefaultAssignment(int $userId): void
    {
        $this->roles->ensureBaseUserAssignment($userId);
    }

    public function selectLowest(int $userId): ?array
    {
        $assignment = $this->roles->lowestAssignmentForUser($userId);

        if ($assignment !== null) {
            Session::put('active_role_assignment_id', (int) $assignment['id']);
        }

        return $assignment;
    }

    public function selectPreferred(
        int $userId
    ): ?array {
        $assignment =
            $this->roles
                ->defaultAssignmentForUser(
                    $userId
                )
            ?? $this->roles
                ->lowestAssignmentForUser(
                    $userId
                );

        if ($assignment !== null) {
            Session::put(
                'active_role_assignment_id',
                (int) $assignment['id']
            );
        }

        return $assignment;
    }


    public function activeAssignment(int $userId): ?array
    {
        $id = Session::get('active_role_assignment_id');

        if ($id !== null) {
            $assignment = $this->roles->assignmentForUser($userId, (int) $id);

            if ($assignment !== null) {
                return $assignment;
            }
        }

        return $this->selectPreferred($userId);
    }

    public function switchTo(int $userId, int $assignmentId): ?array
    {
        $assignment = $this->roles->assignmentForUser($userId, $assignmentId);

        if ($assignment === null) {
            return null;
        }

        Session::put('active_role_assignment_id', (int) $assignment['id']);

        return $assignment;
    }

    public function assignments(int $userId): array
    {
        return $this->roles->assignmentsForUser($userId);
    }
}
