<?php

namespace App\Services;

use App\Repositories\AdminUserManagementRepository;
use App\Support\JalaliDateInput;
use Throwable;

class SelfProfileService extends BaseService
{
    public function __construct(
        private ?AdminUserManagementRepository $users = null
    ) {
        $this->users ??=
            new AdminUserManagementRepository();
    }

    public function form(
        int $userId,
        array $old = []
    ): array {
        $existing = $this->users->findForForm($userId);

        if ($existing === null) {
            return [
                'ok' => false,
                'not_found' => true,
            ];
        }

        $form = [
            'first_name' => (string) (
                $existing['first_name'] ?? ''
            ),
            'last_name' => (string) (
                $existing['last_name'] ?? ''
            ),
            'person_type' => (string) (
                $existing['person_type']
                ?? 'individual'
            ),
            'national_code' => (string) (
                $existing['national_code'] ?? ''
            ),
            'father_name' => (string) (
                $existing['father_name'] ?? ''
            ),
            'birth_date_jalali' =>
                JalaliDateInput::fromGregorian(
                    $existing['birth_date'] ?? ''
                ),
            'birth_place' => (string) (
                $existing['birth_place'] ?? ''
            ),
            'identity_number' => (string) (
                $existing['identity_number'] ?? ''
            ),
            'identity_serial' => (string) (
                $existing['identity_serial'] ?? ''
            ),
            'province_id' => (int) (
                $existing['province_id'] ?? 0
            ),
            'county_id' => (int) (
                $existing['county_id'] ?? 0
            ),
            'city_id' => (int) (
                $existing['city_id'] ?? 0
            ),
            'address_type_id' => (int) (
                $existing['address_type_id'] ?? 0
            ),
            'district' => (string) (
                $existing['district'] ?? ''
            ),
            'postal_code' => (string) (
                $existing['postal_code'] ?? ''
            ),
            'address_line' => (string) (
                $existing['address_line'] ?? ''
            ),
        ];

        foreach (array_keys($form) as $field) {
            if (array_key_exists($field, $old)) {
                $form[$field] = $old[$field];
            }
        }

        $options = $this->users->formOptions();

        return [
            'ok' => true,
            'form' => $form,
            'person_types' => $options['person_types'],
            'provinces' => $options['provinces'],
            'counties' => $options['counties'],
            'cities' => $options['cities'],
            'address_types' => $options['address_types'],
        ];
    }

    public function update(
        int $userId,
        array $input
    ): array {
        $firstName = $this->limit(
            trim((string) (
                $input['first_name'] ?? ''
            )),
            100
        );
        $lastName = $this->limit(
            trim((string) (
                $input['last_name'] ?? ''
            )),
            100
        );
        $nationalCode = preg_replace(
            '/\D+/',
            '',
            (string) (
                $input['national_code'] ?? ''
            )
        ) ?: '';
        $fatherName = $this->limit(
            trim((string) (
                $input['father_name'] ?? ''
            )),
            100
        );
        $birthDateJalali = trim((string) (
            $input['birth_date_jalali'] ?? ''
        ));
        $birthDate = $birthDateJalali === ''
            ? null
            : JalaliDateInput::toGregorian(
                $birthDateJalali
            );
        $birthPlace = $this->limit(
            trim((string) (
                $input['birth_place'] ?? ''
            )),
            150
        );
        $identityNumber = $this->limit(
            trim((string) (
                $input['identity_number'] ?? ''
            )),
            50
        );
        $identitySerial = $this->limit(
            trim((string) (
                $input['identity_serial'] ?? ''
            )),
            50
        );
        $provinceId = max(
            0,
            (int) ($input['province_id'] ?? 0)
        );
        $countyId = max(
            0,
            (int) ($input['county_id'] ?? 0)
        );
        $cityId = max(
            0,
            (int) ($input['city_id'] ?? 0)
        );
        $addressTypeId = max(
            0,
            (int) (
                $input['address_type_id'] ?? 0
            )
        );
        $district = $this->limit(
            trim((string) (
                $input['district'] ?? ''
            )),
            150
        );
        $postalCode = preg_replace(
            '/\D+/',
            '',
            (string) (
                $input['postal_code'] ?? ''
            )
        ) ?: '';
        $addressLine = $this->limit(
            trim((string) (
                $input['address_line'] ?? ''
            )),
            500
        );

        $errors = [];

        if ($firstName === '') {
            $errors['first_name'] = 'نام الزامی است.';
        }

        if ($lastName === '') {
            $errors['last_name'] =
                'نام خانوادگی الزامی است.';
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
                $userId
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
                'تاریخ تولد شمسی معتبر نیست.';
        }

        if (
            $provinceId > 0
            && !$this->users->validProvinceId(
                $provinceId
            )
        ) {
            $errors['province_id'] =
                'استان انتخاب‌شده معتبر نیست.';
        }

        if (
            $countyId > 0
            && !$this->users->validCountyId(
                $countyId
            )
        ) {
            $errors['county_id'] =
                'شهرستان انتخاب‌شده معتبر نیست.';
        }

        if (
            $cityId > 0
            && !$this->users->validCityId($cityId)
        ) {
            $errors['city_id'] =
                'شهر انتخاب‌شده معتبر نیست.';
        }

        if (
            $addressTypeId > 0
            && !$this->users->validAddressTypeId(
                $addressTypeId
            )
        ) {
            $errors['address_type_id'] =
                'نوع نشانی معتبر نیست.';
        }

        if (
            $postalCode !== ''
            && strlen($postalCode) !== 10
        ) {
            $errors['postal_code'] =
                'کد پستی باید ۱۰ رقم باشد.';
        }

        $form = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'national_code' => $nationalCode,
            'father_name' => $fatherName,
            'birth_date_jalali' =>
                $birthDateJalali,
            'birth_place' => $birthPlace,
            'identity_number' => $identityNumber,
            'identity_serial' => $identitySerial,
            'province_id' => $provinceId,
            'county_id' => $countyId,
            'city_id' => $cityId,
            'address_type_id' => $addressTypeId,
            'district' => $district,
            'postal_code' => $postalCode,
            'address_line' => $addressLine,
        ];

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'form' => $form,
            ];
        }

        try {
            $this->users->updateOwnProfile(
                $userId,
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'national_code' =>
                        $nationalCode !== ''
                            ? $nationalCode
                            : null,
                    'father_name' =>
                        $fatherName !== ''
                            ? $fatherName
                            : null,
                    'birth_date' => $birthDate,
                    'birth_place' =>
                        $birthPlace !== ''
                            ? $birthPlace
                            : null,
                    'identity_number' =>
                        $identityNumber !== ''
                            ? $identityNumber
                            : null,
                    'identity_serial' =>
                        $identitySerial !== ''
                            ? $identitySerial
                            : null,
                    'province_id' =>
                        $provinceId > 0
                            ? $provinceId
                            : null,
                    'county_id' =>
                        $countyId > 0
                            ? $countyId
                            : null,
                    'city_id' =>
                        $cityId > 0
                            ? $cityId
                            : null,
                    'address_type_id' =>
                        $addressTypeId > 0
                            ? $addressTypeId
                            : null,
                    'district' => $district,
                    'postal_code' => $postalCode,
                    'address_line' => $addressLine,
                ]
            );
        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'database' =>
                        'ذخیره اطلاعات شخصی انجام نشد.',
                ],
                'form' => $form,
            ];
        }

        return ['ok' => true];
    }

    private function limit(
        string $value,
        int $length
    ): string {
        return function_exists('mb_substr')
            ? mb_substr(
                $value,
                0,
                $length,
                'UTF-8'
            )
            : substr($value, 0, $length);
    }
}
