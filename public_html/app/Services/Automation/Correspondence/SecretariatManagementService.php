<?php

namespace App\Services\Automation\Correspondence;

use App\Support\PersianDate;
use IPKF\Database\Database;
use IPKF\Support\Clock;
use PDO;
use RuntimeException;
use Throwable;

final class SecretariatManagementService
{
    private PDO $core;

    private AutomationOperationalRuntime $runtime;

    private EnterpriseAutomationContextService $enterpriseContext;

    public function __construct(
        ?AutomationOperationalRuntime $runtime = null,
        ?EnterpriseAutomationContextService $enterpriseContext = null,
        ?PDO $core = null
    ) {
        $this->runtime =
            $runtime
            ?? new AutomationOperationalRuntime();

        $this->enterpriseContext =
            $enterpriseContext
            ?? new EnterpriseAutomationContextService();

        $this->core =
            $core
            ?? Database::connect();
    }

    public function page(
        int $userId
    ): array {
        $actor =
            $this->enterpriseContext
                ->forUser(
                    $userId
                );

        $organizations =
            $this->organizationOptions(
                $actor
            );

        $organizationMap = [];

        foreach (
            $organizations
            as $organization
        ) {
            $organizationMap[
                (int) $organization['id']
            ] = $organization;
        }

        $orgUnits =
            $this->orgUnitOptions(
                $actor
            );

        $orgUnitMap = [];

        foreach (
            $orgUnits
            as $unit
        ) {
            $orgUnitMap[
                (int) $unit['id']
            ] = $unit;
        }

        $desks =
            $this->desks(
                $actor,
                $organizationMap,
                $orgUnitMap
            );

        $deskMap = [];

        foreach ($desks as $desk) {
            $deskMap[
                (int) $desk['id']
            ] = $desk;
        }

        $periods =
            $this->periods(
                $actor,
                $organizationMap
            );

        $periodMap = [];

        foreach ($periods as $period) {
            $periodMap[
                (int) $period['id']
            ] = $period;
        }

        $sequences =
            $this->sequences(
                $actor,
                $organizationMap,
                $deskMap,
                $periodMap
            );

        $sequenceMap = [];

        foreach ($sequences as $sequence) {
            $sequenceMap[
                (int) $sequence['id']
            ] = $sequence;
        }

        $books =
            $this->books(
                $actor,
                $organizationMap,
                $deskMap,
                $periodMap,
                $sequenceMap
            );

        return [
            'ok' => true,

            'actor' =>
                $actor,

            'organizations' =>
                $organizations,

            'org_units' =>
                $orgUnits,

            'desks' =>
                $desks,

            'periods' =>
                $periods,

            'sequences' =>
                $sequences,

            'books' =>
                $books,

            'can_manage_root_scope' =>
                (int) $actor[
                    'organization_id'
                ] ===
                (int) $actor[
                    'root_organization_id'
                ],
        ];
    }

    public function createDesk(
        array $input,
        int $userId
    ): array {
        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

            $errors = [];

            $code =
                $this->code(
                    $input['code'] ?? ''
                );

            $titleFa =
                $this->text(
                    $input['title_fa'] ?? '',
                    255
                );

            $titleEn =
                $this->nullableText(
                    $input['title_en'] ?? '',
                    255
                );

            $kind =
                $this->code(
                    $input[
                        'desk_kind_code'
                    ] ?? 'organization'
                );

            if ($code === '') {
                $errors['code'] =
                    'کد دبیرخانه الزامی است و فقط باید شامل حروف انگلیسی، عدد، خط تیره یا زیرخط باشد.';
            }

            if ($titleFa === '') {
                $errors['title_fa'] =
                    'عنوان فارسی دبیرخانه الزامی است.';
            }

            if (
                !in_array(
                    $kind,
                    [
                        'organization',
                        'shared',
                    ],
                    true
                )
            ) {
                $errors['desk_kind_code'] =
                    'نوع دبیرخانه معتبر نیست.';
            }

            $managingOrganizationId =
                $this->positiveInt(
                    $input[
                        'managing_organization_id'
                    ] ?? null
                );

            if (
                $managingOrganizationId === null
                || !$this->organizationAllowed(
                    $managingOrganizationId,
                    $actor
                )
            ) {
                $errors[
                    'managing_organization_id'
                ] =
                    'سازمان متولی دبیرخانه خارج از دامنه مجاز است.';
            }

            $orgUnitId =
                $this->positiveInt(
                    $input[
                        'org_unit_id'
                    ] ?? null
                );

            if (
                $orgUnitId !== null
                && (
                    $managingOrganizationId === null
                    || !$this->orgUnitBelongsTo(
                        $orgUnitId,
                        $managingOrganizationId
                    )
                )
            ) {
                $errors['org_unit_id'] =
                    'واحد سازمانی انتخاب‌شده متعلق به سازمان متولی نیست.';
            }

            $supportsIncoming =
                $this->booleanInput(
                    $input[
                        'supports_incoming'
                    ] ?? null
                );

            $supportsOutgoing =
                $this->booleanInput(
                    $input[
                        'supports_outgoing'
                    ] ?? null
                );

            $supportsInternal =
                $this->booleanInput(
                    $input[
                        'supports_internal'
                    ] ?? null
                );

            if (
                !$supportsIncoming
                && !$supportsOutgoing
                && !$supportsInternal
            ) {
                $errors['directions'] =
                    'دبیرخانه باید حداقل یکی از وارده، صادره یا داخلی را پشتیبانی کند.';
            }

            $servedOrganizationIds =
                $this->idList(
                    $input[
                        'served_organization_ids'
                    ] ?? []
                );

            if (
                $managingOrganizationId !== null
                && !in_array(
                    $managingOrganizationId,
                    $servedOrganizationIds,
                    true
                )
            ) {
                $servedOrganizationIds[] =
                    $managingOrganizationId;
            }

            $servedOrganizationIds =
                array_values(
                    array_unique(
                        $servedOrganizationIds
                    )
                );

            sort(
                $servedOrganizationIds,
                SORT_NUMERIC
            );

            if (
                $kind === 'organization'
                && $managingOrganizationId !== null
            ) {
                $servedOrganizationIds = [
                    $managingOrganizationId,
                ];
            }

            foreach (
                $servedOrganizationIds
                as $organizationId
            ) {
                if (
                    !$this->organizationAllowed(
                        $organizationId,
                        $actor
                    )
                ) {
                    $errors[
                        'served_organization_ids'
                    ] =
                        'یکی از سازمان‌های تحت خدمت خارج از دامنه مجاز است.';

                    break;
                }
            }

            if ($kind === 'shared') {

                if (
                    (int) $actor[
                        'organization_id'
                    ] !==
                    (int) $actor[
                        'root_organization_id'
                    ]
                ) {
                    $errors[
                        'desk_kind_code'
                    ] =
                        'تعریف دبیرخانه مشترک فقط از جایگاه سازمان ریشه/هلدینگ مجاز است.';
                }

                if (
                    count(
                        $servedOrganizationIds
                    ) < 2
                ) {
                    $errors[
                        'served_organization_ids'
                    ] =
                        'دبیرخانه مشترک باید حداقل به دو سازمان خدمت ارائه کند.';
                }
            }

            if ($errors !== []) {
                return [
                    'ok' => false,
                    'errors' => $errors,
                ];
            }

            $pdo =
                $this->runtime
                    ->connection();

            if (
                $this->deskCodeExists(
                    $pdo,
                    (int) $actor[
                        'root_organization_id'
                    ],
                    (int) $managingOrganizationId,
                    $code
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'code' =>
                            'این کد دبیرخانه در سازمان متولی قبلاً استفاده شده است.',
                    ],
                ];
            }

            $organizationReferences =
                $this->organizationReferences(
                    $servedOrganizationIds
                );

            $now =
                Clock::databaseTimestamp();

            $pdo->beginTransaction();

            try {
                $statement =
                    $pdo->prepare("
                        INSERT INTO secretariat_desks (
                            public_reference,
                            root_organization_id,
                            managing_organization_id,
                            org_unit_id,
                            code,
                            title_fa,
                            title_en,
                            desk_kind_code,
                            supports_incoming,
                            supports_outgoing,
                            supports_internal,
                            allow_cross_organization,
                            status,
                            created_by_user_id,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            'active',
                            ?,
                            ?,
                            ?
                        )
                    ");

                $publicReference =
                    $this->publicReference(
                        'SEC'
                    );

                $statement->execute([
                    $publicReference,

                    (int) $actor[
                        'root_organization_id'
                    ],

                    (int) $managingOrganizationId,

                    $orgUnitId,

                    $code,
                    $titleFa,
                    $titleEn,
                    $kind,

                    $supportsIncoming
                        ? 1
                        : 0,

                    $supportsOutgoing
                        ? 1
                        : 0,

                    $supportsInternal
                        ? 1
                        : 0,

                    $kind === 'shared'
                        ? 1
                        : 0,

                    $userId,
                    $now,
                    $now,
                ]);

                $deskId =
                    (int) $pdo
                        ->lastInsertId();

                $link =
                    $pdo->prepare("
                        INSERT INTO secretariat_desk_organizations (
                            secretariat_desk_id,
                            root_organization_id,
                            organization_id,
                            organization_public_reference,
                            relation_code,
                            is_primary,
                            can_register_incoming,
                            can_register_outgoing,
                            can_register_internal,
                            status,
                            valid_from,
                            valid_until,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            ?,
                            'service',
                            ?,
                            ?,
                            ?,
                            ?,
                            'active',
                            NULL,
                            NULL,
                            ?,
                            ?
                        )
                    ");

                foreach (
                    $servedOrganizationIds
                    as $organizationId
                ) {
                    $link->execute([
                        $deskId,

                        (int) $actor[
                            'root_organization_id'
                        ],

                        $organizationId,

                        $organizationReferences[
                            $organizationId
                        ] ?? null,

                        $organizationId ===
                            (int) $managingOrganizationId
                                ? 1
                                : 0,

                        $supportsIncoming
                            ? 1
                            : 0,

                        $supportsOutgoing
                            ? 1
                            : 0,

                        $supportsInternal
                            ? 1
                            : 0,

                        $now,
                        $now,
                    ]);
                }

                $pdo->commit();

                return [
                    'ok' => true,
                    'public_reference' =>
                        $publicReference,
                ];

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $exception;
            }

        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'runtime' =>
                        'ثبت دبیرخانه انجام نشد.',
                ],
            ];
        }
    }

    public function createPeriod(
        array $input,
        int $userId
    ): array {
        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

            $errors = [];

            $scope =
                $this->code(
                    $input[
                        'scope'
                    ] ?? 'organization'
                );

            if (
                !in_array(
                    $scope,
                    [
                        'organization',
                        'root',
                    ],
                    true
                )
            ) {
                $errors['scope'] =
                    'دامنه دوره ثبت معتبر نیست.';
            }

            $organizationId = null;

            if ($scope === 'root') {

                if (
                    (int) $actor[
                        'organization_id'
                    ] !==
                    (int) $actor[
                        'root_organization_id'
                    ]
                ) {
                    $errors['scope'] =
                        'دوره مشترک هلدینگ فقط از جایگاه سازمان ریشه قابل تعریف است.';
                }

            } else {
                $organizationId =
                    $this->positiveInt(
                        $input[
                            'organization_id'
                        ] ?? null
                    );

                if (
                    $organizationId === null
                    || !$this->organizationAllowed(
                        $organizationId,
                        $actor
                    )
                ) {
                    $errors[
                        'organization_id'
                    ] =
                        'سازمان دوره ثبت معتبر نیست.';
                }
            }

            $code =
                $this->code(
                    $input['code'] ?? ''
                );

            $title =
                $this->text(
                    $input['title'] ?? '',
                    255
                );

            if ($code === '') {
                $errors['code'] =
                    'کد دوره ثبت الزامی است.';
            }

            if ($title === '') {
                $errors['title'] =
                    'عنوان دوره ثبت الزامی است.';
            }

            $startsOn =
                $this->dateInput(
                    $input[
                        'starts_on'
                    ] ?? null
                );

            $endsOn =
                $this->dateInput(
                    $input[
                        'ends_on'
                    ] ?? null
                );

            if ($startsOn === null) {
                $errors['starts_on'] =
                    'تاریخ شروع معتبر نیست.';
            }

            if ($endsOn === null) {
                $errors['ends_on'] =
                    'تاریخ پایان معتبر نیست.';
            }

            if (
                $startsOn !== null
                && $endsOn !== null
                && $startsOn > $endsOn
            ) {
                $errors['ends_on'] =
                    'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.';
            }

            if ($errors !== []) {
                return [
                    'ok' => false,
                    'errors' => $errors,
                ];
            }

            $pdo =
                $this->runtime
                    ->connection();

            if (
                $this->periodCodeExists(
                    $pdo,
                    (int) $actor[
                        'root_organization_id'
                    ],
                    $organizationId,
                    $code
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'code' =>
                            'این کد دوره ثبت در دامنه انتخاب‌شده قبلاً استفاده شده است.',
                    ],
                ];
            }

            $now =
                Clock::databaseTimestamp();

            $publicReference =
                $this->publicReference(
                    'RPER'
                );

            $statement =
                $pdo->prepare("
                    INSERT INTO registry_periods (
                        public_reference,
                        root_organization_id,
                        organization_id,
                        code,
                        title,
                        starts_on,
                        ends_on,
                        status,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'active',
                        ?,
                        ?
                    )
                ");

            $statement->execute([
                $publicReference,

                (int) $actor[
                    'root_organization_id'
                ],

                $organizationId,

                $code,
                $title,
                $startsOn,
                $endsOn,
                $now,
                $now,
            ]);

            return [
                'ok' => true,
                'public_reference' =>
                    $publicReference,
            ];

        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'runtime' =>
                        'ثبت دوره شماره‌گذاری انجام نشد.',
                ],
            ];
        }
    }

    public function createSequence(
        array $input,
        int $userId
    ): array {
        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

            $errors = [];

            $deskId =
                $this->positiveInt(
                    $input[
                        'secretariat_desk_id'
                    ] ?? null
                );

            $periodId =
                $this->positiveInt(
                    $input[
                        'registry_period_id'
                    ] ?? null
                );

            $desk =
                $deskId !== null
                    ? $this->deskForActor(
                        $deskId,
                        $actor
                    )
                    : null;

            if ($desk === null) {
                $errors[
                    'secretariat_desk_id'
                ] =
                    'دبیرخانه انتخاب‌شده در دامنه مجاز نیست.';
            }

            $period =
                $periodId !== null
                    ? $this->periodForActor(
                        $periodId,
                        $actor
                    )
                    : null;

            if ($period === null) {
                $errors[
                    'registry_period_id'
                ] =
                    'دوره ثبت انتخاب‌شده در دامنه مجاز نیست.';
            }

            $scope =
                $this->code(
                    $input[
                        'scope'
                    ] ?? 'organization'
                );

            if (
                !in_array(
                    $scope,
                    [
                        'organization',
                        'shared',
                    ],
                    true
                )
            ) {
                $errors['scope'] =
                    'دامنه منبع شماره معتبر نیست.';
            }

            $organizationId = null;

            if ($scope === 'shared') {

                if (
                    (int) $actor[
                        'organization_id'
                    ] !==
                    (int) $actor[
                        'root_organization_id'
                    ]
                ) {
                    $errors['scope'] =
                        'منبع شماره مشترک بین‌سازمانی فقط از جایگاه سازمان ریشه قابل تعریف است.';
                }

                if (
                    $desk !== null
                    && (int) (
                        $desk[
                            'allow_cross_organization'
                        ] ?? 0
                    ) !== 1
                ) {
                    $errors['scope'] =
                        'منبع شماره مشترک فقط برای دبیرخانه مشترک مجاز است.';
                }

                if (
                    $period !== null
                    && $period[
                        'organization_id'
                    ] !== null
                ) {
                    $errors[
                        'registry_period_id'
                    ] =
                        'منبع شماره مشترک باید از دوره ثبت مشترک هلدینگ استفاده کند.';
                }

            } else {
                $organizationId =
                    $this->positiveInt(
                        $input[
                            'organization_id'
                        ] ?? null
                    );

                if (
                    $organizationId === null
                    || !$this->organizationAllowed(
                        $organizationId,
                        $actor
                    )
                ) {
                    $errors[
                        'organization_id'
                    ] =
                        'سازمان منبع شماره معتبر نیست.';
                }

                if (
                    $desk !== null
                    && $organizationId !== null
                    && !$this->deskServesOrganization(
                        (int) $desk['id'],
                        $organizationId
                    )
                ) {
                    $errors[
                        'organization_id'
                    ] =
                        'دبیرخانه انتخاب‌شده به این سازمان خدمت ارائه نمی‌کند.';
                }

                if (
                    $period !== null
                    && $period[
                        'organization_id'
                    ] !== null
                    && (int) $period[
                        'organization_id'
                    ] !== $organizationId
                ) {
                    $errors[
                        'registry_period_id'
                    ] =
                        'دوره ثبت سازمانی با سازمان منبع شماره یکسان نیست.';
                }
            }

            $code =
                $this->code(
                    $input['code'] ?? ''
                );

            $title =
                $this->text(
                    $input['title'] ?? '',
                    255
                );

            $prefix =
                $this->nullableText(
                    $input['prefix'] ?? '',
                    50
                );

            $suffix =
                $this->nullableText(
                    $input['suffix'] ?? '',
                    50
                );

            $formatPattern =
                $this->text(
                    $input[
                        'format_pattern'
                    ] ??
                    '{prefix}{sequence}{suffix}',
                    255
                );

            $padding =
                $this->positiveInt(
                    $input[
                        'number_padding'
                    ] ?? 5
                );

            $nextNumber =
                $this->positiveInt(
                    $input[
                        'next_sequence_number'
                    ] ?? 1
                );

            if ($code === '') {
                $errors['code'] =
                    'کد منبع شماره الزامی است.';
            }

            if ($title === '') {
                $errors['title'] =
                    'عنوان منبع شماره الزامی است.';
            }

            if (
                $formatPattern === ''
                || !str_contains(
                    $formatPattern,
                    '{sequence}'
                )
                || preg_match(
                    '/\{(?!prefix\}|sequence\}|suffix\})[^}]+\}/',
                    $formatPattern
                ) === 1
            ) {
                $errors[
                    'format_pattern'
                ] =
                    'الگوی شماره باید شامل {sequence} باشد و فقط از {prefix}، {sequence} و {suffix} استفاده کند.';
            }

            if (
                $padding === null
                || $padding < 1
                || $padding > 20
            ) {
                $errors[
                    'number_padding'
                ] =
                    'تعداد ارقام منبع شماره باید بین ۱ تا ۲۰ باشد.';
            }

            if ($nextNumber === null) {
                $errors[
                    'next_sequence_number'
                ] =
                    'شماره شروع باید بزرگ‌تر از صفر باشد.';
            }

            if (
                $desk !== null
                && $period !== null
                && (int) $desk[
                    'root_organization_id'
                ] !==
                    (int) $period[
                        'root_organization_id'
                    ]
            ) {
                $errors[
                    'registry_period_id'
                ] =
                    'دوره ثبت و دبیرخانه در یک دامنه هلدینگ قرار ندارند.';
            }

            if ($errors !== []) {
                return [
                    'ok' => false,
                    'errors' => $errors,
                ];
            }

            $pdo =
                $this->runtime
                    ->connection();

            if (
                $this->sequenceCodeExists(
                    $pdo,
                    (int) $actor[
                        'root_organization_id'
                    ],
                    $organizationId,
                    (int) $deskId,
                    (int) $periodId,
                    $code
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'code' =>
                            'این کد منبع شماره در دامنه انتخاب‌شده قبلاً استفاده شده است.',
                    ],
                ];
            }

            $now =
                Clock::databaseTimestamp();

            $publicReference =
                $this->publicReference(
                    'RSEQ'
                );

            $statement =
                $pdo->prepare("
                    INSERT INTO registry_number_sequences (
                        public_reference,
                        root_organization_id,
                        organization_id,
                        secretariat_desk_id,
                        registry_period_id,
                        code,
                        title,
                        prefix,
                        suffix,
                        format_pattern,
                        number_padding,
                        next_sequence_number,
                        status,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'active',
                        ?,
                        ?
                    )
                ");

            $statement->execute([
                $publicReference,

                (int) $actor[
                    'root_organization_id'
                ],

                $organizationId,
                (int) $deskId,
                (int) $periodId,

                $code,
                $title,
                $prefix,
                $suffix,
                $formatPattern,
                (int) $padding,
                (int) $nextNumber,

                $now,
                $now,
            ]);

            return [
                'ok' => true,
                'public_reference' =>
                    $publicReference,
            ];

        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'runtime' =>
                        'ثبت منبع شماره انجام نشد.',
                ],
            ];
        }
    }

    public function sequenceEditForm(
        string $publicReference,
        int $userId
    ): ?array {
        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

            $sequence =
                $this->sequenceForActorByReference(
                    $publicReference,
                    $actor
                );

            if ($sequence === null) {
                return null;
            }

            $usage =
                $this->sequenceUsage(
                    (int) $sequence['id']
                );

            $locked =
                $usage['books'] > 0
                || $usage['reservations'] > 0;

            return [
                'edit_sequence_reference' =>
                    (string) $sequence[
                        'public_reference'
                    ],

                'secretariat_desk_id' =>
                    (int) $sequence[
                        'secretariat_desk_id'
                    ],

                'registry_period_id' =>
                    (int) $sequence[
                        'registry_period_id'
                    ],

                'scope' =>
                    $sequence[
                        'organization_id'
                    ] === null
                        ? 'shared'
                        : 'organization',

                'organization_id' =>
                    $sequence[
                        'organization_id'
                    ] !== null
                        ? (int) $sequence[
                            'organization_id'
                        ]
                        : '',

                'code' =>
                    (string) (
                        $sequence['code']
                        ?? ''
                    ),

                'title' =>
                    (string) (
                        $sequence['title']
                        ?? ''
                    ),

                'prefix' =>
                    (string) (
                        $sequence['prefix']
                        ?? ''
                    ),

                'suffix' =>
                    (string) (
                        $sequence['suffix']
                        ?? ''
                    ),

                'format_pattern' =>
                    (string) (
                        $sequence[
                            'format_pattern'
                        ]
                        ?? '{prefix}{sequence}{suffix}'
                    ),

                'number_padding' =>
                    (int) (
                        $sequence[
                            'number_padding'
                        ]
                        ?? 5
                    ),

                'next_sequence_number' =>
                    (int) (
                        $sequence[
                            'next_sequence_number'
                        ]
                        ?? 1
                    ),

                'sequence_locked' =>
                    $locked ? '1' : '0',

                'sequence_book_count' =>
                    $usage['books'],

                'sequence_reservation_count' =>
                    $usage['reservations'],
            ];

        } catch (Throwable) {
            return null;
        }
    }

    public function updateSequence(
        string $publicReference,
        array $input,
        int $userId
    ): array {
        try {
            $publicReference =
                trim(
                    $publicReference
                );

            if ($publicReference === '') {
                return [
                    'ok' => false,
                    'errors' => [
                        'sequence' =>
                            'منبع شماره موردنظر پیدا نشد.',
                    ],
                ];
            }

            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

            $current =
                $this->sequenceForActorByReference(
                    $publicReference,
                    $actor
                );

            if ($current === null) {
                return [
                    'ok' => false,
                    'errors' => [
                        'sequence' =>
                            'منبع شماره موردنظر در دامنه مجاز شما قرار ندارد.',
                    ],
                ];
            }

            $usage =
                $this->sequenceUsage(
                    (int) $current['id']
                );

            $locked =
                $usage['books'] > 0
                || $usage['reservations'] > 0;

            $code =
                $this->code(
                    $input['code']
                    ?? $current['code']
                    ?? ''
                );

            $title =
                $this->text(
                    $input['title']
                    ?? $current['title']
                    ?? '',
                    255
                );

            $prefix =
                $this->nullableText(
                    $input['prefix']
                    ?? $current['prefix']
                    ?? '',
                    50
                );

            $suffix =
                $this->nullableText(
                    $input['suffix']
                    ?? $current['suffix']
                    ?? '',
                    50
                );

            $formatPattern =
                $this->text(
                    $input[
                        'format_pattern'
                    ]
                    ?? $current[
                        'format_pattern'
                    ]
                    ?? '{prefix}{sequence}{suffix}',
                    255
                );

            $padding =
                $this->positiveInt(
                    $input[
                        'number_padding'
                    ]
                    ?? $current[
                        'number_padding'
                    ]
                    ?? 5
                );

            $nextNumber =
                $this->positiveInt(
                    $input[
                        'next_sequence_number'
                    ]
                    ?? $current[
                        'next_sequence_number'
                    ]
                    ?? 1
                );

            $errors = [];

            if ($code === '') {
                $errors['code'] =
                    'کد منبع شماره الزامی است.';
            }

            if ($title === '') {
                $errors['title'] =
                    'عنوان منبع شماره الزامی است.';
            }

            if (
                $formatPattern === ''
                || !str_contains(
                    $formatPattern,
                    '{sequence}'
                )
                || preg_match(
                    '/\{(?!prefix\}|sequence\}|suffix\})[^}]+\}/',
                    $formatPattern
                ) === 1
            ) {
                $errors[
                    'format_pattern'
                ] =
                    'الگوی شماره باید شامل {sequence} باشد و فقط از {prefix}، {sequence} و {suffix} استفاده کند.';
            }

            if (
                $padding === null
                || $padding < 1
                || $padding > 20
            ) {
                $errors[
                    'number_padding'
                ] =
                    'تعداد ارقام منبع شماره باید بین ۱ تا ۲۰ باشد.';
            }

            if ($nextNumber === null) {
                $errors[
                    'next_sequence_number'
                ] =
                    'شماره بعدی باید بزرگ‌تر از صفر باشد.';
            }

            $currentPrefix =
                $current['prefix'] !== null
                    ? (string) $current[
                        'prefix'
                    ]
                    : null;

            $currentSuffix =
                $current['suffix'] !== null
                    ? (string) $current[
                        'suffix'
                    ]
                    : null;

            $sensitiveChanged =
                $code !==
                    (string) $current['code']
                || $prefix !== $currentPrefix
                || $suffix !== $currentSuffix
                || $formatPattern !==
                    (string) $current[
                        'format_pattern'
                    ]
                || (
                    $padding !== null
                    && $padding !==
                        (int) $current[
                            'number_padding'
                        ]
                )
                || (
                    $nextNumber !== null
                    && $nextNumber !==
                        (int) $current[
                            'next_sequence_number'
                        ]
                );

            if (
                $locked
                && $sensitiveChanged
            ) {
                $errors['locked'] =
                    'این منبع شماره قبلاً استفاده شده است؛ مشخصات شماره‌گذاری آن قفل شده و فقط عنوان قابل ویرایش است.';
            }

            if ($errors !== []) {
                return [
                    'ok' => false,
                    'errors' => $errors,
                ];
            }

            $pdo =
                $this->runtime
                    ->connection();

            if (
                !$locked
                && $code !==
                    (string) $current['code']
                && $this->sequenceCodeExistsExcluding(
                    $pdo,
                    (int) $current[
                        'root_organization_id'
                    ],
                    $current[
                        'organization_id'
                    ] !== null
                        ? (int) $current[
                            'organization_id'
                        ]
                        : null,
                    (int) $current[
                        'secretariat_desk_id'
                    ],
                    (int) $current[
                        'registry_period_id'
                    ],
                    $code,
                    (int) $current['id']
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'code' =>
                            'این کد منبع شماره در دامنه انتخاب‌شده قبلاً استفاده شده است.',
                    ],
                ];
            }

            $now =
                Clock::databaseTimestamp();

            if ($locked) {
                $statement =
                    $pdo->prepare("
                        UPDATE registry_number_sequences
                        SET
                            title = ?,
                            updated_at = ?
                        WHERE id = ?
                          AND public_reference = ?
                    ");

                $statement->execute([
                    $title,
                    $now,
                    (int) $current['id'],
                    $publicReference,
                ]);

            } else {
                $statement =
                    $pdo->prepare("
                        UPDATE registry_number_sequences
                        SET
                            code = ?,
                            title = ?,
                            prefix = ?,
                            suffix = ?,
                            format_pattern = ?,
                            number_padding = ?,
                            next_sequence_number = ?,
                            updated_at = ?
                        WHERE id = ?
                          AND public_reference = ?
                    ");

                $statement->execute([
                    $code,
                    $title,
                    $prefix,
                    $suffix,
                    $formatPattern,
                    (int) $padding,
                    (int) $nextNumber,
                    $now,
                    (int) $current['id'],
                    $publicReference,
                ]);
            }

            return [
                'ok' => true,
                'public_reference' =>
                    $publicReference,
                'locked' =>
                    $locked,
            ];

        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'runtime' =>
                        'ویرایش منبع شماره انجام نشد.',
                ],
            ];
        }
    }

    public function createBook(
        array $input,
        int $userId
    ): array {
        try {
            $actor =
                $this->enterpriseContext
                    ->forUser(
                        $userId
                    );

            $errors = [];

            $organizationId =
                $this->positiveInt(
                    $input[
                        'organization_id'
                    ] ?? null
                );

            if (
                $organizationId === null
                || !$this->organizationAllowed(
                    $organizationId,
                    $actor
                )
            ) {
                $errors[
                    'organization_id'
                ] =
                    'سازمان دفتر ثبت معتبر نیست.';
            }

            $deskId =
                $this->positiveInt(
                    $input[
                        'secretariat_desk_id'
                    ] ?? null
                );

            $desk =
                $deskId !== null
                    ? $this->deskForActor(
                        $deskId,
                        $actor
                    )
                    : null;

            if ($desk === null) {
                $errors[
                    'secretariat_desk_id'
                ] =
                    'دبیرخانه انتخاب‌شده در دامنه مجاز نیست.';
            }

            if (
                $desk !== null
                && $organizationId !== null
                && !$this->deskServesOrganization(
                    (int) $desk['id'],
                    $organizationId
                )
            ) {
                $errors[
                    'organization_id'
                ] =
                    'دبیرخانه انتخاب‌شده به این سازمان خدمت ارائه نمی‌کند.';
            }

            $periodId =
                $this->positiveInt(
                    $input[
                        'registry_period_id'
                    ] ?? null
                );

            $period =
                $periodId !== null
                    ? $this->periodForActor(
                        $periodId,
                        $actor
                    )
                    : null;

            if ($period === null) {
                $errors[
                    'registry_period_id'
                ] =
                    'دوره ثبت انتخاب‌شده معتبر نیست.';
            }

            if (
                $period !== null
                && $organizationId !== null
                && $period[
                    'organization_id'
                ] !== null
                && (int) $period[
                    'organization_id'
                ] !== $organizationId
            ) {
                $errors[
                    'registry_period_id'
                ] =
                    'دوره ثبت سازمانی متعلق به سازمان دیگری است.';
            }

            $sequenceId =
                $this->positiveInt(
                    $input[
                        'number_sequence_id'
                    ] ?? null
                );

            $sequence =
                $sequenceId !== null
                    ? $this->sequenceForActor(
                        $sequenceId,
                        $actor
                    )
                    : null;

            if ($sequence === null) {
                $errors[
                    'number_sequence_id'
                ] =
                    'منبع شماره انتخاب‌شده معتبر نیست.';
            }

            if (
                $sequence !== null
                && $deskId !== null
                && (int) $sequence[
                    'secretariat_desk_id'
                ] !== $deskId
            ) {
                $errors[
                    'number_sequence_id'
                ] =
                    'منبع شماره انتخاب‌شده متعلق به این دبیرخانه نیست.';
            }

            if (
                $sequence !== null
                && $periodId !== null
                && (int) $sequence[
                    'registry_period_id'
                ] !== $periodId
            ) {
                $errors[
                    'number_sequence_id'
                ] =
                    'منبع شماره انتخاب‌شده متعلق به دوره ثبت دیگری است.';
            }

            if (
                $sequence !== null
                && $organizationId !== null
            ) {
                if (
                    $sequence[
                        'organization_id'
                    ] !== null
                    && (int) $sequence[
                        'organization_id'
                    ] !== $organizationId
                ) {
                    $errors[
                        'number_sequence_id'
                    ] =
                        'منبع شماره سازمانی متعلق به سازمان دیگری است.';
                }

                if (
                    $sequence[
                        'organization_id'
                    ] === null
                    && (
                        $desk === null
                        || (int) (
                            $desk[
                                'allow_cross_organization'
                            ] ?? 0
                        ) !== 1
                    )
                ) {
                    $errors[
                        'number_sequence_id'
                    ] =
                        'استفاده از منبع شماره مشترک برای این دبیرخانه مجاز نیست.';
                }
            }

            $scopeCode =
                $this->code(
                    $input[
                        'scope_code'
                    ] ?? ''
                );

            if (
                !in_array(
                    $scopeCode,
                    [
                        'incoming',
                        'outgoing',
                        'internal',
                        'general',
                    ],
                    true
                )
            ) {
                $errors[
                    'scope_code'
                ] =
                    'نوع دفتر ثبت معتبر نیست.';
            }

            if (
                $desk !== null
                && $organizationId !== null
                && in_array(
                    $scopeCode,
                    [
                        'incoming',
                        'outgoing',
                        'internal',
                    ],
                    true
                )
                && !$this->deskCanRegister(
                    (int) $desk['id'],
                    $organizationId,
                    $scopeCode
                )
            ) {
                $errors[
                    'scope_code'
                ] =
                    'این دبیرخانه برای نوع دفتر انتخاب‌شده مجوز ثبت ندارد.';
            }

            $strategy =
                $this->code(
                    $input[
                        'numbering_strategy_code'
                    ] ?? 'dedicated'
                );

            if (
                !in_array(
                    $strategy,
                    [
                        'dedicated',
                        'shared',
                    ],
                    true
                )
            ) {
                $errors[
                    'numbering_strategy_code'
                ] =
                    'راهبرد شماره‌گذاری معتبر نیست.';
            }

            $code =
                $this->code(
                    $input['code'] ?? ''
                );

            $title =
                $this->text(
                    $input['title'] ?? '',
                    255
                );

            if ($code === '') {
                $errors['code'] =
                    'کد دفتر ثبت الزامی است.';
            }

            if ($title === '') {
                $errors['title'] =
                    'عنوان دفتر ثبت الزامی است.';
            }

            if ($errors !== []) {
                return [
                    'ok' => false,
                    'errors' => $errors,
                ];
            }

            $pdo =
                $this->runtime
                    ->connection();

            if (
                !$this->sequenceUsageAllows(
                    $pdo,
                    (int) $sequenceId,
                    $strategy
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'number_sequence_id' =>
                            'راهبرد استفاده از Sequence با دفترهای فعال موجود سازگار نیست. Sequence اختصاصی قابل اشتراک نیست و Sequence مشترک فقط میان دفترهای مشترک قابل استفاده است.',
                    ],
                ];
            }

            if (
                $this->bookCodeExists(
                    $pdo,
                    (int) $actor[
                        'root_organization_id'
                    ],
                    (int) $organizationId,
                    (int) $deskId,
                    (int) $periodId,
                    $code
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'code' =>
                            'این کد دفتر ثبت در دامنه انتخاب‌شده قبلاً استفاده شده است.',
                    ],
                ];
            }

            $organizationReference =
                $this->organizationReference(
                    (int) $organizationId
                );

            $now =
                Clock::databaseTimestamp();

            $statement =
                $pdo->prepare("
                    INSERT INTO registry_books (
                        root_organization_id,
                        organization_id,
                        organization_public_reference,
                        fiscal_year_id,
                        org_unit_id,
                        secretariat_desk_id,
                        registry_period_id,
                        number_sequence_id,
                        numbering_strategy_code,
                        scope_code,
                        code,
                        title,
                        prefix,
                        suffix,
                        next_sequence_number,
                        number_padding,
                        status,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        NULL,
                        NULL,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'active',
                        ?,
                        ?
                    )
                ");

            $statement->execute([
                (int) $actor[
                    'root_organization_id'
                ],

                (int) $organizationId,

                $organizationReference,

                (int) $deskId,
                (int) $periodId,
                (int) $sequenceId,

                $strategy,
                $scopeCode,

                $code,
                $title,

                $sequence['prefix']
                    ?? null,

                $sequence['suffix']
                    ?? null,

                (int) (
                    $sequence[
                        'next_sequence_number'
                    ] ?? 1
                ),

                (int) (
                    $sequence[
                        'number_padding'
                    ] ?? 5
                ),

                $now,
                $now,
            ]);

            return [
                'ok' => true,
            ];

        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'runtime' =>
                        'ثبت دفتر شماره انجام نشد.',
                ],
            ];
        }
    }

    private function desks(
        array $actor,
        array $organizationMap,
        array $orgUnitMap
    ): array {
        $pdo =
            $this->runtime
                ->connection();

        [$in, $params] =
            $this->inClause(
                $actor[
                    'accessible_organization_ids'
                ] ?? []
            );

        if ($in === '') {
            return [];
        }

        $statement =
            $pdo->prepare("
                SELECT DISTINCT d.*

                FROM secretariat_desks d

                INNER JOIN
                    secretariat_desk_organizations dso
                    ON dso.secretariat_desk_id =
                        d.id

                WHERE d.root_organization_id = ?
                  AND d.status = 'active'
                  AND dso.status = 'active'

                  AND dso.organization_id
                        IN ({$in})

                  AND (
                        dso.valid_from IS NULL
                        OR dso.valid_from
                            <= UTC_TIMESTAMP()
                  )

                  AND (
                        dso.valid_until IS NULL
                        OR dso.valid_until
                            >= UTC_TIMESTAMP()
                  )

                ORDER BY
                    d.title_fa,
                    d.id
            ");

        $statement->execute(
            array_merge(
                [
                    (int) $actor[
                        'root_organization_id'
                    ],
                ],
                $params
            )
        );

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $allowedOrganizationIds =
            $this->allowedOrganizationIds(
                $actor
            );

        foreach ($rows as &$row) {

            $row[
                'managing_organization_title'
            ] =
                $organizationMap[
                    (int) $row[
                        'managing_organization_id'
                    ]
                ]['title'] ?? '—';

            $row['org_unit_title'] =
                isset($row['org_unit_id'])
                && $row['org_unit_id'] !== null
                    ? (
                        $orgUnitMap[
                            (int) $row[
                                'org_unit_id'
                            ]
                        ]['title'] ?? '—'
                    )
                    : '—';

            $row[
                'served_organization_ids'
            ] =
                $this->servedOrganizationIds(
                    (int) $row['id'],
                    $allowedOrganizationIds
                );

            $row[
                'served_organization_titles'
            ] = [];

            foreach (
                $row[
                    'served_organization_ids'
                ]
                as $organizationId
            ) {
                $row[
                    'served_organization_titles'
                ][] =
                    $organizationMap[
                        $organizationId
                    ]['title'] ??
                    ('#' . $organizationId);
            }
        }

        unset($row);

        return $rows;
    }

    private function periods(
        array $actor,
        array $organizationMap
    ): array {
        $pdo =
            $this->runtime
                ->connection();

        $ids =
            $this->allowedOrganizationIds(
                $actor
            );

        if ($ids === []) {
            return [];
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $pdo->prepare("
                SELECT *

                FROM registry_periods

                WHERE root_organization_id = ?

                  AND (
                        organization_id IS NULL
                        OR organization_id
                            IN ({$in})
                  )

                ORDER BY
                    starts_on DESC,
                    id DESC
            ");

        $statement->execute(
            array_merge(
                [
                    (int) $actor[
                        'root_organization_id'
                    ],
                ],
                $params
            )
        );

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        foreach ($rows as &$row) {

            $organizationId =
                isset(
                    $row[
                        'organization_id'
                    ]
                )
                && $row[
                    'organization_id'
                ] !== null
                    ? (int) $row[
                        'organization_id'
                    ]
                    : null;

            $row[
                'organization_title'
            ] =
                $organizationId === null
                    ? 'مشترک هلدینگ'
                    : (
                        $organizationMap[
                            $organizationId
                        ]['title'] ??
                        ('#' . $organizationId)
                    );

            $row['starts_on_fa'] =
                PersianDate::fromGregorianDate(
                    $row['starts_on'] ?? null
                );

            $row['ends_on_fa'] =
                PersianDate::fromGregorianDate(
                    $row['ends_on'] ?? null
                );
        }

        unset($row);

        return $rows;
    }

    private function sequences(
        array $actor,
        array $organizationMap,
        array $deskMap,
        array $periodMap
    ): array {
        $pdo =
            $this->runtime
                ->connection();

        $ids =
            $this->allowedOrganizationIds(
                $actor
            );

        if ($ids === []) {
            return [];
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $pdo->prepare("
                SELECT s.*

                FROM registry_number_sequences s

                INNER JOIN secretariat_desks d
                    ON d.id =
                        s.secretariat_desk_id

                INNER JOIN
                    secretariat_desk_organizations dso
                    ON dso.secretariat_desk_id =
                        d.id

                WHERE s.root_organization_id = ?
                  AND s.status = 'active'
                  AND d.status = 'active'
                  AND dso.status = 'active'

                  AND dso.organization_id
                        IN ({$in})

                  AND (
                        dso.valid_from IS NULL
                        OR dso.valid_from
                            <= UTC_TIMESTAMP()
                  )

                  AND (
                        dso.valid_until IS NULL
                        OR dso.valid_until
                            >= UTC_TIMESTAMP()
                  )

                  AND (
                        s.organization_id IS NULL
                        OR s.organization_id
                            IN ({$in})
                  )

                GROUP BY s.id

                ORDER BY
                    s.title,
                    s.id
            ");

        $statement->execute(
            array_merge(
                [
                    (int) $actor[
                        'root_organization_id'
                    ],
                ],
                $params,
                $params
            )
        );

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        foreach ($rows as &$row) {

            $organizationId =
                $row[
                    'organization_id'
                ] !== null
                    ? (int) $row[
                        'organization_id'
                    ]
                    : null;

            $row[
                'organization_title'
            ] =
                $organizationId === null
                    ? 'مشترک'
                    : (
                        $organizationMap[
                            $organizationId
                        ]['title'] ??
                        ('#' . $organizationId)
                    );

            $row['desk_title'] =
                $deskMap[
                    (int) $row[
                        'secretariat_desk_id'
                    ]
                ]['title_fa'] ?? '—';

            $row['period_title'] =
                $periodMap[
                    (int) $row[
                        'registry_period_id'
                    ]
                ]['title'] ?? '—';
        }

        unset($row);

        return $rows;
    }

    private function books(
        array $actor,
        array $organizationMap,
        array $deskMap,
        array $periodMap,
        array $sequenceMap
    ): array {
        $pdo =
            $this->runtime
                ->connection();

        $ids =
            $this->allowedOrganizationIds(
                $actor
            );

        if ($ids === []) {
            return [];
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $pdo->prepare("
                SELECT *

                FROM registry_books

                WHERE root_organization_id = ?
                  AND organization_id
                        IN ({$in})

                ORDER BY
                    title,
                    id
            ");

        $statement->execute(
            array_merge(
                [
                    (int) $actor[
                        'root_organization_id'
                    ],
                ],
                $params
            )
        );

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        foreach ($rows as &$row) {

            $row[
                'organization_title'
            ] =
                $organizationMap[
                    (int) $row[
                        'organization_id'
                    ]
                ]['title'] ?? '—';

            $row['desk_title'] =
                isset(
                    $row[
                        'secretariat_desk_id'
                    ]
                )
                && $row[
                    'secretariat_desk_id'
                ] !== null
                    ? (
                        $deskMap[
                            (int) $row[
                                'secretariat_desk_id'
                            ]
                        ]['title_fa'] ?? '—'
                    )
                    : '—';

            $row['period_title'] =
                isset(
                    $row[
                        'registry_period_id'
                    ]
                )
                && $row[
                    'registry_period_id'
                ] !== null
                    ? (
                        $periodMap[
                            (int) $row[
                                'registry_period_id'
                            ]
                        ]['title'] ?? '—'
                    )
                    : '—';

            $row['sequence_title'] =
                isset(
                    $row[
                        'number_sequence_id'
                    ]
                )
                && $row[
                    'number_sequence_id'
                ] !== null
                    ? (
                        $sequenceMap[
                            (int) $row[
                                'number_sequence_id'
                            ]
                        ]['title'] ?? '—'
                    )
                    : '—';
        }

        unset($row);

        return $rows;
    }

    private function organizationOptions(
        array $actor
    ): array {
        $ids =
            $this->allowedOrganizationIds(
                $actor
            );

        if ($ids === []) {
            return [];
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $this->core->prepare("
                SELECT
                    id,
                    public_reference,
                    COALESCE(
                        NULLIF(title_fa, ''),
                        title
                    ) AS title

                FROM organizations

                WHERE id IN ({$in})
                  AND is_active = 1

                ORDER BY
                    depth,
                    path,
                    id
            ");

        $statement->execute(
            $params
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    private function orgUnitOptions(
        array $actor
    ): array {
        $ids =
            $this->allowedOrganizationIds(
                $actor
            );

        if ($ids === []) {
            return [];
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $this->core->prepare("
                SELECT
                    id,
                    organization_id,
                    public_reference,
                    COALESCE(
                        NULLIF(title_fa, ''),
                        title
                    ) AS title

                FROM org_units

                WHERE organization_id
                        IN ({$in})

                  AND status = 'active'
                  AND deleted_at IS NULL

                ORDER BY
                    organization_id,
                    depth,
                    path,
                    id
            ");

        $statement->execute(
            $params
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    private function deskForActor(
        int $deskId,
        array $actor
    ): ?array {
        $ids =
            $this->allowedOrganizationIds(
                $actor
            );

        if ($ids === []) {
            return null;
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $this->runtime
                ->connection()
                ->prepare("
                    SELECT DISTINCT d.*

                    FROM secretariat_desks d

                    INNER JOIN
                        secretariat_desk_organizations dso
                        ON dso.secretariat_desk_id =
                            d.id

                    WHERE d.id = ?
                      AND d.root_organization_id = ?
                      AND d.status = 'active'
                      AND dso.status = 'active'

                      AND dso.organization_id
                            IN ({$in})

                      AND (
                            dso.valid_from IS NULL
                            OR dso.valid_from
                                <= UTC_TIMESTAMP()
                      )

                      AND (
                            dso.valid_until IS NULL
                            OR dso.valid_until
                                >= UTC_TIMESTAMP()
                      )

                    LIMIT 1
                ");

        $statement->execute(
            array_merge(
                [
                    $deskId,

                    (int) $actor[
                        'root_organization_id'
                    ],
                ],
                $params
            )
        );

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function periodForActor(
        int $periodId,
        array $actor
    ): ?array {
        $ids =
            $this->allowedOrganizationIds(
                $actor
            );

        if ($ids === []) {
            return null;
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $this->runtime
                ->connection()
                ->prepare("
                    SELECT *

                    FROM registry_periods

                    WHERE id = ?
                      AND root_organization_id = ?
                      AND status = 'active'

                      AND (
                            organization_id IS NULL
                            OR organization_id
                                IN ({$in})
                      )

                    LIMIT 1
                ");

        $statement->execute(
            array_merge(
                [
                    $periodId,

                    (int) $actor[
                        'root_organization_id'
                    ],
                ],
                $params
            )
        );

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function sequenceForActor(
        int $sequenceId,
        array $actor
    ): ?array {
        $ids =
            $this->allowedOrganizationIds(
                $actor
            );

        if ($ids === []) {
            return null;
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $this->runtime
                ->connection()
                ->prepare("
                    SELECT DISTINCT s.*

                    FROM registry_number_sequences s

                    INNER JOIN secretariat_desks d
                        ON d.id =
                            s.secretariat_desk_id

                    INNER JOIN
                        secretariat_desk_organizations dso
                        ON dso.secretariat_desk_id =
                            d.id

                    WHERE s.id = ?
                      AND s.root_organization_id = ?
                      AND s.status = 'active'
                      AND d.status = 'active'
                      AND dso.status = 'active'

                      AND dso.organization_id
                            IN ({$in})

                      AND (
                            dso.valid_from IS NULL
                            OR dso.valid_from
                                <= UTC_TIMESTAMP()
                      )

                      AND (
                            dso.valid_until IS NULL
                            OR dso.valid_until
                                >= UTC_TIMESTAMP()
                      )

                      AND (
                            s.organization_id IS NULL
                            OR s.organization_id
                                IN ({$in})
                      )

                    LIMIT 1
                ");

        $statement->execute(
            array_merge(
                [
                    $sequenceId,

                    (int) $actor[
                        'root_organization_id'
                    ],
                ],
                $params,
                $params
            )
        );

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function sequenceForActorByReference(
        string $publicReference,
        array $actor
    ): ?array {
        $publicReference =
            trim(
                $publicReference
            );

        if ($publicReference === '') {
            return null;
        }

        $ids =
            $this->allowedOrganizationIds(
                $actor
            );

        if ($ids === []) {
            return null;
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $this->runtime
                ->connection()
                ->prepare("
                    SELECT DISTINCT s.*

                    FROM registry_number_sequences s

                    INNER JOIN secretariat_desks d
                        ON d.id =
                            s.secretariat_desk_id

                    INNER JOIN
                        secretariat_desk_organizations dso
                        ON dso.secretariat_desk_id =
                            d.id

                    WHERE s.public_reference = ?
                      AND s.root_organization_id = ?
                      AND s.status = 'active'
                      AND d.status = 'active'
                      AND dso.status = 'active'

                      AND dso.organization_id
                            IN ({$in})

                      AND (
                            dso.valid_from IS NULL
                            OR dso.valid_from
                                <= UTC_TIMESTAMP()
                      )

                      AND (
                            dso.valid_until IS NULL
                            OR dso.valid_until
                                >= UTC_TIMESTAMP()
                      )

                      AND (
                            s.organization_id IS NULL
                            OR s.organization_id
                                IN ({$in})
                      )

                    LIMIT 1
                ");

        $statement->execute(
            array_merge(
                [
                    $publicReference,

                    (int) $actor[
                        'root_organization_id'
                    ],
                ],
                $params,
                $params
            )
        );

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function sequenceUsage(
        int $sequenceId
    ): array {
        $statement =
            $this->runtime
                ->connection()
                ->prepare("
                    SELECT
                        (
                            SELECT COUNT(*)
                            FROM registry_books
                            WHERE number_sequence_id = ?
                        ) AS books,

                        (
                            SELECT COUNT(*)
                            FROM registry_number_reservations
                            WHERE number_sequence_id = ?
                        ) AS reservations
                ");

        $statement->execute([
            $sequenceId,
            $sequenceId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            ) ?: [];

        return [
            'books' =>
                (int) (
                    $row['books']
                    ?? 0
                ),

            'reservations' =>
                (int) (
                    $row['reservations']
                    ?? 0
                ),
        ];
    }

    private function sequenceCodeExistsExcluding(
        PDO $pdo,
        int $rootId,
        ?int $organizationId,
        int $deskId,
        int $periodId,
        string $code,
        int $excludeSequenceId
    ): bool {
        $statement =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM registry_number_sequences

                WHERE root_organization_id = ?
                  AND organization_scope_key =
                        COALESCE(?, 0)
                  AND secretariat_desk_id = ?
                  AND registry_period_id = ?
                  AND code = ?
                  AND id <> ?
            ");

        $statement->execute([
            $rootId,
            $organizationId,
            $deskId,
            $periodId,
            $code,
            $excludeSequenceId,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function deskServesOrganization(
        int $deskId,
        int $organizationId
    ): bool {
        $statement =
            $this->runtime
                ->connection()
                ->prepare("
                    SELECT COUNT(*)

                    FROM
                        secretariat_desk_organizations

                    WHERE secretariat_desk_id = ?
                      AND organization_id = ?
                      AND status = 'active'

                      AND (
                            valid_from IS NULL
                            OR valid_from
                                <= UTC_TIMESTAMP()
                      )

                      AND (
                            valid_until IS NULL
                            OR valid_until
                                >= UTC_TIMESTAMP()
                      )
                ");

        $statement->execute([
            $deskId,
            $organizationId,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function deskCanRegister(
        int $deskId,
        int $organizationId,
        string $direction
    ): bool {
        $column = match ($direction) {
            'incoming' =>
                'can_register_incoming',

            'outgoing' =>
                'can_register_outgoing',

            'internal' =>
                'can_register_internal',

            default =>
                null,
        };

        if ($column === null) {
            return false;
        }

        $statement =
            $this->runtime
                ->connection()
                ->prepare("
                    SELECT {$column}

                    FROM
                        secretariat_desk_organizations

                    WHERE secretariat_desk_id = ?
                      AND organization_id = ?
                      AND status = 'active'

                      AND (
                            valid_from IS NULL
                            OR valid_from
                                <= UTC_TIMESTAMP()
                      )

                      AND (
                            valid_until IS NULL
                            OR valid_until
                                >= UTC_TIMESTAMP()
                      )

                    LIMIT 1
                ");

        $statement->execute([
            $deskId,
            $organizationId,
        ]);

        return
            (int) (
                $statement
                    ->fetchColumn()
            ) === 1;
    }

    private function servedOrganizationIds(
        int $deskId,
        array $allowedOrganizationIds
    ): array {
        [$in, $params] =
            $this->inClause(
                $allowedOrganizationIds
            );

        if ($in === '') {
            return [];
        }

        $statement =
            $this->runtime
                ->connection()
                ->prepare("
                    SELECT organization_id

                    FROM
                        secretariat_desk_organizations

                    WHERE secretariat_desk_id = ?

                      AND organization_id
                            IN ({$in})

                      AND status = 'active'

                      AND (
                            valid_from IS NULL
                            OR valid_from
                                <= UTC_TIMESTAMP()
                      )

                      AND (
                            valid_until IS NULL
                            OR valid_until
                                >= UTC_TIMESTAMP()
                      )

                    ORDER BY
                        is_primary DESC,
                        organization_id
                ");

        $statement->execute(
            array_merge(
                [
                    $deskId,
                ],
                $params
            )
        );

        return array_map(
            'intval',
            $statement->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: []
        );
    }

    private function organizationAllowed(
        int $organizationId,
        array $actor
    ): bool {
        return in_array(
            $organizationId,
            $this->allowedOrganizationIds(
                $actor
            ),
            true
        );
    }

    private function allowedOrganizationIds(
        array $actor
    ): array {
        $ids =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            $actor[
                                'accessible_organization_ids'
                            ] ?? []
                        ),
                        static fn (
                            int $id
                        ): bool =>
                            $id > 0
                    )
                )
            );

        sort(
            $ids,
            SORT_NUMERIC
        );

        return $ids;
    }

    private function orgUnitBelongsTo(
        int $orgUnitId,
        int $organizationId
    ): bool {
        $statement =
            $this->core->prepare("
                SELECT COUNT(*)

                FROM org_units

                WHERE id = ?
                  AND organization_id = ?
                  AND status = 'active'
                  AND deleted_at IS NULL
            ");

        $statement->execute([
            $orgUnitId,
            $organizationId,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function organizationReferences(
        array $ids
    ): array {
        if ($ids === []) {
            return [];
        }

        [$in, $params] =
            $this->inClause(
                $ids
            );

        $statement =
            $this->core->prepare("
                SELECT
                    id,
                    public_reference

                FROM organizations

                WHERE id IN ({$in})
                  AND is_active = 1
            ");

        $statement->execute(
            $params
        );

        $references = [];

        foreach (
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
            as $row
        ) {
            $references[
                (int) $row['id']
            ] =
                (string) $row[
                    'public_reference'
                ];
        }

        return $references;
    }

    private function organizationReference(
        int $organizationId
    ): ?string {
        $references =
            $this->organizationReferences([
                $organizationId,
            ]);

        return
            $references[
                $organizationId
            ] ?? null;
    }

    private function deskCodeExists(
        PDO $pdo,
        int $rootId,
        int $organizationId,
        string $code
    ): bool {
        $statement =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM secretariat_desks

                WHERE root_organization_id = ?
                  AND managing_organization_id = ?
                  AND code = ?
            ");

        $statement->execute([
            $rootId,
            $organizationId,
            $code,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function periodCodeExists(
        PDO $pdo,
        int $rootId,
        ?int $organizationId,
        string $code
    ): bool {
        $statement =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM registry_periods

                WHERE root_organization_id = ?
                  AND organization_scope_key =
                        COALESCE(?, 0)
                  AND code = ?
            ");

        $statement->execute([
            $rootId,
            $organizationId,
            $code,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function sequenceCodeExists(
        PDO $pdo,
        int $rootId,
        ?int $organizationId,
        int $deskId,
        int $periodId,
        string $code
    ): bool {
        $statement =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM registry_number_sequences

                WHERE root_organization_id = ?
                  AND organization_scope_key =
                        COALESCE(?, 0)
                  AND secretariat_desk_id = ?
                  AND registry_period_id = ?
                  AND code = ?
            ");

        $statement->execute([
            $rootId,
            $organizationId,
            $deskId,
            $periodId,
            $code,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function bookCodeExists(
        PDO $pdo,
        int $rootId,
        int $organizationId,
        int $deskId,
        int $periodId,
        string $code
    ): bool {
        $statement =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM registry_books

                WHERE root_organization_scope_key = ?
                  AND organization_id = ?
                  AND secretariat_scope_key = ?
                  AND registry_period_scope_key = ?
                  AND code = ?
            ");

        $statement->execute([
            $rootId,
            $organizationId,
            $deskId,
            $periodId,
            $code,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function sequenceUsageAllows(
        PDO $pdo,
        int $sequenceId,
        string $strategy
    ): bool {
        $statement =
            $pdo->prepare("
                SELECT
                    numbering_strategy_code

                FROM registry_books

                WHERE number_sequence_id = ?
                  AND status = 'active'
            ");

        $statement->execute([
            $sequenceId,
        ]);

        $existingStrategies =
            array_values(
                array_filter(
                    array_map(
                        static fn (
                            mixed $value
                        ): string =>
                            strtolower(
                                trim(
                                    (string) $value
                                )
                            ),

                        $statement->fetchAll(
                            PDO::FETCH_COLUMN
                        ) ?: []
                    ),

                    static fn (
                        string $value
                    ): bool =>
                        $value !== ''
                )
            );

        if ($existingStrategies === []) {
            return true;
        }

        if ($strategy === 'dedicated') {
            return false;
        }

        foreach (
            $existingStrategies
            as $existingStrategy
        ) {
            if (
                $existingStrategy !== 'shared'
            ) {
                return false;
            }
        }

        return true;
    }

    private function dateInput(
        mixed $value
    ): ?string {
        $value =
            trim(
                (string) (
                    $value ?? ''
                )
            );

        if ($value === '') {
            return null;
        }

        if (
            preg_match(
                '/^(\d{4})-(\d{2})-(\d{2})$/',
                $value,
                $matches
            ) === 1
        ) {
            $year =
                (int) $matches[1];

            $month =
                (int) $matches[2];

            $day =
                (int) $matches[3];

            return
                checkdate(
                    $month,
                    $day,
                    $year
                )
                    ? sprintf(
                        '%04d-%02d-%02d',
                        $year,
                        $month,
                        $day
                    )
                    : null;
        }

        try {
            return
                PersianDate::toGregorianDate(
                    $value
                );

        } catch (RuntimeException) {
            return null;
        }
    }

    private function code(
        mixed $value
    ): string {
        $value =
            strtolower(
                trim(
                    PersianDate::normalizeDigits(
                        (string) (
                            $value ?? ''
                        )
                    )
                )
            );

        return
            preg_match(
                '/^[a-z0-9][a-z0-9_-]{0,99}$/',
                $value
            ) === 1
                ? $value
                : '';
    }

    private function text(
        mixed $value,
        int $max
    ): string {
        $value =
            trim(
                (string) (
                    $value ?? ''
                )
            );

        return
            function_exists('mb_substr')
                ? mb_substr(
                    $value,
                    0,
                    $max,
                    'UTF-8'
                )
                : substr(
                    $value,
                    0,
                    $max
                );
    }

    private function nullableText(
        mixed $value,
        int $max
    ): ?string {
        $value =
            $this->text(
                $value,
                $max
            );

        return
            $value === ''
                ? null
                : $value;
    }

    private function positiveInt(
        mixed $value
    ): ?int {
        $normalized =
            PersianDate::normalizeDigits(
                trim(
                    (string) (
                        $value ?? ''
                    )
                )
            );

        if (
            $normalized === ''
            || preg_match(
                '/^[0-9]+$/',
                $normalized
            ) !== 1
        ) {
            return null;
        }

        $number =
            (int) $normalized;

        return
            $number > 0
                ? $number
                : null;
    }

    private function idList(
        mixed $value
    ): array {
        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = [];

        foreach ($value as $item) {

            $id =
                $this->positiveInt(
                    $item
                );

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return
            array_values(
                array_unique(
                    $ids
                )
            );
    }

    private function booleanInput(
        mixed $value
    ): bool {
        return in_array(
            strtolower(
                trim(
                    (string) (
                        $value ?? ''
                    )
                )
            ),
            [
                '1',
                'true',
                'on',
                'yes',
            ],
            true
        );
    }

    private function inClause(
        array $ids
    ): array {
        $ids =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            $ids
                        ),
                        static fn (
                            int $id
                        ): bool =>
                            $id > 0
                    )
                )
            );

        if ($ids === []) {
            return [
                '',
                [],
            ];
        }

        return [
            implode(
                ',',
                array_fill(
                    0,
                    count($ids),
                    '?'
                )
            ),

            $ids,
        ];
    }

    private function publicReference(
        string $prefix
    ): string {
        return
            $prefix
            . '-'
            . gmdate('Ymd')
            . '-'
            . strtoupper(
                bin2hex(
                    random_bytes(6)
                )
            );
    }
}
