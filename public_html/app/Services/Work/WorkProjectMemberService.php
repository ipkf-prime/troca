<?php

namespace App\Services\Work;

use App\Repositories\WorkProjectMemberRepository;
use App\Services\BaseService;
use App\Services\UserIdentityLabelService;

class WorkProjectMemberService extends BaseService
{
    private const ROLE_OPTIONS = [
        'manager' => 'مدیر پروژه',
        'member' => 'عضو پروژه',
        'observer' => 'ناظر',
    ];

    public function __construct(
        private ?WorkProjectMemberRepository $members = null,
        private ?UserIdentityLabelService $identities = null
    ) {
        $this->members ??= new WorkProjectMemberRepository();
        $this->identities ??= new UserIdentityLabelService();
    }

    public function view(string $publicReference): array
    {
        $project = $this->members->project(trim($publicReference));
        if ($project === null) {
            return ['ok' => false];
        }

        $items = $this->members->members((int) $project['id']);
        $activeReferences = [];
        $references = [];
        $fallbacks = [];

        foreach ($items as $item) {
            $reference = (string) ($item['user_reference'] ?? '');
            if ($reference !== '') {
                $references[] = $reference;
                $fallbacks[$reference] = (string) ($item['display_name_snapshot'] ?? '');
            }
        }

        $labels = $this->identities->labelsForReferences($references, $fallbacks);

        foreach ($items as &$item) {
            $reference = (string) ($item['user_reference'] ?? '');
            $item['identity_label'] = $labels[$reference]
                ?? $this->identities->labelForReference(
                    $reference,
                    (string) ($item['display_name_snapshot'] ?? '')
                );
            $item['role_title'] = $this->roleTitle((string) ($item['role_code'] ?? ''));
            $item['joined_date_fa'] = \App\Support\PersianDate::fromGregorianDate(
                substr((string) ($item['joined_at'] ?? ''), 0, 10)
            );
            $activeReferences[$reference] = true;
        }
        unset($item);

        $users = [];
        foreach ($this->members->activeUsers() as $user) {
            $reference = 'user:' . (int) $user['id'];
            if (isset($activeReferences[$reference])) {
                continue;
            }

            $user['display_name'] = $this->identities->optionLabelFromRow($user);
            $users[] = $user;
        }

        return [
            'ok' => true,
            'project' => $project,
            'members' => $items,
            'users' => $users,
            'role_options' => self::ROLE_OPTIONS,
        ];
    }

    public function add(string $publicReference, array $input, int $actorUserId, array $context = []): array
    {
        $project = $this->members->project(trim($publicReference));
        if ($project === null) {
            return ['ok' => false, 'not_found' => true, 'errors' => []];
        }
        if (!empty($project['archived_at'])) {
            return ['ok' => false, 'errors' => ['project' => 'پروژه بایگانی‌شده قابل تغییر نیست.']];
        }

        $userId = (int) ($input['user_id'] ?? 0);
        $roleCode = trim((string) ($input['role_code'] ?? 'member'));
        $errors = [];

        if ($userId < 1) {
            $errors['user_id'] = 'یک کاربر را انتخاب کنید.';
        }
        if (!array_key_exists($roleCode, self::ROLE_OPTIONS)) {
            $errors['role_code'] = 'نقش انتخاب‌شده معتبر نیست.';
        }

        $user = $userId > 0 ? $this->members->activeUser($userId) : null;
        if ($userId > 0 && $user === null) {
            $errors['user_id'] = 'کاربر انتخاب‌شده فعال یا معتبر نیست.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->members->saveMember(
            (int) $project['id'],
            $userId,
            $this->identities->labelFromRow($user) ?: 'کاربر',
            $roleCode,
            'user:' . $actorUserId,
            $this->actorDisplayName($context, $actorUserId)
        );

        return ['ok' => true];
    }

    public function updateRole(
        string $publicReference,
        int $memberId,
        array $input,
        int $actorUserId,
        array $context = []
    ): bool {
        $project = $this->members->project(trim($publicReference));
        $roleCode = trim((string) ($input['role_code'] ?? ''));
        if ($project === null || !empty($project['archived_at']) || $memberId < 1) {
            return false;
        }
        if (!array_key_exists($roleCode, self::ROLE_OPTIONS)) {
            return false;
        }

        return $this->members->updateRole(
            (int) $project['id'],
            $memberId,
            $roleCode,
            'user:' . $actorUserId,
            $this->actorDisplayName($context, $actorUserId)
        );
    }

    public function remove(
        string $publicReference,
        int $memberId,
        int $actorUserId,
        array $context = []
    ): bool {
        $project = $this->members->project(trim($publicReference));
        if ($project === null || !empty($project['archived_at']) || $memberId < 1) {
            return false;
        }

        return $this->members->removeMember(
            (int) $project['id'],
            $memberId,
            'user:' . $actorUserId,
            $this->actorDisplayName($context, $actorUserId)
        );
    }

    private function roleTitle(string $roleCode): string
    {
        return $roleCode === 'owner'
            ? 'مالک پروژه'
            : (self::ROLE_OPTIONS[$roleCode] ?? $roleCode);
    }

    private function actorDisplayName(array $context, int $userId): string
    {
        return $this->userIdentityLabel($userId, $context);
    }
}
