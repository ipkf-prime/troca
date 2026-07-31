<?php

namespace App\Services;

use App\Repositories\AdminUserManagementRepository;
use Throwable;

class AdminUserManagementService extends BaseService
{
    public function __construct(
        private ?AdminUserManagementRepository $users = null,
        private ?IdentityNormalizer $normalizer = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->users ??= new AdminUserManagementRepository();
        $this->normalizer ??= new IdentityNormalizer();
        $this->authorization ??= new AuthorizationService();
    }

    public function canCreate(int $actorUserId): bool
    {
        return $this->authorization->hasPermission(
            $actorUserId,
            'users.create'
        ) || $this->authorization->hasPermission(
            $actorUserId,
            'users.manage'
        );
    }

    public function canUpdate(int $actorUserId): bool
    {
        return $this->authorization->hasPermission(
            $actorUserId,
            'users.update'
        ) || $this->authorization->hasPermission(
            $actorUserId,
            'users.manage'
        );
    }

    public function form(
        int $actorUserId,
        ?int $userId = null,
        array $old = []
    ): array {
        $includeProtected = $this->canAssignProtectedRoles($actorUserId);
        $existing = $userId === null
            ? null
            : $this->users->findForForm($userId);

        if ($userId !== null && $existing === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $form = [
            'id' => $userId,
            'first_name' => '',
            'last_name' => '',
            'full_name' => '',
            'username' => '',
            'email' => '',
            'mobile' => '',
            'status' => 'active',
            'email_verified' => false,
            'mobile_verified' => false,
            'role_ids' => [],
            'access_kind' => 'all',
            'access_area' => 'all',
            'role_search' => '',
        ];

        if ($existing !== null) {
            $form = [
                'id' => (int) $existing['id'],
                'first_name' => (string) (
                    $existing['first_name'] ?? ''
                ),
                'last_name' => (string) (
                    $existing['last_name'] ?? ''
                ),
                'full_name' => (string) (
                    $existing['full_name'] ?? ''
                ),
                'username' => (string) (
                    $existing['username'] ?? ''
                ),
                'email' => (string) ($existing['email'] ?? ''),
                'mobile' => (string) ($existing['mobile'] ?? ''),
                'status' => (string) (
                    $existing['status'] ?? 'active'
                ),
                'email_verified' => !empty(
                    $existing['email_verified_at']
                ),
                'mobile_verified' => !empty(
                    $existing['mobile_verified_at']
                ),
                'role_ids' => array_map(
                    'intval',
                    $existing['role_ids'] ?? []
                ),
                'access_kind' => 'all',
                'access_area' => 'all',
                'role_search' => '',
            ];
        }

        if ($old !== []) {
            $form = $this->mergeOldInput($form, $old);
        }

        return [
            'ok' => true,
            'form' => $form,
            'roles' => $this->users->roles($includeProtected),
            'role_kinds' => $this->accessOptions(
                $this->users->roleKinds($includeProtected),
                'همه انواع دسترسی'
            ),
            'role_areas' => $this->accessOptions(
                $this->users->roleAreas($includeProtected),
                'همه حوزه‌ها'
            ),
            'status_options' => [
                'active' => 'فعال',
                'inactive' => 'غیرفعال',
            ],
            'is_edit' => $userId !== null,
            'can_assign_protected_roles' => $includeProtected,
        ];
    }

    public function create(
        int $actorUserId,
        array $input
    ): array {
        if (!$this->canCreate($actorUserId)) {
            return [
                'ok' => false,
                'forbidden' => true,
                'errors' => [],
            ];
        }

        $validated = $this->validate(
            $input,
            null,
            true,
            $actorUserId
        );

        if ($validated['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validated['errors'],
                'form' => $validated['form'],
            ];
        }

        try {
            $userId = $this->users->create(
                $validated['data'],
                $validated['role_ids']
            );
        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'database' => 'ثبت کاربر در پایگاه داده انجام نشد.',
                ],
                'form' => $validated['form'],
            ];
        }

        return ['ok' => true, 'user_id' => $userId];
    }

    public function update(
        int $actorUserId,
        int $userId,
        array $input
    ): array {
        if (!$this->canUpdate($actorUserId)) {
            return [
                'ok' => false,
                'forbidden' => true,
                'errors' => [],
            ];
        }

        $existing = $this->users->findForForm($userId);
        if ($existing === null) {
            return [
                'ok' => false,
                'not_found' => true,
                'errors' => [],
            ];
        }

        $validated = $this->validate(
            $input,
            $userId,
            false,
            $actorUserId
        );

        if (
            $actorUserId === $userId
            && $validated['data']['status'] !== 'active'
        ) {
            $validated['errors']['status'] =
                'نمی‌توانید حساب فعال خودتان را غیرفعال کنید.';
        }

        if ($validated['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validated['errors'],
                'form' => $validated['form'],
            ];
        }

        $preserveOwnSuperAdmin = $actorUserId === $userId
            && $this->users->userHasGlobalRole(
                $userId,
                'super_admin'
            );

        try {
            $this->users->update(
                $userId,
                $validated['data'],
                $validated['role_ids'],
                $preserveOwnSuperAdmin
            );
        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'database' => 'ذخیره تغییرات کاربر انجام نشد.',
                ],
                'form' => $validated['form'],
            ];
        }

        return ['ok' => true, 'user_id' => $userId];
    }

    private function validate(
        array $input,
        ?int $exceptUserId,
        bool $passwordRequired,
        int $actorUserId
    ): array {
        $firstName = $this->limit(
            trim((string) ($input['first_name'] ?? '')),
            100
        );
        $lastName = $this->limit(
            trim((string) ($input['last_name'] ?? '')),
            100
        );
        $usernameRaw = trim(
            (string) ($input['username'] ?? '')
        );
        $emailRaw = trim((string) ($input['email'] ?? ''));
        $mobileRaw = trim((string) ($input['mobile'] ?? ''));
        $status = trim(
            (string) ($input['status'] ?? 'active')
        );
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) (
            $input['password_confirmation'] ?? ''
        );
        $emailVerified = (string) (
            $input['email_verified'] ?? '0'
        ) === '1';
        $mobileVerified = (string) (
            $input['mobile_verified'] ?? '0'
        ) === '1';

        $username = $this->normalizer->username($usernameRaw);
        $email = $emailRaw === ''
            ? null
            : $this->normalizer->email($emailRaw);
        $mobile = $mobileRaw === ''
            ? null
            : $this->normalizer->mobile($mobileRaw);

        $roleIdsRaw = $input['role_ids'] ?? [];
        if (!is_array($roleIdsRaw)) {
            $roleIdsRaw = [];
        }

        $includeProtected = $this->canAssignProtectedRoles(
            $actorUserId
        );
        $roleIds = $this->users->roleIdsByIds(
            $roleIdsRaw,
            $includeProtected
        );

        $errors = [];

        if ($firstName === '') {
            $errors['first_name'] = 'نام الزامی است.';
        }

        if ($lastName === '') {
            $errors['last_name'] = 'نام خانوادگی الزامی است.';
        }

        if ($username === null) {
            $errors['username'] =
                'نام کاربری باید با حرف انگلیسی شروع شود و فقط شامل حروف، عدد و زیرخط باشد.';
        } elseif (
            $this->users->identityExists(
                'username',
                $username,
                $exceptUserId
            )
        ) {
            $errors['username'] =
                'این نام کاربری قبلاً ثبت شده است.';
        }

        if ($emailRaw !== '' && $email === null) {
            $errors['email'] = 'ایمیل معتبر نیست.';
        } elseif (
            $email !== null
            && $this->users->identityExists(
                'email',
                $email,
                $exceptUserId
            )
        ) {
            $errors['email'] = 'این ایمیل قبلاً ثبت شده است.';
        }

        if ($mobileRaw !== '' && $mobile === null) {
            $errors['mobile'] =
                'شماره موبایل باید با الگوی 09xxxxxxxxx باشد.';
        } elseif (
            $mobile !== null
            && $this->users->identityExists(
                'mobile',
                $mobile,
                $exceptUserId
            )
        ) {
            $errors['mobile'] =
                'این شماره موبایل قبلاً ثبت شده است.';
        }

        if ($email === null && $mobile === null) {
            $errors['contact'] =
                'ثبت حداقل ایمیل یا شماره موبایل الزامی است.';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'وضعیت حساب معتبر نیست.';
            $status = 'active';
        }

        if ($passwordRequired || $password !== '') {
            if (strlen($password) < 10) {
                $errors['password'] =
                    'رمز عبور باید حداقل ۱۰ کاراکتر باشد.';
            } elseif ($password !== $confirmation) {
                $errors['password_confirmation'] =
                    'تکرار رمز عبور یکسان نیست.';
            }
        }

        $fullName = trim($firstName . ' ' . $lastName);

        $form = [
            'id' => $exceptUserId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'username' => $usernameRaw,
            'email' => $emailRaw,
            'mobile' => $mobileRaw,
            'status' => $status,
            'email_verified' => $emailVerified,
            'mobile_verified' => $mobileVerified,
            'role_ids' => array_map('intval', $roleIdsRaw),
            'access_kind' => trim((string) ($input['access_kind'] ?? 'all')) ?: 'all',
            'access_area' => trim((string) ($input['access_area'] ?? 'all')) ?: 'all',
            'role_search' => $this->limit(trim((string) ($input['role_search'] ?? '')), 80),
        ];

        return [
            'errors' => $errors,
            'form' => $form,
            'role_ids' => $roleIds,
            'data' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'username' => $username,
                'username_norm' => $username,
                'email' => $email,
                'email_norm' => $email,
                'mobile' => $mobile,
                'mobile_norm' => $mobile,
                'status' => $status,
                'email_verified' => $emailVerified,
                'mobile_verified' => $mobileVerified,
                'password_hash' => $password === ''
                    ? null
                    : password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    ),
            ],
        ];
    }

    private function canAssignProtectedRoles(int $actorUserId): bool
    {
        return $this->authorization->hasPermission(
            $actorUserId,
            'permissions.assign'
        );
    }

    private function mergeOldInput(
        array $form,
        array $old
    ): array {
        foreach ([
            'first_name',
            'last_name',
            'username',
            'email',
            'mobile',
            'status',
            'email_verified',
            'mobile_verified',
            'role_ids',
            'access_kind',
            'access_area',
            'role_search',
        ] as $field) {
            if (array_key_exists($field, $old)) {
                $form[$field] = $old[$field];
            }
        }

        return $form;
    }

    private function accessOptions(array $rows, string $allTitle): array
    {
        return array_merge([[
            'id' => 0,
            'code' => 'all',
            'title' => $allTitle,
        ]], array_values($rows));
    }

    private function limit(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}
