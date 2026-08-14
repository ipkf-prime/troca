<?php

namespace App\Services\Automation\Correspondence;

use App\Repositories\ExternalOrganizationDirectoryRepository;
use Throwable;

final class ExternalOrganizationDirectoryService
{
    private const DISPATCH_CHANNELS = [
        'postal',
        'courier',
        'hand_delivery',
        'fax',
        'email',
        'system',
    ];

    public function __construct(
        private ?ExternalOrganizationDirectoryRepository $repository = null
    ) {
        $this->repository ??=
            new ExternalOrganizationDirectoryRepository();
    }

    public function page(
        string $query = '',
        ?string $organizationReference = null
    ): array {
        $organizations =
            $this->repository->organizations(
                trim($query),
                true
            );

        $selected = null;
        $points = [];

        if (
            is_string(
                $organizationReference
            )
            && trim(
                $organizationReference
            ) !== ''
        ) {
            $selected =
                $this->repository
                    ->organization(
                        trim(
                            $organizationReference
                        )
                    );

            if (is_array($selected)) {
                $points =
                    $this->repository
                        ->contactPoints(
                            (int) $selected['id']
                        );

                foreach (
                    $points
                    as &$point
                ) {
                    $point['methods'] =
                        $this->repository
                            ->contactMethods(
                                (int) $point['id']
                            );

                    $point['addresses'] =
                        $this->repository
                            ->addresses(
                                (int) $point['id']
                            );
                }

                unset($point);
            }
        }

        return [
            'ok' => true,
            'organizations' =>
                $organizations,
            'selected_organization' =>
                $selected,
            'contact_points' =>
                $points,
            'contact_types' =>
                $this->repository
                    ->contactTypes(),
            'address_types' =>
                $this->repository
                    ->addressTypes(),
            'dispatch_channels' =>
                self::DISPATCH_CHANNELS,
        ];
    }

    public function saveOrganization(
        array $input
    ): array {
        $reference =
            trim(
                (string) (
                    $input[
                        'public_reference'
                    ]
                    ?? ''
                )
            );

        $data = [
            'title_fa' =>
                trim(
                    (string) (
                        $input['title_fa']
                        ?? ''
                    )
                ),

            'title_en' =>
                $this->nullable(
                    $input['title_en']
                    ?? null
                ),

            'short_title' =>
                $this->nullable(
                    $input['short_title']
                    ?? null
                ),

            'national_id' =>
                $this->nullable(
                    $input['national_id']
                    ?? null
                ),

            'registration_number' =>
                $this->nullable(
                    $input[
                        'registration_number'
                    ]
                    ?? null
                ),

            'website_url' =>
                $this->nullable(
                    $input['website_url']
                    ?? null
                ),

            'notes' =>
                $this->nullable(
                    $input['notes']
                    ?? null
                ),
        ];

        $errors = [];

        if ($data['title_fa'] === '') {
            $errors['title_fa'] =
                'عنوان فارسی سازمان الزامی است.';
        }

        if (
            $data['website_url'] !== null
            && filter_var(
                $data['website_url'],
                FILTER_VALIDATE_URL
            ) === false
        ) {
            $errors['website_url'] =
                'نشانی وب‌سایت معتبر نیست.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        try {
            if ($reference === '') {
                $data['public_reference'] =
                    $this->reference();

                $reference =
                    $this->repository
                        ->createOrganization(
                            $data
                        );
            } else {
                if (
                    $this->repository
                        ->organization(
                            $reference
                        ) === null
                ) {
                    return [
                        'ok' => false,
                        'errors' => [
                            'public_reference' =>
                                'سازمان بیرونی یافت نشد.',
                        ],
                    ];
                }

                $this->repository
                    ->updateOrganization(
                        $reference,
                        $data
                    );
            }

            return [
                'ok' => true,
                'public_reference' =>
                    $reference,
            ];

        } catch (Throwable $exception) {
            return
                $this->failure(
                    $exception
                );
        }
    }

    public function saveContactPoint(
        string $organizationReference,
        array $input
    ): array {
        $organization =
            $this->repository
                ->organization(
                    trim(
                        $organizationReference
                    )
                );

        if (!is_array($organization)) {
            return [
                'ok' => false,
                'errors' => [
                    'organization' =>
                        'سازمان بیرونی یافت نشد.',
                ],
            ];
        }

        $reference =
            trim(
                (string) (
                    $input[
                        'public_reference'
                    ]
                    ?? ''
                )
            );

        $existing =
            $reference !== ''
                ? $this->repository
                    ->contactPoint(
                        $reference
                    )
                : null;

        if (
            $reference !== ''
            && (
                !is_array($existing)
                || (int) $existing[
                    'external_organization_id'
                ] !==
                   (int) $organization['id']
            )
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'public_reference' =>
                        'مقصد مکاتباتی معتبر نیست.',
                ],
            ];
        }

        $pointKind =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'point_kind_code'
                        ]
                        ?? 'secretariat'
                    )
                )
            );

        $title =
            trim(
                (string) (
                    $input['title']
                    ?? ''
                )
            );

        $preferred =
            $this->nullable(
                $input[
                    'preferred_dispatch_channel_code'
                ]
                ?? null
            );

        $isPrimary =
            $this->boolean(
                $input[
                    'is_primary'
                ]
                ?? null
            );

        $errors = [];

        if ($title === '') {
            $errors['title'] =
                'عنوان مقصد مکاتباتی الزامی است.';
        }

        if (
            $pointKind === ''
            || preg_match(
                '/^[a-z0-9][a-z0-9_-]{0,79}$/',
                $pointKind
            ) !== 1
        ) {
            $errors['point_kind_code'] =
                'نوع مقصد مکاتباتی معتبر نیست.';
        }

        if (
            $preferred !== null
            && !in_array(
                $preferred,
                self::DISPATCH_CHANNELS,
                true
            )
        ) {
            $errors[
                'preferred_dispatch_channel_code'
            ] =
                'روش ارسال ترجیحی معتبر نیست.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $data = [
            'code' =>
                is_array($existing)
                    ? (string) (
                        $existing['code']
                        ?? ''
                    )
                    : $this->pointCode(
                        $pointKind
                    ),

            'title' =>
                $title,

            'point_kind_code' =>
                $pointKind,

            'contact_person_name' =>
                $this->nullable(
                    $input[
                        'contact_person_name'
                    ]
                    ?? null
                ),

            'contact_person_title' =>
                $this->nullable(
                    $input[
                        'contact_person_title'
                    ]
                    ?? null
                ),

            'business_hours' =>
                $this->nullable(
                    $input[
                        'business_hours'
                    ]
                    ?? null
                ),

            'preferred_dispatch_channel_code' =>
                $preferred,

            'is_primary' =>
                $isPrimary,
        ];

        try {
            $reference =
                $this->repository
                    ->transaction(
                        function () use (
                            $organization,
                            $existing,
                            $reference,
                            $data,
                            $isPrimary
                        ): string {
                            $savedReference =
                                $reference;

                            if (!is_array($existing)) {
                                $data[
                                    'public_reference'
                                ] =
                                    $this->reference();

                                $savedReference =
                                    $this->repository
                                        ->createContactPoint(
                                            (int) $organization[
                                                'id'
                                            ],
                                            $data
                                        );

                            } else {
                                $this->repository
                                    ->updateContactPoint(
                                        $reference,
                                        $data
                                    );
                            }

                            $savedPoint =
                                $this->repository
                                    ->contactPoint(
                                        $savedReference
                                    );

                            if (!is_array($savedPoint)) {
                                throw new \RuntimeException(
                                    'Saved contact point unavailable.'
                                );
                            }

                            $pointId =
                                (int) $savedPoint['id'];

                            if ($isPrimary === 1) {
                                $this->repository
                                    ->clearPrimaryContactPoints(
                                        (int) $organization[
                                            'id'
                                        ],
                                        $pointId
                                    );
                            }

                            return
                                $savedReference;
                        }
                    );

            return [
                'ok' => true,
                'public_reference' =>
                    $reference,
            ];

        } catch (Throwable $exception) {
            return
                $this->failure(
                    $exception
                );
        }
    }

    public function saveContactMethod(
        string $organizationReference,
        string $contactPointReference,
        array $input
    ): array {
        $scope =
            $this->pointScope(
                $organizationReference,
                $contactPointReference
            );

        if (($scope['ok'] ?? false) !== true) {
            return $scope;
        }

        $point =
            $scope['point'];

        $reference =
            trim(
                (string) (
                    $input[
                        'public_reference'
                    ]
                    ?? ''
                )
            );

        $typeCode =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'contact_type_code'
                        ]
                        ?? ''
                    )
                )
            );

        $type =
            $this->repository
                ->contactType(
                    $typeCode
                );

        $value =
            trim(
                (string) (
                    $input['value']
                    ?? ''
                )
            );

        $areaCode = null;
        $extension = null;

        if ($typeCode === 'phone') {
            $areaCode =
                $this->phoneAreaCode(
                    $input[
                        'area_code'
                    ]
                    ?? null
                );

            $extension =
                $this->phoneExtension(
                    $input[
                        'extension'
                    ]
                    ?? null
                );

            $value =
                $this->phoneNumber(
                    $value
                );
        }

        $errors = [];

        if (!is_array($type)) {
            $errors['contact_type_code'] =
                'نوع راه ارتباطی معتبر نیست.';
        }

        if ($typeCode === 'extension') {
            $errors['contact_type_code'] =
                'داخلی باید همراه همان تلفن ثابت ثبت شود.';
        }

        if ($value === '') {
            $errors['value'] =
                $typeCode === 'phone'
                    ? 'شماره تلفن الزامی است.'
                    : 'مقدار راه ارتباطی الزامی است.';
        }

        $supportsDispatch =
            $this->boolean(
                $input[
                    'supports_dispatch'
                ]
                ?? null
            );

        if (
            $supportsDispatch === 1
            && !in_array(
                $typeCode,
                [
                    'fax',
                    'email',
                    'system',
                ],
                true
            )
        ) {
            $errors['supports_dispatch'] =
                'این نوع راه ارتباطی برای ارسال مستقیم معتبر نیست.';
        }

        if (
            $typeCode === 'email'
            && $value !== ''
            && filter_var(
                $value,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $errors['value'] =
                'نشانی ایمیل معتبر نیست.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $normalizedValue =
            $typeCode === 'phone'
                ? $this->normalizePhone(
                    $areaCode,
                    $value
                )
                : $this->normalizeContact(
                    $typeCode,
                    $value
                );

        $data = [
            'contact_type_id' =>
                (int) $type['id'],

            'value' =>
                $value,

            'normalized_value' =>
                $normalizedValue,

            'area_code' =>
                $areaCode,

            'extension' =>
                $extension,

            'label' =>
                $this->nullable(
                    $input['label']
                    ?? null
                ),

            'is_primary' =>
                $this->boolean(
                    $input['is_primary']
                    ?? null
                ),

            'is_verified' =>
                $this->boolean(
                    $input['is_verified']
                    ?? null
                ),

            'supports_dispatch' =>
                $supportsDispatch,

            'supports_followup' =>
                $this->boolean(
                    $input[
                        'supports_followup'
                    ]
                    ?? null
                ),

            'sort_order' =>
                max(
                    0,
                    (int) $this->asciiDigits(
                        (string) (
                            $input[
                                'sort_order'
                            ]
                            ?? 0
                        )
                    )
                ),
        ];

        try {
            if ($reference === '') {
                $data['public_reference'] =
                    $this->reference();

                $reference =
                    $this->repository
                        ->createContactMethod(
                            (int) $point['id'],
                            $data
                        );
            } else {
                $existing =
                    $this->repository
                        ->contactMethod(
                            $reference
                        );

                if (
                    !is_array($existing)
                    || (int) $existing[
                        'contact_point_id'
                    ] !==
                       (int) $point['id']
                ) {
                    return [
                        'ok' => false,
                        'errors' => [
                            'public_reference' =>
                                'راه ارتباطی معتبر نیست.',
                        ],
                    ];
                }

                $this->repository
                    ->updateContactMethod(
                        $reference,
                        $data
                    );
            }

            return [
                'ok' => true,
                'public_reference' =>
                    $reference,
            ];

        } catch (\Throwable $exception) {
            return
                $this->failure(
                    $exception
                );
        }
    }

    public function saveAddress(
        string $organizationReference,
        string $contactPointReference,
        array $input
    ): array {
        $scope =
            $this->pointScope(
                $organizationReference,
                $contactPointReference
            );

        if (($scope['ok'] ?? false) !== true) {
            return $scope;
        }

        $point =
            $scope['point'];

        $reference =
            trim(
                (string) (
                    $input[
                        'public_reference'
                    ]
                    ?? ''
                )
            );

        $addressTypeCode =
            strtolower(
                trim(
                    (string) (
                        $input[
                            'address_type_code'
                        ]
                        ?? 'correspondence'
                    )
                )
            );

        $addressType =
            $this->repository
                ->addressType(
                    $addressTypeCode
                );

        $addressLine =
            trim(
                (string) (
                    $input[
                        'address_line'
                    ]
                    ?? ''
                )
            );

        $errors = [];

        if (!is_array($addressType)) {
            $errors['address_type_code'] =
                'نوع نشانی معتبر نیست.';
        }

        if ($addressLine === '') {
            $errors['address_line'] =
                'نشانی الزامی است.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $geographicLocationId =
            max(
                0,
                (int) (
                    $input[
                        'geographic_location_id'
                    ]
                    ?? 0
                )
            );

        $data = [
            'address_type_id' =>
                (int) $addressType['id'],

            'geographic_location_id' =>
                $geographicLocationId > 0
                    ? $geographicLocationId
                    : null,

            'district' =>
                $this->nullable(
                    $input['district']
                    ?? null
                ),

            'address_line' =>
                $addressLine,

            'postal_code' =>
                $this->nullable(
                    $input['postal_code']
                    ?? null
                ),

            'is_primary' =>
                $this->boolean(
                    $input['is_primary']
                    ?? null
                ),

            'supports_dispatch' =>
                $this->boolean(
                    $input[
                        'supports_dispatch'
                    ]
                    ?? null
                ),
        ];

        try {
            if ($reference === '') {
                $data['public_reference'] =
                    $this->reference();

                $reference =
                    $this->repository
                        ->createAddress(
                            (int) $point['id'],
                            $data
                        );
            } else {
                $existing =
                    $this->repository
                        ->address(
                            $reference
                        );

                if (
                    !is_array($existing)
                    || (int) $existing[
                        'contact_point_id'
                    ] !==
                       (int) $point['id']
                ) {
                    return [
                        'ok' => false,
                        'errors' => [
                            'public_reference' =>
                                'نشانی معتبر نیست.',
                        ],
                    ];
                }

                $this->repository
                    ->updateAddress(
                        $reference,
                        $data
                    );
            }

            return [
                'ok' => true,
                'public_reference' =>
                    $reference,
            ];

        } catch (Throwable $exception) {
            return
                $this->failure(
                    $exception
                );
        }
    }

    public function deactivateOrganization(
        string $organizationReference
    ): array {
        $reference =
            trim($organizationReference);

        if (
            $reference === ''
            || $this->repository
                ->organization($reference) === null
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'organization' =>
                        'سازمان بیرونی یافت نشد.',
                ],
            ];
        }

        try {
            $this->repository
                ->deactivateOrganization(
                    $reference
                );

            return [
                'ok' => true,
            ];

        } catch (Throwable $exception) {
            return
                $this->failure(
                    $exception
                );
        }
    }

    public function deactivateContactPoint(
        string $organizationReference,
        string $contactPointReference
    ): array {
        $scope =
            $this->pointScope(
                $organizationReference,
                $contactPointReference
            );

        if (($scope['ok'] ?? false) !== true) {
            return $scope;
        }

        try {
            $this->repository
                ->deactivateContactPoint(
                    trim(
                        $contactPointReference
                    )
                );

            return [
                'ok' => true,
            ];

        } catch (Throwable $exception) {
            return
                $this->failure(
                    $exception
                );
        }
    }

    public function deactivateContactMethod(
        string $organizationReference,
        string $contactPointReference,
        string $methodReference
    ): array {
        $scope =
            $this->pointScope(
                $organizationReference,
                $contactPointReference
            );

        if (($scope['ok'] ?? false) !== true) {
            return $scope;
        }

        $method =
            $this->repository
                ->contactMethod(
                    trim(
                        $methodReference
                    )
                );

        if (
            !is_array($method)
            || (int) $method[
                'contact_point_id'
            ] !==
               (int) $scope['point']['id']
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'method' =>
                        'راه ارتباطی معتبر نیست.',
                ],
            ];
        }

        try {
            $this->repository
                ->deactivateContactMethod(
                    trim(
                        $methodReference
                    )
                );

            return [
                'ok' => true,
            ];

        } catch (Throwable $exception) {
            return
                $this->failure(
                    $exception
                );
        }
    }

    public function deactivateAddress(
        string $organizationReference,
        string $contactPointReference,
        string $addressReference
    ): array {
        $scope =
            $this->pointScope(
                $organizationReference,
                $contactPointReference
            );

        if (($scope['ok'] ?? false) !== true) {
            return $scope;
        }

        $address =
            $this->repository
                ->address(
                    trim(
                        $addressReference
                    )
                );

        if (
            !is_array($address)
            || (int) $address[
                'contact_point_id'
            ] !==
               (int) $scope['point']['id']
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'address' =>
                        'نشانی معتبر نیست.',
                ],
            ];
        }

        try {
            $this->repository
                ->deactivateAddress(
                    trim(
                        $addressReference
                    )
                );

            return [
                'ok' => true,
            ];

        } catch (Throwable $exception) {
            return
                $this->failure(
                    $exception
                );
        }
    }

    private function syncDestinationContactField(
        int $contactPointId,
        string $typeCode,
        ?string $value,
        bool $supportsDispatch,
        bool $supportsFollowup,
        int $sortOrder
    ): void {
        $type =
            $this->repository
                ->contactType(
                    $typeCode
                );

        if (!is_array($type)) {
            if ($value === null) {
                return;
            }

            throw new \RuntimeException(
                "Contact type unavailable: {$typeCode}"
            );
        }

        $existing =
            $this->repository
                ->contactMethodByType(
                    $contactPointId,
                    (int) $type['id']
                );

        if ($value === null) {
            if (
                is_array($existing)
                && (
                    $existing['status']
                    ?? ''
                ) === 'active'
            ) {
                $this->repository
                    ->deactivateContactMethod(
                        (string) $existing[
                            'public_reference'
                        ]
                    );
            }

            return;
        }

        $data = [
            'contact_type_id' =>
                (int) $type['id'],

            'value' =>
                $value,

            'normalized_value' =>
                $this->normalizeContact(
                    $typeCode,
                    $value
                ),

            'label' =>
                null,

            'is_primary' =>
                1,

            'is_verified' =>
                0,

            'supports_dispatch' =>
                $supportsDispatch
                    ? 1
                    : 0,

            'supports_followup' =>
                $supportsFollowup
                    ? 1
                    : 0,

            'sort_order' =>
                $sortOrder,
        ];

        if (is_array($existing)) {
            $this->repository
                ->updateContactMethod(
                    (string) $existing[
                        'public_reference'
                    ],
                    $data
                );

            return;
        }

        $data['public_reference'] =
            $this->reference();

        $this->repository
            ->createContactMethod(
                $contactPointId,
                $data
            );
    }

    private function syncDestinationPostalAddress(
        int $contactPointId,
        ?string $district,
        ?string $addressLine,
        ?string $postalCode
    ): void {
        $existing =
            $this->repository
                ->preferredAddressForPoint(
                    $contactPointId
                );

        if ($addressLine === null) {
            if (
                is_array($existing)
                && (
                    $existing['status']
                    ?? ''
                ) === 'active'
            ) {
                $this->repository
                    ->deactivateAddress(
                        (string) $existing[
                            'public_reference'
                        ]
                    );
            }

            return;
        }

        $addressType =
            $this->repository
                ->addressType(
                    'correspondence'
                );

        if (!is_array($addressType)) {
            throw new \RuntimeException(
                'Correspondence address type unavailable.'
            );
        }

        $data = [
            'address_type_id' =>
                (int) $addressType['id'],

            'geographic_location_id' =>
                null,

            'district' =>
                $district,

            'address_line' =>
                $addressLine,

            'postal_code' =>
                $postalCode,

            'is_primary' =>
                1,

            'supports_dispatch' =>
                1,
        ];

        if (is_array($existing)) {
            $this->repository
                ->updateAddress(
                    (string) $existing[
                        'public_reference'
                    ],
                    $data
                );

            return;
        }

        $data['public_reference'] =
            $this->reference();

        $this->repository
            ->createAddress(
                $contactPointId,
                $data
            );
    }

    private function pointCode(
        string $pointKind
    ): string {
        $prefix =
            preg_replace(
                '/[^a-z0-9]+/',
                '-',
                strtolower(
                    trim($pointKind)
                )
            );

        $prefix =
            trim(
                (string) $prefix,
                '-'
            );

        if ($prefix === '') {
            $prefix = 'destination';
        }

        $suffix =
            substr(
                str_replace(
                    '-',
                    '',
                    $this->reference()
                ),
                0,
                10
            );

        return
            substr(
                $prefix,
                0,
                70
            )
            . '-'
            . $suffix;
    }

    private function pointScope(
        string $organizationReference,
        string $contactPointReference
    ): array {
        $organization =
            $this->repository
                ->organization(
                    trim(
                        $organizationReference
                    )
                );

        $point =
            $this->repository
                ->contactPoint(
                    trim(
                        $contactPointReference
                    )
                );

        if (
            !is_array($organization)
            || !is_array($point)
            || (int) $point[
                'external_organization_id'
            ] !==
               (int) $organization['id']
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'scope' =>
                        'سازمان یا نقطه مکاتباتی معتبر نیست.',
                ],
            ];
        }

        return [
            'ok' => true,
            'organization' =>
                $organization,
            'point' =>
                $point,
        ];
    }

    private function nullable(
        mixed $value
    ): ?string {
        $value =
            trim(
                (string) $value
            );

        return
            $value === ''
                ? null
                : $value;
    }

    private function boolean(
        mixed $value
    ): int {
        return
            in_array(
                strtolower(
                    trim(
                        (string) $value
                    )
                ),
                [
                    '1',
                    'true',
                    'yes',
                    'on',
                ],
                true
            )
                ? 1
                : 0;
    }

    private function phoneAreaCode(
        mixed $value
    ): ?string {
        $digits =
            preg_replace(
                '/\D/u',
                '',
                $this->asciiDigits(
                    trim(
                        (string) $value
                    )
                )
            );

        if (!is_string($digits)) {
            return null;
        }

        $digits =
            ltrim(
                $digits,
                '0'
            );

        return
            $digits === ''
                ? null
                : $digits;
    }

    private function phoneNumber(
        mixed $value
    ): string {
        $digits =
            preg_replace(
                '/\D/u',
                '',
                $this->asciiDigits(
                    trim(
                        (string) $value
                    )
                )
            );

        return
            is_string($digits)
                ? $digits
                : '';
    }

    private function phoneExtension(
        mixed $value
    ): ?string {
        $digits =
            $this->phoneNumber(
                $value
            );

        return
            $digits === ''
                ? null
                : $digits;
    }

    private function normalizePhone(
        ?string $areaCode,
        string $number
    ): string {
        return
            $areaCode === null
                ? $number
                : '0'
                    . $areaCode
                    . $number;
    }

    private function asciiDigits(
        string $value
    ): string {
        return strtr(
            $value,
            [
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',

                '٠' => '0',
                '١' => '1',
                '٢' => '2',
                '٣' => '3',
                '٤' => '4',
                '٥' => '5',
                '٦' => '6',
                '٧' => '7',
                '٨' => '8',
                '٩' => '9',
            ]
        );
    }

    private function normalizeContact(
        string $typeCode,
        string $value
    ): string {
        if ($typeCode === 'email') {
            return
                strtolower(
                    trim($value)
                );
        }

        if (
            in_array(
                $typeCode,
                [
                    'phone',
                    'mobile',
                    'fax',
                ],
                true
            )
        ) {
            $normalized =
                preg_replace(
                    '/[^\d+]/u',
                    '',
                    $this->asciiDigits(
                        $value
                    )
                );

            return
                is_string($normalized)
                && $normalized !== ''
                    ? $normalized
                    : trim($value);
        }

        return trim($value);
    }

    private function reference(): string
    {
        $bytes =
            random_bytes(16);

        $bytes[6] =
            chr(
                (
                    ord($bytes[6])
                    & 0x0f
                )
                | 0x40
            );

        $bytes[8] =
            chr(
                (
                    ord($bytes[8])
                    & 0x3f
                )
                | 0x80
            );

        $hex =
            bin2hex($bytes);

        return
            substr($hex, 0, 8)
            . '-'
            . substr($hex, 8, 4)
            . '-'
            . substr($hex, 12, 4)
            . '-'
            . substr($hex, 16, 4)
            . '-'
            . substr($hex, 20, 12);
    }

    private function failure(
        Throwable $exception
    ): array {
        return [
            'ok' => false,
            'errors' => [
                'system' =>
                    'ذخیره اطلاعات سازمان بیرونی انجام نشد.',
            ],
            'exception_class' =>
                get_class($exception),
        ];
    }
}
