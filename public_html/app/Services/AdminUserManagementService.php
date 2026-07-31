<?php

namespace App\Services;

use App\Repositories\AdminUserManagementRepository;
use App\Support\JalaliDateInput;
use Throwable;

class AdminUserManagementService extends BaseService
{
    public function __construct(
        private ?AdminUserManagementRepository $users = null,
        private ?IdentityNormalizer $normalizer = null,
        private ?AuthorizationService $authorization = null,
        private ?IdentityVerificationService $verification = null
    ) {
        $this->users ??= new AdminUserManagementRepository();
        $this->normalizer ??= new IdentityNormalizer();
        $this->authorization ??= new AuthorizationService();
        $this->verification ??=
            new IdentityVerificationService();
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
            'person_type' => 'individual',
            'first_name' => '',
            'last_name' => '',
            'full_name' => '',
            'national_code' => '',
            'father_name' => '',
            'birth_date' => '',
            'birth_date_jalali' => '',
            'birth_place' => '',
            'identity_number' => '',
            'identity_serial' => '',
            'username' => '',
            'email' => '',
            'mobile' => '',
            'status' => 'active',
            'email_verified' => false,
            'mobile_verified' => false,
            'contact_email_label' => 'ایمیل اصلی',
            'contact_mobile_label' => 'موبایل اصلی',
            'province_location_id' => 0,
            'county_location_id' => 0,
            'city_location_id' => 0,
            'address_type_id' => 0,
            'district' => '',
            'postal_code' => '',
            'address_line' => '',
            'role_ids' => [],
            'access_kind' => 'all',
            'access_area' => 'all',
            'role_search' => '',
        ];

        if ($existing !== null) {
            foreach ($form as $field => $default) {
                if (array_key_exists($field, $existing)) {
                    $form[$field] = $existing[$field] ?? $default;
                }
            }
            $form['id'] = (int) $existing['id'];
            $form['email_verified'] = !empty($existing['email_verified_at']);
            $form['mobile_verified'] = !empty($existing['mobile_verified_at']);
            $form['role_ids'] = array_map(
                'intval',
                $existing['role_ids'] ?? []
            );
            $form['birth_date_jalali'] =
                JalaliDateInput::fromGregorian(
                    $existing['birth_date'] ?? ''
                );
        }

        if ($old !== []) {
            $form = $this->mergeOldInput($form, $old);
        }

        $options = $this->users->formOptions();

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
            'person_types' => $options['person_types'],
            'provinces' => $options['provinces'],
            'counties' => $options['counties'],
            'cities' => $options['cities'],
            'address_types' => $options['address_types'],
            'status_options' => [
                'active' => 'فعال',
                'inactive' => 'غیرفعال',
            ],
            'is_edit' => $userId !== null,
            'can_assign_protected_roles' => $includeProtected,
        ];
    }

    public function create(int $actorUserId, array $input): array
    {
        if (!$this->canCreate($actorUserId)) {
            return ['ok' => false, 'forbidden' => true, 'errors' => []];
        }

        $validated = $this->validate($input, null, true, $actorUserId);
        if ($validated['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validated['errors'],
                'form' => $validated['form'],
            ];
        }

        $validated['data']['email_verified'] = false;
        $validated['data']['mobile_verified'] = false;

        try {
            $userId = $this->users->create(
                $validated['data'],
                $validated['role_ids']
            );
        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'database' =>
                        'ثبت کاربر در پایگاه داده انجام نشد.',
                ],
                'form' => $validated['form'],
            ];
        }

        return [
            'ok' => true,
            'user_id' => $userId,
            'verification' =>
                $this->requestVerification(
                    $userId,
                    $validated['data'],
                    ['email', 'mobile']
                ),
        ];
    }

    public function update(
        int $actorUserId,
        int $userId,
        array $input
    ): array {
        if (!$this->canUpdate($actorUserId)) {
            return ['ok' => false, 'forbidden' => true, 'errors' => []];
        }

        $existing = $this->users->findForForm(
            $userId
        );

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

        $emailChanged = $this->identityChanged(
            'email',
            $existing['email'] ?? null,
            $validated['data']['email']
        );
        $mobileChanged = $this->identityChanged(
            'mobile',
            $existing['mobile'] ?? null,
            $validated['data']['mobile']
        );

        $validated['data']['email_verified'] =
            !$emailChanged
            && !empty($existing['email_verified_at']);
        $validated['data']['mobile_verified'] =
            !$mobileChanged
            && !empty($existing['mobile_verified_at']);
        $validated['form']['email_verified'] =
            $validated['data']['email_verified'];
        $validated['form']['mobile_verified'] =
            $validated['data']['mobile_verified'];

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
            && $this->users->userHasGlobalRole($userId, 'super_admin');

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
                'errors' => ['database' => 'ذخیره تغییرات کاربر انجام نشد.'],
                'form' => $validated['form'],
            ];
        }

        $changedFields = [];

        if ($emailChanged) {
            $changedFields[] = 'email';
        }

        if ($mobileChanged) {
            $changedFields[] = 'mobile';
        }

        return [
            'ok' => true,
            'user_id' => $userId,
            'verification' =>
                $this->requestVerification(
                    $userId,
                    $validated['data'],
                    $changedFields
                ),
        ];
    }

    private function validate(
        array $input,
        ?int $exceptUserId,
        bool $passwordRequired,
        int $actorUserId
    ): array {
        $personType = trim((string) ($input['person_type'] ?? 'individual'));
        $firstName = $this->limit(trim((string) ($input['first_name'] ?? '')), 100);
        $lastName = $this->limit(trim((string) ($input['last_name'] ?? '')), 100);
        $nationalCode = preg_replace('/\D+/', '', (string) ($input['national_code'] ?? '')) ?: '';
        $fatherName = $this->limit(trim((string) ($input['father_name'] ?? '')), 100);
        $birthDateJalali = trim(
            (string) (
                $input['birth_date_jalali']
                ?? $input['birth_date']
                ?? ''
            )
        );
        $birthDate = $birthDateJalali === ''
            ? null
            : JalaliDateInput::toGregorian(
                $birthDateJalali
            );
        $birthPlace = $this->limit(trim((string) ($input['birth_place'] ?? '')), 150);
        $identityNumber = $this->limit(trim((string) ($input['identity_number'] ?? '')), 50);
        $identitySerial = $this->limit(trim((string) ($input['identity_serial'] ?? '')), 50);
        $usernameRaw = trim((string) ($input['username'] ?? ''));
        $emailRaw = trim((string) ($input['email'] ?? ''));
        $mobileRaw = trim((string) ($input['mobile'] ?? ''));
        $status = trim((string) ($input['status'] ?? 'active'));
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');
        $emailVerified = false;
        $mobileVerified = false;
        $contactEmailLabel = $this->limit(trim((string) ($input['contact_email_label'] ?? 'ایمیل اصلی')), 100);
        $contactMobileLabel = $this->limit(trim((string) ($input['contact_mobile_label'] ?? 'موبایل اصلی')), 100);
        $provinceLocationId = max(0, (int) ($input['province_location_id'] ?? 0));
        $countyLocationId = max(0, (int) ($input['county_location_id'] ?? 0));
        $cityLocationId = max(0, (int) ($input['city_location_id'] ?? 0));
        $addressTypeId = max(0, (int) ($input['address_type_id'] ?? 0));
        $district = $this->limit(trim((string) ($input['district'] ?? '')), 150);
        $postalCode = preg_replace('/\D+/', '', (string) ($input['postal_code'] ?? '')) ?: '';
        $addressLine = $this->limit(trim((string) ($input['address_line'] ?? '')), 500);

        $username = $this->normalizer->username($usernameRaw);
        $email = $emailRaw === '' ? null : $this->normalizer->email($emailRaw);
        $mobile = $mobileRaw === '' ? null : $this->normalizer->mobile($mobileRaw);

        $roleIdsRaw = $input['role_ids'] ?? [];
        if (!is_array($roleIdsRaw)) {
            $roleIdsRaw = [];
        }
        $includeProtected = $this->canAssignProtectedRoles($actorUserId);
        $roleIds = $this->users->roleIdsByIds($roleIdsRaw, $includeProtected);

        $errors = [];

        if (!$this->users->validPersonType($personType)) {
            $errors['person_type'] = 'نوع شخص معتبر نیست.';
        }
        if ($firstName === '') {
            $errors['first_name'] = 'نام الزامی است.';
        }
        if ($lastName === '') {
            $errors['last_name'] = 'نام خانوادگی الزامی است.';
        }
        if (
            $nationalCode !== ''
            && strlen($nationalCode) !== 10
        ) {
            $errors['national_code'] =
                'کد ملی باید ۱۰ رقم باشد.';
        } elseif (
            $nationalCode !== ''
            && $this->users->nationalCodeExists(
                $nationalCode,
                $exceptUserId
            )
        ) {
            $errors['national_code'] =
                'این کد ملی قبلاً ثبت شده است.';
        }

        if (
            $birthDateJalali !== ''
            && $birthDate === null
        ) {
            $errors['birth_date_jalali'] =
                'تاریخ تولد شمسی معتبر نیست. قالب صحیح ۱۴۰۰/۰۱/۰۱ است.';
        }
        if ($username === null) {
            $errors['username'] = 'نام کاربری باید با حرف انگلیسی شروع شود و فقط شامل حروف، عدد و زیرخط باشد.';
        } elseif ($this->users->identityExists('username', $username, $exceptUserId)) {
            $errors['username'] = 'این نام کاربری قبلاً ثبت شده است.';
        }
        if ($emailRaw !== '' && $email === null) {
            $errors['email'] = 'ایمیل معتبر نیست.';
        } elseif ($email !== null && $this->users->identityExists('email', $email, $exceptUserId)) {
            $errors['email'] = 'این ایمیل قبلاً ثبت شده است.';
        }
        if ($mobileRaw !== '' && $mobile === null) {
            $errors['mobile'] = 'شماره موبایل باید با الگوی 09xxxxxxxxx باشد.';
        } elseif ($mobile !== null && $this->users->identityExists('mobile', $mobile, $exceptUserId)) {
            $errors['mobile'] = 'این شماره موبایل قبلاً ثبت شده است.';
        }
        if ($email === null && $mobile === null) {
            $errors['contact'] = 'ثبت حداقل ایمیل یا شماره موبایل الزامی است.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'وضعیت حساب معتبر نیست.';
            $status = 'active';
        }
        if (
            !$this->users->validGeographicSelection(
                $provinceLocationId,
                $countyLocationId,
                $cityLocationId
            )
        ) {
            $errors['geography'] =
                'ترکیب استان، شهرستان و شهر معتبر نیست.';
        }
        if ($addressTypeId > 0 && !$this->users->validAddressTypeId($addressTypeId)) {
            $errors['address_type_id'] = 'نوع نشانی معتبر نیست.';
        }
        if ($postalCode !== '' && strlen($postalCode) !== 10) {
            $errors['postal_code'] = 'کد پستی باید ۱۰ رقم باشد.';
        }
        if ($passwordRequired || $password !== '') {
            if (strlen($password) < 10) {
                $errors['password'] = 'رمز عبور باید حداقل ۱۰ کاراکتر باشد.';
            } elseif ($password !== $confirmation) {
                $errors['password_confirmation'] = 'تکرار رمز عبور یکسان نیست.';
            }
        }

        $fullName = trim($firstName . ' ' . $lastName);
        $form = [
            'id' => $exceptUserId,
            'person_type' => $personType,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'national_code' => $nationalCode,
            'father_name' => $fatherName,
            'birth_date' => $birthDate ?? '',
            'birth_date_jalali' => $birthDateJalali,
            'birth_place' => $birthPlace,
            'identity_number' => $identityNumber,
            'identity_serial' => $identitySerial,
            'username' => $usernameRaw,
            'email' => $emailRaw,
            'mobile' => $mobileRaw,
            'status' => $status,
            'email_verified' => $emailVerified,
            'mobile_verified' => $mobileVerified,
            'contact_email_label' => $contactEmailLabel,
            'contact_mobile_label' => $contactMobileLabel,
            'province_location_id' => $provinceLocationId,
            'county_location_id' => $countyLocationId,
            'city_location_id' => $cityLocationId,
            'address_type_id' => $addressTypeId,
            'district' => $district,
            'postal_code' => $postalCode,
            'address_line' => $addressLine,
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
                'person_type' => $personType,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'national_code' => $nationalCode !== '' ? $nationalCode : null,
                'father_name' => $fatherName !== '' ? $fatherName : null,
                'birth_date' => $birthDate,
                'birth_place' => $birthPlace !== '' ? $birthPlace : null,
                'identity_number' => $identityNumber !== '' ? $identityNumber : null,
                'identity_serial' => $identitySerial !== '' ? $identitySerial : null,
                'username' => $username,
                'username_norm' => $username,
                'email' => $email,
                'email_norm' => $email,
                'mobile' => $mobile,
                'mobile_norm' => $mobile,
                'status' => $status,
                'email_verified' => $emailVerified,
                'mobile_verified' => $mobileVerified,
                'contact_email_label' => $contactEmailLabel,
                'contact_mobile_label' => $contactMobileLabel,
                'province_location_id' => $provinceLocationId > 0 ? $provinceLocationId : null,
                'county_location_id' => $countyLocationId > 0 ? $countyLocationId : null,
                'city_location_id' => $cityLocationId > 0 ? $cityLocationId : null,
                'address_type_id' => $addressTypeId > 0 ? $addressTypeId : null,
                'district' => $district,
                'postal_code' => $postalCode,
                'address_line' => $addressLine,
                'password_hash' => $password === ''
                    ? null
                    : password_hash($password, PASSWORD_DEFAULT),
            ],
        ];
    }

    private function identityChanged(
        string $field,
        mixed $currentValue,
        mixed $newValue
    ): bool {
        $current = trim((string) ($currentValue ?? ''));
        $new = trim((string) ($newValue ?? ''));

        if ($field === 'email') {
            $current = $current === ''
                ? ''
                : (string) (
                    $this->normalizer->email($current)
                    ?? $current
                );
            $new = $new === ''
                ? ''
                : (string) (
                    $this->normalizer->email($new)
                    ?? $new
                );
        } else {
            $current = $current === ''
                ? ''
                : (string) (
                    $this->normalizer->mobile($current)
                    ?? $current
                );
            $new = $new === ''
                ? ''
                : (string) (
                    $this->normalizer->mobile($new)
                    ?? $new
                );
        }

        return $current !== $new;
    }

    private function requestVerification(
        int $userId,
        array $data,
        array $fields
    ): array {
        $result = [];

        foreach ($fields as $field) {
            if (
                !in_array(
                    $field,
                    ['email', 'mobile'],
                    true
                )
                || trim((string) (
                    $data[$field] ?? ''
                )) === ''
            ) {
                continue;
            }

            try {
                $result[$field] =
                    $this->verification->request(
                        $userId,
                        $field
                    );
            } catch (Throwable) {
                $result[$field] = [
                    'ok' => false,
                    'status' => 'delivery_failed',
                ];
            }
        }

        return $result;
    }

    private function canAssignProtectedRoles(int $actorUserId): bool
    {
        return $this->authorization->hasPermission($actorUserId, 'permissions.assign');
    }

    private function mergeOldInput(array $form, array $old): array
    {
        foreach (array_keys($form) as $field) {
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
