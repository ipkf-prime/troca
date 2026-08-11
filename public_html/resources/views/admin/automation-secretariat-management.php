<?php

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$page = $page ?? [];
$errors = $errors ?? [];
$activeSection = (string) ($activeSection ?? 'desk');
$formInput = is_array($formInput ?? null) ? $formInput : [];
$status = (string) ($status ?? '');

$organizations = $page['organizations'] ?? [];
$orgUnits = $page['org_units'] ?? [];
$desks = $page['desks'] ?? [];
$appointments = $page['appointments'] ?? [];
$memberships = $page['memberships'] ?? [];
$periods = $page['periods'] ?? [];
$sequences = $page['sequences'] ?? [];
$books = $page['books'] ?? [];

$canManageRoot =
    (bool) (
        $page['can_manage_root_scope']
        ?? false
    );

$actor =
    is_array($page['actor'] ?? null)
        ? $page['actor']
        : [];

$actorOrganizationId =
    (int) (
        $actor['organization_id']
        ?? 0
    );

$csrf =
    (new \IPKF\Security\Csrf())
        ->token();

$activeMembershipCount =
    count(
        array_filter(
            $memberships,
            static fn (
                array $row
            ): bool =>
                (string) (
                    $row['status']
                    ?? ''
                ) === 'active'
        )
    );

$digits =
    static fn (
        mixed $value
    ): string =>
        \App\Support\AdminFormat::digits(
            $value
        );

$statusMessages = [
    'desk_saved' =>
        'دبیرخانه با موفقیت ثبت شد.',

    'desk_updated' =>
        'دبیرخانه با موفقیت ویرایش شد.',

    'desk_not_found' =>
        'دبیرخانه موردنظر در دامنه مجاز شما پیدا نشد.',

    'period_saved' =>
        'دوره ثبت با موفقیت ایجاد شد.',

    'period_updated' =>
        'دوره ثبت با موفقیت ویرایش شد.',

    'period_not_found' =>
        'دوره ثبت موردنظر در دامنه مجاز شما پیدا نشد.',

    'sequence_saved' =>
        'منبع شماره با موفقیت ایجاد شد.',

    'sequence_updated' =>
        'منبع شماره با موفقیت ویرایش شد.',

    'sequence_not_found' =>
        'منبع شماره موردنظر در دامنه مجاز شما پیدا نشد.',

    'book_saved' =>
        'دفتر ثبت با موفقیت ایجاد شد.',

    'book_updated' =>
        'دفتر ثبت با موفقیت ویرایش شد.',

    'book_not_found' =>
        'دفتر ثبت موردنظر در دامنه مجاز شما پیدا نشد.',

    'member_saved' =>
        'عضویت دبیرخانه با موفقیت ثبت شد.',

    'member_deactivated' =>
        'عضویت دبیرخانه با موفقیت غیرفعال شد.',

    'invalid_csrf' =>
        'اعتبار فرم منقضی شده است. فرم را دوباره ارسال کنید.',
];

$inputValue =
    static function (
        string $name,
        mixed $default = ''
    ) use ($formInput): mixed {
        return array_key_exists(
            $name,
            $formInput
        )
            ? $formInput[$name]
            : $default;
    };

$selected =
    static function (
        mixed $current,
        mixed $expected
    ): string {
        return
            (string) $current ===
            (string) $expected
                ? ' selected'
                : '';
    };

$checked =
    static function (
        string $name,
        bool $default = false
    ) use ($formInput): string {
        if ($formInput === []) {
            return $default
                ? ' checked'
                : '';
        }

        if (
            !array_key_exists(
                $name,
                $formInput
            )
        ) {
            return '';
        }

        $value =
            strtolower(
                trim(
                    (string) (
                        $formInput[$name]
                        ?? ''
                    )
                )
            );

        return in_array(
            $value,
            [
                '1',
                'true',
                'on',
                'yes',
            ],
            true
        )
            ? ' checked'
            : '';
    };

$editingDeskReference =
    trim(
        (string) (
            $formInput[
                'edit_desk_reference'
            ]
            ?? ''
        )
    );

$isDeskEdit =
    $activeSection === 'desk'
    && $editingDeskReference !== '';

$deskLocked =
    $isDeskEdit
    && (string) (
        $formInput[
            'desk_locked'
        ]
        ?? '0'
    ) === '1';


$editingPeriodReference =
    trim(
        (string) (
            $formInput[
                'edit_period_reference'
            ]
            ?? ''
        )
    );

$isPeriodEdit =
    $activeSection === 'period'
    && $editingPeriodReference !== '';

$periodLocked =
    $isPeriodEdit
    && (string) (
        $formInput[
            'period_locked'
        ]
        ?? '0'
    ) === '1';


$editingSequenceReference =
    trim(
        (string) (
            $formInput[
                'edit_sequence_reference'
            ]
            ?? ''
        )
    );

$isSequenceEdit =
    $activeSection === 'sequence'
    && $editingSequenceReference !== '';

$sequenceLocked =
    $isSequenceEdit
    && (string) (
        $formInput[
            'sequence_locked'
        ]
        ?? '0'
    ) === '1';


$editingBookReference =
    trim(
        (string) (
            $formInput[
                'edit_book_reference'
            ]
            ?? ''
        )
    );

$isBookEdit =
    $activeSection === 'book'
    && $editingBookReference !== '';

$bookLocked =
    $isBookEdit
    && (string) (
        $formInput[
            'book_locked'
        ]
        ?? '0'
    ) === '1';


$bookDirectionLabels = [
    'incoming' => 'وارده',
    'outgoing' => 'صادره',
    'internal' => 'داخلی',
];

$bookDirectionInput = [];

if (
    array_key_exists(
        'direction_codes',
        $formInput
    )
) {
    $bookDirectionInput =
        is_array(
            $formInput[
                'direction_codes'
            ]
        )
            ? $formInput[
                'direction_codes'
            ]
            : [
                $formInput[
                    'direction_codes'
                ],
            ];

} elseif (
    array_key_exists(
        'direction_codes_present',
        $formInput
    )
) {
    /*
     * The user explicitly submitted the
     * multi-direction control but selected
     * no direction.
     */
    $bookDirectionInput = [];

} elseif (
    array_key_exists(
        'scope_code',
        $formInput
    )
) {
    $legacyBookScope =
        (string) $formInput[
            'scope_code'
        ];

    if ($legacyBookScope === 'general') {
        $bookDirectionInput = [
            'incoming',
            'outgoing',
            'internal',
        ];

    } elseif (
        isset(
            $bookDirectionLabels[
                $legacyBookScope
            ]
        )
    ) {
        $bookDirectionInput = [
            $legacyBookScope,
        ];
    }

} elseif ($formInput === []) {
    $bookDirectionInput = [
        'incoming',
    ];
}

$bookDirectionInput =
    array_values(
        array_unique(
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
                    $bookDirectionInput
                ),
                static fn (
                    string $value
                ): bool =>
                    isset(
                        $bookDirectionLabels[
                            $value
                        ]
                    )
            )
        )
    );


$servedInput =
    $formInput[
        'served_organization_ids'
    ] ?? [];

if (!is_array($servedInput)) {
    $servedInput = [
        $servedInput,
    ];
}

$servedInput =
    array_map(
        'intval',
        $servedInput
    );

$periodStartFa =
    (string) (
        $formInput['starts_on_fa']
        ?? \App\Support\PersianDate::fromGregorianDate(
            (string) (
                $formInput['starts_on']
                ?? ''
            )
        )
    );

$periodEndFa =
    (string) (
        $formInput['ends_on_fa']
        ?? \App\Support\PersianDate::fromGregorianDate(
            (string) (
                $formInput['ends_on']
                ?? ''
            )
        )
    );

ob_start();
?>

<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span>/</span>
    <a href="/admin/automation">اتوماسیون اداری</a>
    <span>/</span>
    <span>دبیرخانه و دفاتر ثبت</span>
</nav>

<section class="admin-module-hub admin-module-hub--teal admin-users-heading">
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html('organization') ?>
    </div>

    <div>
        <h2>مدیریت دبیرخانه و دفاتر ثبت</h2>
        <p>
            تعریف دبیرخانه، دوره ثبت، منبع شماره، دفترهای ثبت
            و اعضای عملیاتی دبیرخانه در دامنه سازمانی فعال
        </p>
    </div>

    <a
        class="admin-module-hub__back"
        href="/admin/automation"
    >بازگشت به اتوماسیون</a>
</section>

<?php if (($page['ok'] ?? false) !== true): ?>
    <section class="admin-section">
        <div class="admin-alert admin-alert--danger">
            اطلاعات پیکربندی دبیرخانه در دسترس نیست.
        </div>
    </section>
<?php else: ?>

<?php if (isset($statusMessages[$status])): ?>
    <section class="admin-section">
        <div
            class="admin-alert<?= $status === 'invalid_csrf' ? ' admin-alert--danger' : '' ?>"
            role="status"
        >
            <?= admin_h($statusMessages[$status]) ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <section class="admin-section">
        <div class="admin-alert admin-alert--danger" role="alert">
            <strong>ثبت اطلاعات انجام نشد.</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= admin_h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<section class="admin-section admin-users-panel">
    <div class="admin-section__header">
        <div>
            <h2>راه‌اندازی دبیرخانه</h2>
            <p class="admin-muted">
                ترتیب پیشنهادی: دبیرخانه ← دوره ثبت ← منبع شماره ← دفتر ثبت ← اعضای دبیرخانه.
            </p>
        </div>
    </div>

    <nav
        class="automation-secretariat-tabs"
        aria-label="بخش‌های مدیریت دبیرخانه"
    >
        <?php foreach ([
            [
                'desk',
                'دبیرخانه',
                count($desks),
            ],
            [
                'period',
                'دوره ثبت',
                count($periods),
            ],
            [
                'sequence',
                'منبع شماره',
                count($sequences),
            ],
            [
                'book',
                'دفتر ثبت',
                count($books),
            ],
            [
                'member',
                'اعضای دبیرخانه',
                $activeMembershipCount,
            ],
        ] as [$sectionCode, $sectionLabel, $sectionCount]): ?>
            <a
                class="automation-secretariat-tab<?= $activeSection === $sectionCode ? ' is-active' : '' ?>"
                href="/admin/automation/secretariat?section=<?= admin_h(
                    $sectionCode
                ) ?>"
                <?= $activeSection === $sectionCode
                    ? 'aria-current="page"'
                    : '' ?>
            >
                <span>
                    <?= admin_h($sectionLabel) ?>
                </span>

                <strong>
                    <?= admin_h(
                        $digits(
                            $sectionCount
                        )
                    ) ?>
                </strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- STEP 1 -->
    <details
        class="automation-secretariat-step"
        <?= $activeSection === 'desk' ? 'open' : 'hidden' ?>
    >
        <summary>
            مرحله ۱ ـ تعریف دبیرخانه
        </summary>

        <div class="automation-secretariat-step__body">
            <div class="automation-secretariat-step__head">
                <div>
                    <h3>دبیرخانه</h3>
                    <p class="admin-muted">
                        دبیرخانه می‌تواند سازمانی باشد؛ در سطح ریشه نیز
                        امکان تعریف دبیرخانه مشترک برای چند سازمان وجود دارد.
                    </p>
                </div>
            </div>

            <?php if ($isDeskEdit): ?>
                <div class="admin-alert">
                    <?php if ($deskLocked): ?>
                        این دبیرخانه دارای وابستگی عملیاتی است؛
                        ساختار آن قفل شده و فقط عنوان فارسی و انگلیسی
                        قابل ویرایش است.
                    <?php else: ?>
                        در حال ویرایش دبیرخانه هستید.
                        چون هنوز وابستگی عملیاتی ندارد، ساختار آن نیز
                        قابل اصلاح است.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= admin_h(
                    $isDeskEdit
                        ? (
                            '/admin/automation/secretariat/desks/'
                            . rawurlencode(
                                $editingDeskReference
                            )
                        )
                        : '/admin/automation/secretariat/desks'
                ) ?>"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($csrf) ?>"
                >

                <div class="admin-form-grid">
                    <label>
                        <span>کد دبیرخانه</span>
                        <input
                            class="automation-secretariat-code"
                            name="code"
                            maxlength="100"
                            dir="ltr"
                            required
                            placeholder="central"
                            <?= $deskLocked ? 'disabled' : '' ?>
                            value="<?= admin_h($inputValue('code')) ?>"
                        >
                    </label>

                    <label>
                        <span>عنوان فارسی</span>
                        <input
                            name="title_fa"
                            maxlength="255"
                            required
                            placeholder="دبیرخانه مرکزی"
                            value="<?= admin_h($inputValue('title_fa')) ?>"
                        >
                    </label>

                    <label>
                        <span>عنوان انگلیسی</span>
                        <input
                            name="title_en"
                            maxlength="255"
                            dir="ltr"
                            placeholder="Central Secretariat"
                            value="<?= admin_h($inputValue('title_en')) ?>"
                        >
                    </label>

                    <label>
                        <span>نوع دبیرخانه</span>
                        <select
                            name="desk_kind_code"
                            data-desk-kind
                            <?= $deskLocked ? 'disabled' : '' ?>
                        >
                            <option
                                value="organization"
                                <?= $selected(
                                    $inputValue(
                                        'desk_kind_code',
                                        'organization'
                                    ),
                                    'organization'
                                ) ?>
                            >سازمانی</option>

                            <?php if ($canManageRoot): ?>
                                <option
                                    value="shared"
                                    <?= $selected(
                                        $inputValue(
                                            'desk_kind_code'
                                        ),
                                        'shared'
                                    ) ?>
                                >مشترک بین سازمان‌ها</option>
                            <?php endif; ?>
                        </select>
                    </label>

                    <label>
                        <span>سازمان متولی</span>
                        <select
                            name="managing_organization_id"
                            required
                            data-managing-organization
                            <?= $deskLocked ? 'disabled' : '' ?>
                        >
                            <?php foreach ($organizations as $organization): ?>
                                <?php
                                $organizationId =
                                    (int) $organization['id'];

                                $defaultOrg =
                                    $inputValue(
                                        'managing_organization_id',
                                        $actorOrganizationId
                                    );
                                ?>
                                <option
                                    value="<?= admin_h($organizationId) ?>"
                                    <?= $selected(
                                        $defaultOrg,
                                        $organizationId
                                    ) ?>
                                >
                                    <?= admin_h($organization['title'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>واحد سازمانی دبیرخانه</span>
                        <select
                            name="org_unit_id"
                            data-org-unit-select
                            <?= $deskLocked ? 'disabled' : '' ?>
                        >
                            <option value="">بدون واحد مشخص</option>

                            <?php foreach ($orgUnits as $unit): ?>
                                <option
                                    value="<?= admin_h($unit['id']) ?>"
                                    data-organization-id="<?= admin_h($unit['organization_id']) ?>"
                                    <?= $selected(
                                        $inputValue('org_unit_id'),
                                        $unit['id']
                                    ) ?>
                                >
                                    <?= admin_h($unit['title'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="automation-secretariat-flags">
                    <label>
                        <input
                            type="checkbox"
                            name="supports_incoming"
                            value="1"
                            <?= $deskLocked ? 'disabled' : '' ?>
                            <?= $checked(
                                'supports_incoming',
                                true
                            ) ?>
                        >
                        وارده
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            name="supports_outgoing"
                            value="1"
                            <?= $deskLocked ? 'disabled' : '' ?>
                            <?= $checked(
                                'supports_outgoing',
                                true
                            ) ?>
                        >
                        صادره
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            name="supports_internal"
                            value="1"
                            <?= $deskLocked ? 'disabled' : '' ?>
                            <?= $checked(
                                'supports_internal',
                                true
                            ) ?>
                        >
                        داخلی
                    </label>
                </div>

                <?php if ($canManageRoot && count($organizations) > 1): ?>
                    <div
                        data-shared-organizations
                        style="margin-top:14px"
                    >
                        <strong>سازمان‌های تحت خدمت دبیرخانه مشترک</strong>
                        <p class="admin-muted">
                            سازمان متولی به‌صورت خودکار در این فهرست قرار می‌گیرد.
                        </p>

                        <div class="automation-secretariat-org-list">
                            <?php foreach ($organizations as $organization): ?>
                                <?php
                                $organizationId =
                                    (int) $organization['id'];

                                $isServed =
                                    in_array(
                                        $organizationId,
                                        $servedInput,
                                        true
                                    )
                                    || (
                                        $servedInput === []
                                        && $organizationId ===
                                            $actorOrganizationId
                                    );
                                ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="served_organization_ids[]"
                                        value="<?= admin_h($organizationId) ?>"
                                        <?= $deskLocked ? 'disabled' : '' ?>
                                        <?= $isServed ? ' checked' : '' ?>
                                    >
                                    <?= admin_h($organization['title'] ?? '') ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="admin-form-actions">
                    <button
                        class="admin-button"
                        type="submit"
                    ><?= $isDeskEdit
                        ? 'ذخیره ویرایش'
                        : 'ثبت دبیرخانه' ?></button>

                    <?php if ($isDeskEdit): ?>
                        <a
                            class="admin-button"
                            href="/admin/automation/secretariat?section=desk"
                        >انصراف از ویرایش</a>
                    <?php endif; ?>
                </div>
            </form>

            <h4 class="automation-secretariat-table-title">
                دبیرخانه‌های موجود
            </h4>

            <?php if ($desks === []): ?>
                <div class="admin-empty-state">
                    هنوز دبیرخانه‌ای تعریف نشده است.
                </div>
            <?php else: ?>
                <div class="admin-users-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>کد</th>
                                <th>نوع</th>
                                <th>سازمان متولی</th>
                                <th>سازمان‌های تحت خدمت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($desks as $desk): ?>
                                <?php
                                $deskReference =
                                    trim(
                                        (string) (
                                            $desk[
                                                'public_reference'
                                            ]
                                            ?? ''
                                        )
                                    );

                                $deskCanEdit =
                                    $deskReference !== ''
                                    && (
                                        (
                                            $desk[
                                                'desk_kind_code'
                                            ] ?? ''
                                        ) !== 'shared'
                                        || $canManageRoot
                                    );
                                ?>
                                <tr>
                                    <td><?= admin_h($desk['title_fa'] ?? '') ?></td>
                                    <td class="automation-secretariat-code"><?= admin_h($desk['code'] ?? '') ?></td>
                                    <td>
                                        <?= ($desk['desk_kind_code'] ?? '') === 'shared'
                                            ? 'مشترک'
                                            : 'سازمانی' ?>
                                    </td>
                                    <td><?= admin_h($desk['managing_organization_title'] ?? '—') ?></td>
                                    <td>
                                        <?= admin_h(
                                            implode(
                                                '، ',
                                                $desk['served_organization_titles']
                                                ?? []
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php if ($deskCanEdit): ?>
                                            <a
                                                class="automation-secretariat-action-button"
                                                href="/admin/automation/secretariat?section=desk&amp;edit_desk=<?= rawurlencode(
                                                    $deskReference
                                                ) ?>"
                                            >ویرایش</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </details>

    <!-- STEP 2 -->
    <details
        class="automation-secretariat-step"
        <?= $activeSection === 'period' ? 'open' : 'hidden' ?>
    >
        <summary>
            مرحله ۲ ـ تعریف دوره ثبت
        </summary>

        <div class="automation-secretariat-step__body">
            <div class="automation-secretariat-step__head">
                <div>
                    <h3>دوره ثبت</h3>
                    <p class="admin-muted">
                        بازه زمانی معتبر برای دفتر و شماره‌گذاری؛
                        می‌تواند سازمانی یا در سطح ریشه مشترک باشد.
                    </p>
                </div>
            </div>

            <?php if ($isPeriodEdit): ?>
                <div class="admin-alert">
                    <?php if ($periodLocked): ?>
                        این دوره ثبت دارای وابستگی عملیاتی است؛
                        ساختار آن قفل شده و فقط عنوان قابل ویرایش است.
                    <?php else: ?>
                        در حال ویرایش دوره ثبت هستید.
                        چون هنوز وابستگی عملیاتی ندارد، دامنه، کد و
                        تاریخ‌های دوره نیز قابل اصلاح هستند.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= admin_h(
                    $isPeriodEdit
                        ? (
                            '/admin/automation/secretariat/periods/'
                            . rawurlencode(
                                $editingPeriodReference
                            )
                        )
                        : '/admin/automation/secretariat/periods'
                ) ?>"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($csrf) ?>"
                >

                <div class="admin-form-grid">
                    <label>
                        <span>دامنه دوره</span>
                        <select
                            name="scope"
                            data-scope-controller
                            data-scope-target="#period-organization-field"
                            data-shared-value="root"
                            <?= $periodLocked ? 'disabled' : '' ?>
                        >
                            <option
                                value="organization"
                                <?= $selected(
                                    $inputValue(
                                        'scope',
                                        'organization'
                                    ),
                                    'organization'
                                ) ?>
                            >سازمانی</option>

                            <?php if ($canManageRoot): ?>
                                <option
                                    value="root"
                                    <?= $selected(
                                        $inputValue('scope'),
                                        'root'
                                    ) ?>
                                >مشترک هلدینگ/گروه</option>
                            <?php endif; ?>
                        </select>
                    </label>

                    <label id="period-organization-field">
                        <span>سازمان</span>
                        <select
                            name="organization_id"
                            <?= $periodLocked ? 'disabled' : '' ?>
                        >
                            <?php foreach ($organizations as $organization): ?>
                                <option
                                    value="<?= admin_h($organization['id']) ?>"
                                    <?= $selected(
                                        $inputValue(
                                            'organization_id',
                                            $actorOrganizationId
                                        ),
                                        $organization['id']
                                    ) ?>
                                >
                                    <?= admin_h($organization['title'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>کد دوره</span>
                        <input
                            name="code"
                            maxlength="100"
                            required
                            inputmode="numeric"
                            data-persian-number-input
                            placeholder="۱۴۰۵"
                            <?= $periodLocked ? 'disabled' : '' ?>
                            value="<?= admin_h(
                                $digits(
                                    $inputValue('code')
                                )
                            ) ?>"
                        >
                    </label>

                    <label>
                        <span>عنوان دوره</span>
                        <input
                            name="title"
                            maxlength="255"
                            required
                            data-persian-number-input
                            placeholder="سال ۱۴۰۵"
                            value="<?= admin_h(
                                $digits(
                                    $inputValue('title')
                                )
                            ) ?>"
                        >
                    </label>

                    <label>
                        <span>تاریخ شروع</span>
                        <div
                            class="admin-persian-date"
                            data-persian-datepicker
                        >
                            <input
                                type="text"
                                name="starts_on_fa"
                                data-persian-date-input
                                <?= $periodLocked ? 'disabled' : '' ?>
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="۱۴۰۵/۰۱/۰۱"
                                value="<?= admin_h($periodStartFa) ?>"
                                required
                            >

                            <input
                                type="hidden"
                                name="starts_on"
                                data-persian-date-output
                                <?= $periodLocked ? 'disabled' : '' ?>
                                value="<?= admin_h($inputValue('starts_on')) ?>"
                            >

                            <button
                                type="button"
                                class="admin-persian-date__toggle"
                                data-persian-date-toggle
                                <?= $periodLocked ? 'disabled' : '' ?>
                                aria-label="انتخاب تاریخ شروع"
                            >
                                <?= \App\Support\AdminIcon::html('calendar') ?>
                            </button>
                        </div>
                    </label>

                    <label>
                        <span>تاریخ پایان</span>
                        <div
                            class="admin-persian-date"
                            data-persian-datepicker
                        >
                            <input
                                type="text"
                                name="ends_on_fa"
                                data-persian-date-input
                                <?= $periodLocked ? 'disabled' : '' ?>
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="۱۴۰۵/۱۲/۲۹"
                                value="<?= admin_h($periodEndFa) ?>"
                                required
                            >

                            <input
                                type="hidden"
                                name="ends_on"
                                data-persian-date-output
                                <?= $periodLocked ? 'disabled' : '' ?>
                                value="<?= admin_h($inputValue('ends_on')) ?>"
                            >

                            <button
                                type="button"
                                class="admin-persian-date__toggle"
                                data-persian-date-toggle
                                <?= $periodLocked ? 'disabled' : '' ?>
                                aria-label="انتخاب تاریخ پایان"
                            >
                                <?= \App\Support\AdminIcon::html('calendar') ?>
                            </button>
                        </div>
                    </label>
                </div>

                <div class="admin-form-actions">
                    <button
                        class="admin-button"
                        type="submit"
                    ><?= $isPeriodEdit
                        ? 'ذخیره ویرایش'
                        : 'ثبت دوره' ?></button>

                    <?php if ($isPeriodEdit): ?>
                        <a
                            class="admin-button"
                            href="/admin/automation/secretariat?section=period"
                        >انصراف از ویرایش</a>
                    <?php endif; ?>
                </div>
            </form>

            <h4 class="automation-secretariat-table-title">
                دوره‌های موجود
            </h4>

            <?php if ($periods === []): ?>
                <div class="admin-empty-state">
                    هنوز دوره ثبتی تعریف نشده است.
                </div>
            <?php else: ?>
                <div class="admin-users-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>کد</th>
                                <th>دامنه</th>
                                <th>شروع</th>
                                <th>پایان</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periods as $period): ?>
                                <?php
                                $periodReference =
                                    trim(
                                        (string) (
                                            $period[
                                                'public_reference'
                                            ]
                                            ?? ''
                                        )
                                    );

                                $periodCanEdit =
                                    $periodReference !== ''
                                    && (
                                        $period[
                                            'organization_id'
                                        ] !== null
                                        || $canManageRoot
                                    );
                                ?>
                                <tr>
                                    <td><?= admin_h(
                                        $digits(
                                            $period['title'] ?? ''
                                        )
                                    ) ?></td>
                                    <td><?= admin_h(
                                        $digits(
                                            $period['code'] ?? ''
                                        )
                                    ) ?></td>
                                    <td><?= admin_h(
                                        $digits(
                                            $period['organization_title'] ?? ''
                                        )
                                    ) ?></td>
                                    <td><?= admin_h(
                                        $digits(
                                            $period['starts_on_fa'] ?? ''
                                        )
                                    ) ?></td>
                                    <td><?= admin_h(
                                        $digits(
                                            $period['ends_on_fa'] ?? ''
                                        )
                                    ) ?></td>

                                    <td>
                                        <?php if ($periodCanEdit): ?>
                                            <a
                                                class="automation-secretariat-action-button"
                                                href="/admin/automation/secretariat?section=period&amp;edit_period=<?= rawurlencode(
                                                    $periodReference
                                                ) ?>"
                                            >ویرایش</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </details>

    <!-- STEP 3 -->
    <details
        class="automation-secretariat-step"
        <?= $activeSection === 'sequence' ? 'open' : 'hidden' ?>
    >
        <summary>
            مرحله ۳ ـ تعریف منبع شماره
        </summary>

        <div class="automation-secretariat-step__body">
            <div class="automation-secretariat-step__head">
                <div>
                    <h3>منبع شماره</h3>
                    <p class="admin-muted">
                        منبع ترتیبی شماره‌ها؛ شماره واقعی هنگام ثبت رسمی
                        به‌صورت تراکنشی از این منبع رزرو خواهد شد.
                    </p>
                </div>
            </div>

            <?php if ($desks === [] || $periods === []): ?>
                <div class="admin-alert automation-secretariat-prerequisite">
                    ابتدا حداقل یک دبیرخانه و یک دوره ثبت تعریف کنید.
                </div>
            <?php endif; ?>

            <?php if ($isSequenceEdit): ?>
                <div class="admin-alert">
                    <?php if ($sequenceLocked): ?>
                        این منبع شماره قبلاً استفاده شده است؛
                        مشخصات شماره‌گذاری آن قفل هستند و فقط عنوان
                        قابل ویرایش است.
                    <?php else: ?>
                        در حال ویرایش منبع شماره هستید.
                        چون هنوز دفتر ثبت یا شماره رسمی به آن وابسته نیست،
                        مشخصات شماره‌گذاری قابل اصلاح است.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= admin_h(
                    $isSequenceEdit
                        ? (
                            '/admin/automation/secretariat/sequences/'
                            . rawurlencode(
                                $editingSequenceReference
                            )
                        )
                        : '/admin/automation/secretariat/sequences'
                ) ?>"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($csrf) ?>"
                >

                <div class="admin-form-grid">
                    <label>
                        <span>دبیرخانه</span>
                        <select
                            name="secretariat_desk_id"
                            required
                            <?= $isSequenceEdit ? 'disabled' : '' ?>
                        >
                            <?php foreach ($desks as $desk): ?>
                                <option
                                    value="<?= admin_h($desk['id']) ?>"
                                    <?= $selected(
                                        $inputValue('secretariat_desk_id'),
                                        $desk['id']
                                    ) ?>
                                >
                                    <?= admin_h($desk['title_fa'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>دوره ثبت</span>
                        <select
                            name="registry_period_id"
                            required
                            <?= $isSequenceEdit ? 'disabled' : '' ?>
                        >
                            <?php foreach ($periods as $period): ?>
                                <option
                                    value="<?= admin_h($period['id']) ?>"
                                    <?= $selected(
                                        $inputValue('registry_period_id'),
                                        $period['id']
                                    ) ?>
                                >
                                    <?= admin_h(
                                        $digits(
                                            ($period['title'] ?? '')
                                            . ' ـ '
                                            . ($period['organization_title'] ?? '')
                                        )
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>دامنه شماره</span>
                        <select
                            name="scope"
                            data-scope-controller
                            data-scope-target="#sequence-organization-field"
                            data-shared-value="shared"
                            <?= $isSequenceEdit ? 'disabled' : '' ?>
                        >
                            <option
                                value="organization"
                                <?= $selected(
                                    $inputValue(
                                        'scope',
                                        'organization'
                                    ),
                                    'organization'
                                ) ?>
                            >سازمانی</option>

                            <?php if ($canManageRoot): ?>
                                <option
                                    value="shared"
                                    <?= $selected(
                                        $inputValue('scope'),
                                        'shared'
                                    ) ?>
                                >مشترک بین سازمان‌ها</option>
                            <?php endif; ?>
                        </select>
                    </label>

                    <label id="sequence-organization-field">
                        <span>سازمان</span>
                        <select
                            name="organization_id"
                            <?= $isSequenceEdit ? 'disabled' : '' ?>
                        >
                            <?php foreach ($organizations as $organization): ?>
                                <option
                                    value="<?= admin_h($organization['id']) ?>"
                                    <?= $selected(
                                        $inputValue(
                                            'organization_id',
                                            $actorOrganizationId
                                        ),
                                        $organization['id']
                                    ) ?>
                                >
                                    <?= admin_h($organization['title'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>کد منبع شماره</span>
                        <input
                            class="automation-secretariat-code"
                            name="code"
                            maxlength="100"
                            required
                            placeholder="incoming"
                            <?= $sequenceLocked ? 'disabled' : '' ?>
                            value="<?= admin_h($inputValue('code')) ?>"
                        >
                    </label>

                    <label>
                        <span>عنوان</span>
                        <input
                            name="title"
                            maxlength="255"
                            required
                            placeholder="شماره وارده"
                            value="<?= admin_h($inputValue('title')) ?>"
                        >
                    </label>

                    <label>
                        <span>پیشوند</span>
                        <input
                            class="automation-secretariat-code"
                            name="prefix"
                            maxlength="50"
                            dir="ltr"
                            placeholder="IN-"
                            <?= $sequenceLocked ? 'disabled' : '' ?>
                            value="<?= admin_h($inputValue('prefix')) ?>"
                        >
                    </label>

                    <label>
                        <span>پسوند</span>
                        <input
                            class="automation-secretariat-code"
                            name="suffix"
                            maxlength="50"
                            dir="ltr"
                            <?= $sequenceLocked ? 'disabled' : '' ?>
                            value="<?= admin_h($inputValue('suffix')) ?>"
                        >
                    </label>

                    <label class="admin-form-grid__wide">
                        <span>الگوی شماره</span>
                        <input
                            class="automation-secretariat-code"
                            name="format_pattern"
                            maxlength="255"
                            dir="ltr"
                            required
                            <?= $sequenceLocked ? 'disabled' : '' ?>
                            value="<?= admin_h(
                                $inputValue(
                                    'format_pattern',
                                    '{prefix}{sequence}{suffix}'
                                )
                            ) ?>"
                        >
                        <small class="admin-muted">
                            متغیرهای مجاز:
                            {prefix} ، {sequence} ، {suffix}
                        </small>
                    </label>

                    <label>
                        <span>تعداد ارقام</span>
                        <input
                            name="number_padding"
                            inputmode="numeric"
                            data-persian-number-input
                            <?= $sequenceLocked ? 'disabled' : '' ?>
                            value="<?= admin_h(
                                $digits(
                                    $inputValue(
                                        'number_padding',
                                        '5'
                                    )
                                )
                            ) ?>"
                            required
                        >
                    </label>

                    <label>
                        <span>شماره شروع</span>
                        <input
                            name="next_sequence_number"
                            inputmode="numeric"
                            data-persian-number-input
                            <?= $sequenceLocked ? 'disabled' : '' ?>
                            value="<?= admin_h(
                                $digits(
                                    $inputValue(
                                        'next_sequence_number',
                                        '1'
                                    )
                                )
                            ) ?>"
                            required
                        >
                    </label>
                </div>

                <div class="admin-form-actions">
                    <button
                        class="admin-button"
                        type="submit"
                        <?= $desks === [] || $periods === [] ? 'disabled' : '' ?>
                    ><?= $isSequenceEdit
                        ? 'ذخیره ویرایش'
                        : 'ثبت منبع شماره' ?></button>

                    <?php if ($isSequenceEdit): ?>
                        <a
                            class="admin-button"
                            href="/admin/automation/secretariat?section=sequence"
                        >انصراف از ویرایش</a>
                    <?php endif; ?>
                </div>
            </form>

            <h4 class="automation-secretariat-table-title">
                منابع شماره موجود
            </h4>

            <?php if ($sequences === []): ?>
                <div class="admin-empty-state">
                    هنوز منبع شماره‌ای تعریف نشده است.
                </div>
            <?php else: ?>
                <div class="admin-users-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>کد</th>
                                <th>دبیرخانه</th>
                                <th>دوره</th>
                                <th>دامنه</th>
                                <th>شماره بعدی</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sequences as $sequence): ?>
                                <tr>
                                    <td><?= admin_h(
                                        $digits(
                                            $sequence['title'] ?? ''
                                        )
                                    ) ?></td>

                                    <td class="automation-secretariat-code"><?= admin_h(
                                        $sequence['code'] ?? ''
                                    ) ?></td>

                                    <td><?= admin_h(
                                        $digits(
                                            $sequence['desk_title'] ?? ''
                                        )
                                    ) ?></td>

                                    <td><?= admin_h(
                                        $digits(
                                            $sequence['period_title'] ?? ''
                                        )
                                    ) ?></td>

                                    <td><?= admin_h(
                                        $digits(
                                            $sequence['organization_title'] ?? ''
                                        )
                                    ) ?></td>

                                    <td><?= admin_h(
                                        $digits(
                                            $sequence[
                                                'next_sequence_number'
                                            ] ?? 1
                                        )
                                    ) ?></td>

                                    <td>
                                        <?php
                                        $sequenceReference =
                                            trim(
                                                (string) (
                                                    $sequence[
                                                        'public_reference'
                                                    ]
                                                    ?? ''
                                                )
                                            );

                                        $sequenceCanEdit =
                                            $sequenceReference !== ''
                                            && (
                                                $sequence[
                                                    'organization_id'
                                                ] !== null
                                                || $canManageRoot
                                            );
                                        ?>

                                        <?php if ($sequenceCanEdit): ?>
                                            <a
                                                class="automation-secretariat-action-button"
                                                href="/admin/automation/secretariat?section=sequence&amp;edit_sequence=<?= rawurlencode(
                                                    $sequenceReference
                                                ) ?>"
                                            >ویرایش</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </details>

    <!-- STEP 4 -->
    <details
        class="automation-secretariat-step"
        <?= $activeSection === 'book' ? 'open' : 'hidden' ?>
    >
        <summary>
            مرحله ۴ ـ تعریف دفتر ثبت
        </summary>

        <div class="automation-secretariat-step__body">
            <div class="automation-secretariat-step__head">
                <div>
                    <h3>دفتر ثبت</h3>
                    <p class="admin-muted">
                        یک دفتر ثبت می‌تواند یک یا چند نوع مکاتبه
                        وارده، صادره و داخلی را پوشش دهد و همه آن‌ها
                        از منبع شماره متصل به همان دفتر استفاده کنند.
                    </p>
                </div>
            </div>

            <?php if (
                $desks === []
                || $periods === []
                || $sequences === []
            ): ?>
                <div class="admin-alert automation-secretariat-prerequisite">
                    ابتدا دبیرخانه، دوره ثبت و منبع شماره را تکمیل کنید.
                </div>
            <?php endif; ?>

            <?php if ($isBookEdit): ?>
                <div class="admin-alert">
                    <?php if ($bookLocked): ?>
                        این دفتر ثبت قبلاً استفاده شده است؛
                        ساختار آن قفل شده و فقط عنوان قابل ویرایش است.
                    <?php else: ?>
                        در حال ویرایش دفتر ثبت هستید.
                        چون هنوز رزرو شماره یا ثبت رسمی ندارد،
                        ساختار دفتر نیز قابل اصلاح است.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= admin_h(
                    $isBookEdit
                        ? (
                            '/admin/automation/secretariat/books/'
                            . rawurlencode(
                                $editingBookReference
                            )
                        )
                        : '/admin/automation/secretariat/books'
                ) ?>"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($csrf) ?>"
                >

                <div class="admin-form-grid">
                    <label>
                        <span>سازمان</span>
                        <select
                            name="organization_id"
                            required
                            <?= $bookLocked ? 'disabled' : '' ?>
                        >
                            <?php foreach ($organizations as $organization): ?>
                                <option
                                    value="<?= admin_h($organization['id']) ?>"
                                    <?= $selected(
                                        $inputValue(
                                            'organization_id',
                                            $actorOrganizationId
                                        ),
                                        $organization['id']
                                    ) ?>
                                >
                                    <?= admin_h($organization['title'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>دبیرخانه</span>
                        <select
                            name="secretariat_desk_id"
                            required
                            <?= $bookLocked ? 'disabled' : '' ?>
                        >
                            <?php foreach ($desks as $desk): ?>
                                <option
                                    value="<?= admin_h($desk['id']) ?>"
                                    <?= $selected(
                                        $inputValue('secretariat_desk_id'),
                                        $desk['id']
                                    ) ?>
                                >
                                    <?= admin_h($desk['title_fa'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>دوره ثبت</span>
                        <select
                            name="registry_period_id"
                            required
                            <?= $bookLocked ? 'disabled' : '' ?>
                        >
                            <?php foreach ($periods as $period): ?>
                                <option
                                    value="<?= admin_h($period['id']) ?>"
                                    <?= $selected(
                                        $inputValue('registry_period_id'),
                                        $period['id']
                                    ) ?>
                                >
                                    <?= admin_h($period['title'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>منبع شماره</span>
                        <select
                            name="number_sequence_id"
                            required
                            <?= $bookLocked ? 'disabled' : '' ?>
                        >
                            <?php foreach ($sequences as $sequence): ?>
                                <option
                                    value="<?= admin_h($sequence['id']) ?>"
                                    <?= $selected(
                                        $inputValue('number_sequence_id'),
                                        $sequence['id']
                                    ) ?>
                                >
                                    <?= admin_h(
                                        ($sequence['title'] ?? '')
                                        . ' ـ '
                                        . ($sequence['desk_title'] ?? '')
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="admin-form-grid__wide">
                        <span class="automation-secretariat-field-title">
                            انواع مکاتبه دفتر
                        </span>

                        <input
                            type="hidden"
                            name="direction_codes_present"
                            value="1"
                        >

                        <div class="automation-secretariat-flags automation-secretariat-book-directions">
                            <?php foreach (
                                $bookDirectionLabels
                                as $code => $label
                            ): ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="direction_codes[]"
                                        value="<?= admin_h($code) ?>"
                                        <?= $bookLocked ? 'disabled' : '' ?>
                                        <?= in_array(
                                            $code,
                                            $bookDirectionInput,
                                            true
                                        ) ? ' checked' : '' ?>
                                    >
                                    <?= admin_h($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <small class="admin-muted">
                            می‌توانید بیش از یک نوع را انتخاب کنید.
                            در این حالت همه انواع انتخاب‌شده از همین
                            دفتر و منبع شماره استفاده می‌کنند و توالی
                            شماره میان آن‌ها مشترک خواهد بود.
                        </small>
                    </div>

                    <label>
                        <span>راهبرد شماره‌گذاری</span>
                        <select
                            name="numbering_strategy_code"
                            <?= $bookLocked ? 'disabled' : '' ?>
                        >
                            <option
                                value="dedicated"
                                <?= $selected(
                                    $inputValue(
                                        'numbering_strategy_code',
                                        'dedicated'
                                    ),
                                    'dedicated'
                                ) ?>
                            >اختصاصی</option>

                            <option
                                value="shared"
                                <?= $selected(
                                    $inputValue(
                                        'numbering_strategy_code'
                                    ),
                                    'shared'
                                ) ?>
                            >مشترک</option>
                        </select>
                    </label>

                    <label>
                        <span>کد دفتر</span>
                        <input
                            class="automation-secretariat-code"
                            name="code"
                            maxlength="100"
                            required
                            placeholder="incoming-main"
                            <?= $bookLocked ? 'disabled' : '' ?>
                            value="<?= admin_h($inputValue('code')) ?>"
                        >
                    </label>

                    <label>
                        <span>عنوان دفتر</span>
                        <input
                            name="title"
                            maxlength="255"
                            required
                            placeholder="دفتر وارده دبیرخانه مرکزی"
                            value="<?= admin_h($inputValue('title')) ?>"
                        >
                    </label>
                </div>

                <div class="admin-form-actions">
                    <button
                        class="admin-button"
                        type="submit"
                        <?= (
                            $desks === []
                            || $periods === []
                            || $sequences === []
                        ) ? 'disabled' : '' ?>
                    ><?= $isBookEdit
                        ? 'ذخیره ویرایش'
                        : 'ثبت دفتر' ?></button>

                    <?php if ($isBookEdit): ?>
                        <a
                            class="admin-button"
                            href="/admin/automation/secretariat?section=book"
                        >انصراف از ویرایش</a>
                    <?php endif; ?>
                </div>
            </form>

            <h4 class="automation-secretariat-table-title">
                دفاتر ثبت موجود
            </h4>

            <?php if ($books === []): ?>
                <div class="admin-empty-state">
                    هنوز دفتر ثبتی تعریف نشده است.
                </div>
            <?php else: ?>
                <div class="admin-users-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>کد</th>
                                <th>انواع مکاتبه</th>
                                <th>سازمان</th>
                                <th>دبیرخانه</th>
                                <th>دوره</th>
                                <th>منبع شماره</th>
                                <th>راهبرد</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <?php
                                $bookReference =
                                    trim(
                                        (string) (
                                            $book[
                                                'public_reference'
                                            ]
                                            ?? ''
                                        )
                                    );

                                $bookDirectionCodes =
                                    is_array(
                                        $book[
                                            'direction_codes'
                                        ] ?? null
                                    )
                                        ? $book[
                                            'direction_codes'
                                        ]
                                        : [];

                                if ($bookDirectionCodes === []) {
                                    $legacyBookScope =
                                        (string) (
                                            $book[
                                                'scope_code'
                                            ]
                                            ?? ''
                                        );

                                    if (
                                        $legacyBookScope
                                        === 'general'
                                    ) {
                                        $bookDirectionCodes = [
                                            'incoming',
                                            'outgoing',
                                            'internal',
                                        ];

                                    } elseif (
                                        isset(
                                            $bookDirectionLabels[
                                                $legacyBookScope
                                            ]
                                        )
                                    ) {
                                        $bookDirectionCodes = [
                                            $legacyBookScope,
                                        ];
                                    }
                                }

                                $bookDirectionTitles = [];

                                foreach (
                                    $bookDirectionCodes
                                    as $directionCode
                                ) {
                                    $directionCode =
                                        (string) $directionCode;

                                    if (
                                        isset(
                                            $bookDirectionLabels[
                                                $directionCode
                                            ]
                                        )
                                    ) {
                                        $bookDirectionTitles[] =
                                            $bookDirectionLabels[
                                                $directionCode
                                            ];
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= admin_h($book['title'] ?? '') ?></td>
                                    <td class="automation-secretariat-code"><?= admin_h($book['code'] ?? '') ?></td>
                                    <td><?= admin_h(
                                        $bookDirectionTitles !== []
                                            ? implode(
                                                '، ',
                                                $bookDirectionTitles
                                            )
                                            : '—'
                                    ) ?></td>
                                    <td><?= admin_h($book['organization_title'] ?? '') ?></td>
                                    <td><?= admin_h($book['desk_title'] ?? '') ?></td>
                                    <td><?= admin_h($book['period_title'] ?? '') ?></td>
                                    <td><?= admin_h($book['sequence_title'] ?? '') ?></td>
                                    <td>
                                        <?= ($book['numbering_strategy_code'] ?? '') === 'shared'
                                            ? 'مشترک'
                                            : 'اختصاصی' ?>
                                    </td>

                                    <td>
                                        <?php if ($bookReference !== ''): ?>
                                            <a
                                                class="automation-secretariat-action-button"
                                                href="/admin/automation/secretariat?section=book&amp;edit_book=<?= rawurlencode(
                                                    $bookReference
                                                ) ?>"
                                            >ویرایش</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </details>

    <!-- STEP 5 -->
    <details
        class="automation-secretariat-step"
        <?= $activeSection === 'member' ? 'open' : 'hidden' ?>
    >
        <summary>
            مرحله ۵ ـ اعضای دبیرخانه
        </summary>

        <div class="automation-secretariat-step__body">
            <div class="automation-secretariat-step__head">
                <div>
                    <h3>اعضای عملیاتی دبیرخانه</h3>
                    <p class="admin-muted">
                        عضویت بر مبنای انتصاب سازمانی فعال ثبت می‌شود؛
                        تغییر کاربر یا جایگاه، عضویت دبیرخانه را به حساب
                        کاربری مستقل تبدیل نمی‌کند.
                    </p>
                </div>
            </div>

            <?php if ($desks === []): ?>
                <div class="admin-alert automation-secretariat-prerequisite">
                    ابتدا حداقل یک دبیرخانه تعریف کنید.
                </div>
            <?php elseif ($appointments === []): ?>
                <div class="admin-alert automation-secretariat-prerequisite">
                    هیچ انتصاب سازمانی فعال در دامنه مجاز پیدا نشد.
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="/admin/automation/secretariat/memberships"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($csrf) ?>"
                >

                <div class="admin-form-grid">
                    <label>
                        <span>دبیرخانه</span>

                        <select
                            name="secretariat_desk_reference"
                            required
                        >
                            <?php foreach ($desks as $desk): ?>
                                <option
                                    value="<?= admin_h(
                                        $desk[
                                            'public_reference'
                                        ] ?? ''
                                    ) ?>"
                                    <?= $selected(
                                        $inputValue(
                                            'secretariat_desk_reference'
                                        ),
                                        $desk[
                                            'public_reference'
                                        ] ?? ''
                                    ) ?>
                                >
                                    <?= admin_h(
                                        $desk[
                                            'title_fa'
                                        ] ?? ''
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>انتصاب سازمانی</span>

                        <select
                            name="appointment_reference"
                            required
                        >
                            <?php foreach (
                                $appointments
                                as $appointment
                            ): ?>
                                <option
                                    value="<?= admin_h(
                                        $appointment[
                                            'appointment_reference'
                                        ] ?? ''
                                    ) ?>"
                                    <?= $selected(
                                        $inputValue(
                                            'appointment_reference'
                                        ),
                                        $appointment[
                                            'appointment_reference'
                                        ] ?? ''
                                    ) ?>
                                >
                                    <?= admin_h(
                                        (
                                            $appointment[
                                                'person_name'
                                            ] ?? ''
                                        )
                                        . ' ـ '
                                        . (
                                            $appointment[
                                                'position_title'
                                            ] ?? ''
                                        )
                                        . ' ـ '
                                        . (
                                            $appointment[
                                                'organization_title'
                                            ] ?? ''
                                        )
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>نقش عملیاتی</span>

                        <select
                            name="membership_role_code"
                            required
                        >
                            <option
                                value="operator"
                                <?= $selected(
                                    $inputValue(
                                        'membership_role_code',
                                        'operator'
                                    ),
                                    'operator'
                                ) ?>
                            >اپراتور دبیرخانه</option>

                            <option
                                value="supervisor"
                                <?= $selected(
                                    $inputValue(
                                        'membership_role_code'
                                    ),
                                    'supervisor'
                                ) ?>
                            >سرپرست دبیرخانه</option>
                        </select>
                    </label>
                </div>

                <div class="automation-secretariat-flags">
                    <label>
                        <input
                            type="checkbox"
                            name="is_primary"
                            value="1"
                            <?= $checked(
                                'is_primary',
                                false
                            ) ?>
                        >
                        دبیرخانه اصلی این انتصاب
                    </label>
                </div>

                <p class="admin-muted">
                    «دبیرخانه اصلی» فقط اولویت عملیاتی این انتصاب را
                    مشخص می‌کند و به‌تنهایی مجوز ثبت مکاتبه ایجاد نمی‌کند.
                </p>

                <div class="admin-form-actions">
                    <button
                        class="admin-button"
                        type="submit"
                        <?= (
                            $desks === []
                            || $appointments === []
                        ) ? 'disabled' : '' ?>
                    >ثبت عضویت</button>
                </div>
            </form>

            <h4 class="automation-secretariat-table-title">
                عضویت‌های دبیرخانه
            </h4>

            <?php if ($memberships === []): ?>
                <div class="admin-empty-state">
                    هنوز هیچ انتصابی عضو دبیرخانه نشده است.
                </div>
            <?php else: ?>
                <div class="admin-users-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>شخص</th>
                                <th>سمت</th>
                                <th>سازمان</th>
                                <th>دبیرخانه</th>
                                <th>نقش</th>
                                <th>اصلی</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach (
                                $memberships
                                as $membership
                            ): ?>
                                <?php
                                $membershipRoleLabels = [
                                    'operator' =>
                                        'اپراتور',
                                    'supervisor' =>
                                        'سرپرست',
                                ];

                                $membershipStatus =
                                    (string) (
                                        $membership[
                                            'status'
                                        ]
                                        ?? ''
                                    );

                                $membershipActive =
                                    $membershipStatus
                                    === 'active';
                                ?>

                                <tr>
                                    <td>
                                        <?= admin_h(
                                            (
                                                $membership[
                                                    'person_name'
                                                ] ?? ''
                                            ) !== ''
                                                ? $membership[
                                                    'person_name'
                                                ]
                                                : $membership[
                                                    'appointment_reference'
                                                ]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= admin_h(
                                            $membership[
                                                'position_title'
                                            ] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= admin_h(
                                            $membership[
                                                'organization_title'
                                            ] ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= admin_h(
                                            $membership[
                                                'desk_title'
                                            ] ?? ''
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= admin_h(
                                            $membershipRoleLabels[
                                                (string) (
                                                    $membership[
                                                        'membership_role_code'
                                                    ]
                                                    ?? ''
                                                )
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (
                                            (int) (
                                                $membership[
                                                    'is_primary'
                                                ]
                                                ?? 0
                                            ) === 1
                                        )
                                            ? 'بله'
                                            : 'خیر' ?>
                                    </td>

                                    <td>
                                        <?= $membershipActive
                                            ? 'فعال'
                                            : 'غیرفعال' ?>
                                    </td>

                                    <td>
                                        <?php if ($membershipActive): ?>
                                            <form
                                                class="automation-secretariat-inline-form"
                                                method="post"
                                                action="/admin/automation/secretariat/memberships/deactivate"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="_token"
                                                    value="<?= admin_h(
                                                        $csrf
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="membership_id"
                                                    value="<?= admin_h(
                                                        $membership[
                                                            'id'
                                                        ] ?? ''
                                                    ) ?>"
                                                >

                                                <button
                                                    class="automation-secretariat-action-button"
                                                    type="submit"
                                                >غیرفعال‌سازی</button>
                                            </form>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </details>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const managingOrganization =
        document.querySelector(
            '[data-managing-organization]'
        );

    const orgUnitSelect =
        document.querySelector(
            '[data-org-unit-select]'
        );

    const filterOrgUnits = function () {
        if (!managingOrganization || !orgUnitSelect) {
            return;
        }

        const organizationId =
            managingOrganization.value;

        Array.from(
            orgUnitSelect.options
        ).forEach(function (option) {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            const visible =
                option.dataset.organizationId ===
                organizationId;

            option.hidden = !visible;

            if (
                !visible
                && option.selected
            ) {
                orgUnitSelect.value = '';
            }
        });
    };

    filterOrgUnits();

    if (managingOrganization) {
        managingOrganization.addEventListener(
            'change',
            filterOrgUnits
        );
    }

    document.querySelectorAll(
        '[data-scope-controller]'
    ).forEach(function (controller) {
        const target =
            document.querySelector(
                controller.dataset.scopeTarget
            );

        if (!target) {
            return;
        }

        const update = function () {
            const shared =
                controller.value ===
                controller.dataset.sharedValue;

            target.hidden = shared;
        };

        update();

        controller.addEventListener(
            'change',
            update
        );
    });

    const deskKind =
        document.querySelector(
            '[data-desk-kind]'
        );

    const sharedOrganizations =
        document.querySelector(
            '[data-shared-organizations]'
        );

    if (
        deskKind
        && sharedOrganizations
    ) {
        const updateDeskKind = function () {
            sharedOrganizations.hidden =
                deskKind.value !== 'shared';
        };

        updateDeskKind();

        deskKind.addEventListener(
            'change',
            updateDeskKind
        );
    }
});
</script>

<?php endif; ?>

<?php
$content = ob_get_clean();

require __DIR__ . '/layout.php';
