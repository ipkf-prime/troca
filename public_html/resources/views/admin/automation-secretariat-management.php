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

    'period_saved' =>
        'دوره ثبت با موفقیت ایجاد شد.',

    'sequence_saved' =>
        'منبع شماره با موفقیت ایجاد شد.',

    'sequence_updated' =>
        'منبع شماره با موفقیت ویرایش شد.',

    'sequence_not_found' =>
        'منبع شماره موردنظر در دامنه مجاز شما پیدا نشد.',

    'book_saved' =>
        'دفتر ثبت با موفقیت ایجاد شد.',

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

        return array_key_exists(
            $name,
            $formInput
        )
            ? ' checked'
            : '';
    };

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
            تعریف دبیرخانه، دوره ثبت، منبع شماره و دفترهای وارده،
            صادره و داخلی در دامنه سازمانی فعال
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

<section class="automation-secretariat-summary">
    <?php foreach ([
        ['دبیرخانه‌ها', count($desks)],
        ['دوره‌های ثبت', count($periods)],
        ['منابع شماره', count($sequences)],
        ['دفاتر ثبت', count($books)],
    ] as [$label, $count]): ?>
        <article class="admin-card">
            <span><?= admin_h($label) ?></span>
            <strong><?= admin_h($digits($count)) ?></strong>
        </article>
    <?php endforeach; ?>
</section>

<section class="admin-section admin-users-panel">
    <div class="admin-section__header">
        <div>
            <h2>راه‌اندازی دبیرخانه</h2>
            <p class="admin-muted">
                ترتیب پیشنهادی: دبیرخانه ← دوره ثبت ← منبع شماره ← دفتر ثبت.
            </p>
        </div>
    </div>

    <!-- STEP 1 -->
    <details
        class="automation-secretariat-step"
        <?= $activeSection === 'desk' ? 'open' : '' ?>
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

            <form
                method="post"
                action="/admin/automation/secretariat/desks"
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
                    >ثبت دبیرخانه</button>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($desks as $desk): ?>
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
        <?= $activeSection === 'period' ? 'open' : '' ?>
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

            <form
                method="post"
                action="/admin/automation/secretariat/periods"
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
                        <select name="organization_id">
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
                                value="<?= admin_h($inputValue('starts_on')) ?>"
                            >

                            <button
                                type="button"
                                class="admin-persian-date__toggle"
                                data-persian-date-toggle
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
                                value="<?= admin_h($inputValue('ends_on')) ?>"
                            >

                            <button
                                type="button"
                                class="admin-persian-date__toggle"
                                data-persian-date-toggle
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
                    >ثبت دوره</button>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periods as $period): ?>
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
        <?= $activeSection === 'sequence' ? 'open' : '' ?>
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
                                        <a
                                            href="/admin/automation/secretariat?section=sequence&amp;edit_sequence=<?= rawurlencode(
                                                (string) (
                                                    $sequence[
                                                        'public_reference'
                                                    ]
                                                    ?? ''
                                                )
                                            ) ?>"
                                        >ویرایش</a>
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
        <?= $activeSection === 'book' ? 'open' : '' ?>
    >
        <summary>
            مرحله ۴ ـ تعریف دفتر ثبت
        </summary>

        <div class="automation-secretariat-step__body">
            <div class="automation-secretariat-step__head">
                <div>
                    <h3>دفتر ثبت</h3>
                    <p class="admin-muted">
                        دفتر وارده، صادره، داخلی یا عمومی را به دبیرخانه،
                        دوره و منبع شماره متصل کنید.
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

            <form
                method="post"
                action="/admin/automation/secretariat/books"
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

                    <label>
                        <span>نوع دفتر</span>
                        <select name="scope_code">
                            <?php foreach ([
                                'incoming' => 'وارده',
                                'outgoing' => 'صادره',
                                'internal' => 'داخلی',
                                'general' => 'عمومی',
                            ] as $code => $label): ?>
                                <option
                                    value="<?= admin_h($code) ?>"
                                    <?= $selected(
                                        $inputValue(
                                            'scope_code',
                                            'incoming'
                                        ),
                                        $code
                                    ) ?>
                                >
                                    <?= admin_h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>راهبرد شماره‌گذاری</span>
                        <select name="numbering_strategy_code">
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
                    >ثبت دفتر</button>
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
                                <th>نوع</th>
                                <th>سازمان</th>
                                <th>دبیرخانه</th>
                                <th>دوره</th>
                                <th>منبع شماره</th>
                                <th>راهبرد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <?php
                                $scopeLabels = [
                                    'incoming' => 'وارده',
                                    'outgoing' => 'صادره',
                                    'internal' => 'داخلی',
                                    'general' => 'عمومی',
                                ];
                                ?>
                                <tr>
                                    <td><?= admin_h($book['title'] ?? '') ?></td>
                                    <td class="automation-secretariat-code"><?= admin_h($book['code'] ?? '') ?></td>
                                    <td><?= admin_h($scopeLabels[$book['scope_code'] ?? ''] ?? ($book['scope_code'] ?? '')) ?></td>
                                    <td><?= admin_h($book['organization_title'] ?? '') ?></td>
                                    <td><?= admin_h($book['desk_title'] ?? '') ?></td>
                                    <td><?= admin_h($book['period_title'] ?? '') ?></td>
                                    <td><?= admin_h($book['sequence_title'] ?? '') ?></td>
                                    <td>
                                        <?= ($book['numbering_strategy_code'] ?? '') === 'shared'
                                            ? 'مشترک'
                                            : 'اختصاصی' ?>
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
