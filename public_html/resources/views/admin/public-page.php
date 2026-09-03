<?php

if (!function_exists('landing_admin_h')) {
    function landing_admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$page = is_array($page ?? null) ? $page : [];

$settings = is_array($page['settings'] ?? null)
    ? $page['settings']
    : [];

$identity =
    is_array($page['system_identity'] ?? null)
        ? $page['system_identity']
        : [];

$items = is_array($page['items'] ?? null)
    ? $page['items']
    : [];

$editing = is_array($page['editing'] ?? null)
    ? $page['editing']
    : [];

$status = trim((string) ($status ?? ''));

$csrf = (new \IPKF\Security\Csrf())->token();

$title = 'مدیریت صفحات';

$typeTitles = [
    'slide' => 'اسلایدر',
    'announcement' => 'اطلاعیه‌ها',
    'card' => 'کارت‌ها',
    'nav' => 'منوی بالا',
    'footer_link' => 'فوتر',
];

$tabs = [
    'settings' => 'تنظیمات عمومی',
    'slide' => 'اسلایدر',
    'announcement' => 'اطلاعیه‌ها',
    'card' => 'کارت‌ها',
    'nav' => 'منوی بالا',
    'footer_link' => 'فوتر',
];

$activeTab = trim(
    (string) ($_GET['tab'] ?? 'settings')
);

if (!isset($tabs[$activeTab])) {
    $activeTab = 'settings';
}

if (
    $editing !== []
    && isset(
        $typeTitles[
            (string) ($editing['item_type'] ?? '')
        ]
    )
) {
    $activeTab =
        (string) $editing['item_type'];
}

$isItemTab = isset($typeTitles[$activeTab]);

$visibleItems = [];

if ($isItemTab) {
    foreach ($items as $item) {
        if (
            (string) ($item['item_type'] ?? '')
            === $activeTab
        ) {
            $visibleItems[] = $item;
        }
    }
}

$notices = [
    'settings_saved' => [
        'ok',
        'تنظیمات صفحه عمومی ذخیره شد.',
    ],
    'item_saved' => [
        'ok',
        'آیتم صفحه عمومی ذخیره شد.',
    ],
    'item_deleted' => [
        'ok',
        'آیتم حذف شد.',
    ],
    'invalid_csrf' => [
        'error',
        'نشست فرم معتبر نیست. صفحه را تازه‌سازی کنید.',
    ],
    'landing_item_invalid' => [
        'error',
        'کد یا عنوان آیتم معتبر نیست.',
    ],
    'landing_item_type_invalid' => [
        'error',
        'نوع آیتم معتبر نیست.',
    ],
    'landing_url_invalid' => [
        'error',
        'آدرس واردشده معتبر نیست.',
    ],
    'landing_upload_size_invalid' => [
        'error',
        'حجم تصویر بیشتر از حد مجاز است.',
    ],
    'landing_upload_type_invalid' => [
        'error',
        'فقط JPEG، PNG و WebP مجاز است.',
    ],
];

$showBody = in_array(
    $activeTab,
    [
        'slide',
        'announcement',
        'card',
    ],
    true
);

$showEyebrow =
    $activeTab === 'slide';

$showButton = in_array(
    $activeTab,
    [
        'slide',
        'announcement',
        'card',
    ],
    true
);

$showLink = in_array(
    $activeTab,
    [
        'slide',
        'announcement',
        'card',
        'nav',
        'footer_link',
    ],
    true
);

$showIcon =
    $activeTab === 'card';

$showImages =
    $activeTab === 'slide';

$showSchedule = in_array(
    $activeTab,
    [
        'slide',
        'announcement',
        'card',
    ],
    true
);

ob_start();
?>

<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/dashboard">داشبورد</a>
    <span>/</span>
    <a href="/admin/modules/system">
        مدیریت سامانه
    </a>
    <span>/</span>
    <span>صفحه عمومی</span>
</nav>

<section
    class="admin-module-hub admin-module-hub--green"
>
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html(
            'file-lines'
        ) ?>
    </div>

    <div>
        <h2>مدیریت صفحات</h2>
        <p>
            مدیریت محتوای عمومی،
            اسلایدر، منو و فوتر سامانه
        </p>
    </div>

    <a
        class="admin-module-hub__back"
        href="/"
        target="_blank"
        rel="noopener"
    >
        مشاهده صفحه عمومی
    </a>
</section>

<style>
.public-page-tabs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin:0 0 18px
}
.public-page-tabs a{
    display:inline-flex;
    align-items:center;
    min-height:42px;
    padding:9px 16px;
    border:1px solid #d9e6df;
    border-radius:11px;
    background:#fff;
    color:#52667a;
    font-weight:700;
    text-decoration:none
}
.public-page-tabs a:hover{
    color:#178451;
    border-color:#afd4bf
}
.public-page-tabs a.is-active{
    background:#e5f5eb;
    color:#147545;
    border-color:#b9dec8
}

.landing-admin{
    display:grid;
    gap:18px
}

.la-card{
    background:#fff;
    border:1px solid #dfe9e4;
    border-radius:16px;
    padding:20px
}

.la-card__header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    margin-bottom:18px
}

.la-card__header h2{
    margin:0;
    font-size:1.12rem
}

.la-card__header p{
    margin:5px 0 0;
    color:#718096;
    font-size:.88rem
}

.la-grid{
    display:grid;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap:14px
}

.la-grid-4{
    display:grid;
    grid-template-columns:
        repeat(4,minmax(0,1fr));
    gap:12px
}

.la-field{
    display:grid;
    gap:6px
}

.la-field span{
    font-size:.84rem;
    font-weight:650;
    color:#425466
}

.la-field--wide{
    grid-column:1/-1
}

.la-field input,
.la-field textarea,
.la-field select{
    width:100%;
    min-height:42px;
    padding:9px 11px;
    border:1px solid #cddbd4;
    border-radius:10px;
    background:#fff;
    font:inherit
}

.la-field textarea{
    min-height:92px;
    resize:vertical
}

.la-field input:focus,
.la-field textarea:focus,
.la-field select:focus{
    outline:none;
    border-color:#51a779;
    box-shadow:0 0 0 3px rgba(47,148,91,.08)
}

.la-check{
    display:flex;
    gap:8px;
    align-items:center;
    min-height:42px
}

.la-actions{
    display:flex;
    align-items:center;
    gap:9px;
    flex-wrap:wrap;
    margin-top:16px
}

.la-button{
    border:0;
    border-radius:10px;
    padding:10px 16px;
    background:#238c55;
    color:#fff;
    font:inherit;
    cursor:pointer;
    text-decoration:none
}

.la-button--muted{
    background:#eef3f6;
    color:#475569
}

.la-button--danger{
    background:#fee2e2;
    color:#b91c1c
}

.la-notice{
    padding:12px 15px;
    border-radius:11px
}

.la-notice--ok{
    background:#ecfdf3;
    color:#166534
}

.la-notice--error{
    background:#fef2f2;
    color:#991b1b
}

.la-table-wrap{
    overflow:auto
}

.la-table{
    width:100%;
    border-collapse:collapse;
    min-width:720px
}

.la-table th,
.la-table td{
    padding:11px 10px;
    border-bottom:1px solid #e7efeb;
    text-align:right;
    vertical-align:middle
}

.la-table th{
    background:#f7faf8;
    color:#526174;
    font-size:.82rem
}

.la-table tbody tr:hover{
    background:#fbfdfc
}

.la-empty{
    padding:34px 16px;
    text-align:center;
    color:#789087;
    background:#f9fcfa;
    border-radius:12px
}

@media(max-width:850px){
    .la-grid,
    .la-grid-4{
        grid-template-columns:1fr
    }

    .la-field--wide{
        grid-column:auto
    }

    .public-page-tabs{
        overflow:auto;
        flex-wrap:nowrap;
        padding-bottom:4px
    }

    .public-page-tabs a{
        white-space:nowrap
    }
}

.la-brand-logo__body{
    display:grid;
    grid-template-columns:120px minmax(0,1fr);
    gap:14px;
    align-items:center
}
.la-brand-logo__preview{
    width:112px;
    height:82px;
    display:grid;
    place-items:center;
    overflow:hidden;
    border:1px solid #dbe4df;
    border-radius:12px;
    background:#f8fbf9
}
.la-brand-logo__preview img{
    display:block;
    max-width:84px;
    max-height:60px;
    object-fit:contain
}
.la-brand-logo__picker{
    min-height:82px;
    padding:12px 14px;
    display:grid;
    align-content:center;
    gap:4px;
    border:1px dashed #9fc7b0;
    border-radius:12px;
    background:#f5fbf7;
    cursor:pointer
}
.la-brand-logo__picker small{
    color:#64748b
}
.la-brand-logo__picker input{
    margin-top:7px
}
@media(max-width:700px){
    .la-brand-logo__body{
        grid-template-columns:1fr
    }
}

</style>

<div class="public-page-tabs">
    <?php foreach ($tabs as $tabCode => $tabTitle): ?>
        <a
            href="/admin/public-page?tab=<?= landing_admin_h($tabCode) ?>"
            class="<?= $activeTab === $tabCode
                ? 'is-active'
                : '' ?>"
        >
            <?= landing_admin_h($tabTitle) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="landing-admin">

<?php if (isset($notices[$status])): ?>
    <?php
    [$kind, $message] =
        $notices[$status];
    ?>
    <div
        class="la-notice la-notice--<?= landing_admin_h($kind) ?>"
    >
        <?= landing_admin_h($message) ?>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'settings'): ?>

<section class="la-card">
    <div class="la-card__header">
        <div>
            <h2>هویت سامانه و تنظیمات صفحه عمومی</h2>
            <p>
                نام و فوتر از هویت سراسری سامانه استفاده می‌کنند؛
                سایر گزینه‌ها مخصوص صفحه عمومی هستند.
            </p>
        </div>
    </div>

    <form
        method="post"
        action="/admin/public-page/settings"

        enctype="multipart/form-data">
        <input
            type="hidden"
            name="_token"
            value="<?= landing_admin_h($csrf) ?>"
        >

        <div class="la-grid">
            <label class="la-field">
                <span>نام سامانه</span>
                <input
                    name="brand_name"
                    value="<?= landing_admin_h(
                        $identity['brand_name']
                        ?? ''
                    ) ?>"
                >
            </label>

            <label>
                <span>عنوان زیر نام سامانه</span>
                <input
                    name="brand_subtitle"
                    value="<?= landing_admin_h(
                        $identity['brand_subtitle']
                        ?? 'سامانه یکپارچه خدمات سازمانی'
                    ) ?>"
                >
            </label>

            <div
                class="la-field la-field--wide la-brand-logo"
            >
                <span>لوگوی سامانه</span>

                <div class="la-brand-logo__body">
                    <div
                        class="la-brand-logo__preview"
                    >
                        <img
                            src="<?= landing_admin_h(
                                $identity['logo_url']
                                ?? '/assets/admin/images/logos/default-logo.svg'
                            ) ?>"
                            alt="لوگوی فعلی سامانه"
                        >
                    </div>

                    <label
                        class="la-brand-logo__picker"
                    >
                        <strong>
                            بارگذاری لوگوی جدید
                        </strong>

                        <small>
                            PNG، JPEG یا WebP
                            ـ حداکثر ۸ مگابایت
                        </small>

                        <input
                            type="file"
                            name="brand_logo"
                            accept="image/jpeg,image/png,image/webp"
                        >
                    </label>
                </div>
            </div>

            <label class="la-field">
                <span>متن وضعیت سامانه</span>
                <input
                    name="status_text"
                    value="<?= landing_admin_h(
                        $settings['status_text']
                        ?? ''
                    ) ?>"
                >
            </label>

            <label
                class="la-field la-field--wide"
            >
                <span>
                    توضیح صفحه /
                    Meta Description
                </span>
                <textarea
                    name="meta_description"
                ><?= landing_admin_h(
                    $settings['meta_description']
                    ?? ''
                ) ?></textarea>
            </label>

            <label
                class="la-field la-field--wide"
            >
                <span>متن فوتر سراسری</span>
                <textarea
                    name="footer_text"
                ><?= landing_admin_h(
                    $identity['footer_text']
                    ?? ''
                ) ?></textarea>
            </label>

            <label class="la-check">
                <input
                    type="checkbox"
                    name="footer_enabled"
                    value="1"
                    <?= !empty(
                        $identity['footer_enabled']
                    )
                        ? 'checked'
                        : '' ?>
                >
                <span>
                    نمایش فوتر در سامانه
                </span>
            </label>

            <label class="la-field">
                <span>عنوان ورود</span>
                <input
                    name="login_label"
                    value="<?= landing_admin_h(
                        $settings['login_label']
                        ?? ''
                    ) ?>"
                >
            </label>

            <label class="la-field">
                <span>عنوان ثبت‌نام</span>
                <input
                    name="register_label"
                    value="<?= landing_admin_h(
                        $settings['register_label']
                        ?? ''
                    ) ?>"
                >
            </label>

            <label
                class="la-field la-field--wide"
            >
                <span>مسیر ثبت‌نام</span>
                <input
                    name="register_url"
                    dir="ltr"
                    value="<?= landing_admin_h(
                        $settings['register_url']
                        ?? '/register'
                    ) ?>"
                >
            </label>
        </div>

        <div class="la-actions">
            <?php
            foreach (
                [
                    'show_status' =>
                        'نمایش وضعیت',
                    'show_version' =>
                        'نمایش نسخه',
                    'show_deploy_date' =>
                        'نمایش تاریخ استقرار',
                    'show_register' =>
                        'نمایش دکمه ثبت‌نام',
                ]
                as $key => $label
            ):
            ?>
                <label class="la-check">
                    <input
                        type="checkbox"
                        name="<?= landing_admin_h($key) ?>"
                        value="1"
                        <?= (
                            $settings[$key]
                            ?? '0'
                        ) === '1'
                            ? 'checked'
                            : '' ?>
                    >
                    <span>
                        <?= landing_admin_h($label) ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="la-actions">
            <button
                class="la-button"
                type="submit"
            >
                ذخیره تنظیمات
            </button>
        </div>

        <?php
        $runtimePositionOptions = [
            'right' => 'راست',
            'center' => 'وسط',
            'left' => 'چپ',
            'hidden' => 'عدم نمایش',
        ];

        $runtimePositionFields = [
            'runtime_status_position' =>
                [
                    'وضعیت سامانه',
                    'right',
                ],
            'runtime_online_position' =>
                [
                    'تعداد کاربران آنلاین',
                    'right',
                ],
            'runtime_datetime_position' =>
                [
                    'تاریخ و ساعت',
                    'center',
                ],
            'runtime_version_position' =>
                [
                    'نسخه سامانه',
                    'left',
                ],
            'runtime_deploy_position' =>
                [
                    'تاریخ استقرار',
                    'left',
                ],
        ];
        ?>

        <section
            class="la-runtime-layout"
        >
            <h3>
                چیدمان نوار وضعیت
            </h3>

            <p class="admin-muted">
                برای هر اطلاعات مشخص کنید
                در بخش راست، وسط یا چپ نوار
                نمایش داده شود.
            </p>

            <div class="la-grid">
                <?php foreach (
                    $runtimePositionFields
                    as $field =>
                        [$label, $default]
                ): ?>
                    <label
                        class="la-field"
                    >
                        <span>
                            <?= landing_admin_h(
                                $label
                            ) ?>
                        </span>

                        <select
                            name="<?= landing_admin_h(
                                $field
                            ) ?>"
                        >
                            <?php foreach (
                                $runtimePositionOptions
                                as $value =>
                                    $optionLabel
                            ): ?>
                                <option
                                    value="<?= landing_admin_h(
                                        $value
                                    ) ?>"
                                    <?= (
                                        (
                                            $settings[$field]
                                            ?? $default
                                        ) === $value
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= landing_admin_h(
                                        $optionLabel
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

</form>
</section>

<?php elseif ($isItemTab): ?>

<section class="la-card">
    <div class="la-card__header">
        <div>
            <h2>
                <?= $editing !== []
                    ? 'ویرایش '
                    : 'افزودن ' ?>
                <?= landing_admin_h(
                    $typeTitles[$activeTab]
                ) ?>
            </h2>

            <p>
                مدیریت
                <?= landing_admin_h(
                    $typeTitles[$activeTab]
                ) ?>
                صفحه عمومی
            </p>
        </div>

        <?php if ($editing !== []): ?>
            <a
                class="la-button la-button--muted"
                href="/admin/public-page?tab=<?= landing_admin_h($activeTab) ?>"
            >
                افزودن آیتم جدید
            </a>
        <?php endif; ?>
    </div>

    <form
        method="post"
        action="/admin/public-page/items"
        enctype="multipart/form-data"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= landing_admin_h($csrf) ?>"
        >

        <input
            type="hidden"
            name="_tab"
            value="<?= landing_admin_h($activeTab) ?>"
        >

        <input
            type="hidden"
            name="item_type"
            value="<?= landing_admin_h($activeTab) ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?= (int) (
                $editing['id']
                ?? 0
            ) ?>"
        >

        <div class="la-grid">
            <label class="la-field">
                <span>کد یکتای انگلیسی</span>
                <input
                    name="code"
                    dir="ltr"
                    required
                    pattern="[a-z][a-z0-9_-]{1,79}"
                    value="<?= landing_admin_h(
                        $editing['code']
                        ?? ''
                    ) ?>"
                >
            </label>

            <label class="la-field">
                <span>عنوان</span>
                <input
                    name="title"
                    required
                    value="<?= landing_admin_h(
                        $editing['title']
                        ?? ''
                    ) ?>"
                >
            </label>

            <?php if ($showEyebrow): ?>
                <label class="la-field la-field--wide">
                    <span>تیتر کوچک</span>
                    <input
                        name="eyebrow"
                        value="<?= landing_admin_h(
                            $editing['eyebrow']
                            ?? ''
                        ) ?>"
                    >
                </label>
            <?php endif; ?>

            <?php if ($showBody): ?>
                <label class="la-field la-field--wide">
                    <span>متن</span>
                    <textarea
                        name="body"
                    ><?= landing_admin_h(
                        $editing['body']
                        ?? ''
                    ) ?></textarea>
                </label>
            <?php endif; ?>

            <?php if ($showButton): ?>
                <label class="la-field">
                    <span>متن دکمه</span>
                    <input
                        name="action_text"
                        value="<?= landing_admin_h(
                            $editing['action_text']
                            ?? ''
                        ) ?>"
                    >
                </label>
            <?php endif; ?>

            <?php if ($showLink): ?>
                <label class="la-field">
                    <span>
                        آدرس
                        <?= $activeTab === 'nav'
                            || $activeTab === 'footer_link'
                            ? 'لینک'
                            : 'دکمه / لینک' ?>
                    </span>

                    <input
                        name="action_url"
                        dir="ltr"
                        value="<?= landing_admin_h(
                            $editing['action_url']
                            ?? ''
                        ) ?>"
                    >
                </label>

                <label class="la-field">
                    <span>نحوه باز شدن</span>
                    <select name="action_target">
                        <option
                            value="_self"
                            <?= (
                                $editing[
                                    'action_target'
                                ]
                                ?? '_self'
                            ) === '_self'
                                ? 'selected'
                                : '' ?>
                        >
                            همین صفحه
                        </option>

                        <option
                            value="_blank"
                            <?= (
                                $editing[
                                    'action_target'
                                ]
                                ?? ''
                            ) === '_blank'
                                ? 'selected'
                                : '' ?>
                        >
                            صفحه جدید
                        </option>
                    </select>
                </label>
            <?php endif; ?>

            <?php if ($showIcon): ?>
                <label class="la-field">
                    <span>آیکن</span>
                    <input
                        name="icon"
                        dir="ltr"
                        value="<?= landing_admin_h(
                            $editing['icon']
                            ?? ''
                        ) ?>"
                    >
                </label>
            <?php endif; ?>

            <?php if ($showImages): ?>
                <label class="la-field">
                    <span>تصویر دسکتاپ</span>
                    <input
                        type="file"
                        name="image"
                        accept="
                            image/jpeg,
                            image/png,
                            image/webp
                        "
                    >
                </label>

                <label class="la-field">
                    <span>تصویر موبایل</span>
                    <input
                        type="file"
                        name="mobile_image"
                        accept="
                            image/jpeg,
                            image/png,
                            image/webp
                        "
                    >
                </label>
            <?php endif; ?>
        </div>

        <div
            class="la-grid-4"
            style="margin-top:14px"
        >
            <label class="la-field">
                <span>ترتیب</span>
                <input
                    type="number"
                    name="sort_order"
                    min="0"
                    max="9999"
                    value="<?= (int) (
                        $editing['sort_order']
                        ?? 100
                    ) ?>"
                >
            </label>

            <?php if ($showSchedule): ?>
                <label class="la-field">
                    <span>
                        شروع نمایش - تاریخ شمسی
                    </span>
                    <input
                        name="starts_date"
                        placeholder="۱۴۰۵/۰۶/۱۳"
                        value="<?= landing_admin_h(
                            $editing[
                                'starts_date_form'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>

                <label class="la-field">
                    <span>ساعت شروع</span>
                    <input
                        name="starts_time"
                        placeholder="08:00"
                        value="<?= landing_admin_h(
                            $editing[
                                'starts_time_form'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>

                <label class="la-field">
                    <span>
                        پایان نمایش - تاریخ شمسی
                    </span>
                    <input
                        name="ends_date"
                        placeholder="۱۴۰۵/۰۶/۲۰"
                        value="<?= landing_admin_h(
                            $editing[
                                'ends_date_form'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>

                <label class="la-field">
                    <span>ساعت پایان</span>
                    <input
                        name="ends_time"
                        placeholder="23:59"
                        value="<?= landing_admin_h(
                            $editing[
                                'ends_time_form'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>
            <?php endif; ?>

            <label class="la-check">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    <?= (int) (
                        $editing['is_active']
                        ?? 1
                    ) === 1
                        ? 'checked'
                        : '' ?>
                >
                <span>فعال</span>
            </label>
        </div>

        <div class="la-actions">
            <button
                class="la-button"
                type="submit"
            >
                <?= $editing !== []
                    ? 'ذخیره تغییرات'
                    : 'افزودن' ?>
            </button>
        </div>
    </form>
</section>

<section class="la-card">
    <div class="la-card__header">
        <div>
            <h2>
                فهرست
                <?= landing_admin_h(
                    $typeTitles[$activeTab]
                ) ?>
            </h2>

            <p>
                <?= count($visibleItems) ?>
                آیتم ثبت‌شده
            </p>
        </div>
    </div>

    <?php if ($visibleItems === []): ?>
        <div class="la-empty">
            هنوز آیتمی در این بخش ثبت نشده است.
        </div>
    <?php else: ?>

        <div class="la-table-wrap">
            <table class="la-table">
                <thead>
                <tr>
                    <th>عنوان</th>
                    <th>کد</th>
                    <th>ترتیب</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($visibleItems as $item): ?>
                    <tr>
                        <td>
                            <?= landing_admin_h(
                                $item['title']
                                ?? ''
                            ) ?>
                        </td>

                        <td dir="ltr">
                            <?= landing_admin_h(
                                $item['code']
                                ?? ''
                            ) ?>
                        </td>

                        <td>
                            <?= (int) (
                                $item['sort_order']
                                ?? 0
                            ) ?>
                        </td>

                        <td>
                            <?= (int) (
                                $item['is_active']
                                ?? 0
                            ) === 1
                                ? 'فعال'
                                : 'غیرفعال' ?>
                        </td>

                        <td>
                            <div class="la-actions">
                                <a
                                    class="la-button la-button--muted"
                                    href="/admin/public-page?tab=<?= landing_admin_h($activeTab) ?>&edit_id=<?= (int) $item['id'] ?>"
                                >
                                    ویرایش
                                </a>

                                <form
                                    method="post"
                                    action="/admin/public-page/items/delete"
                                    onsubmit="return confirm('آیتم حذف شود؟');"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        value="<?= landing_admin_h($csrf) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="_tab"
                                        value="<?= landing_admin_h($activeTab) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $item['id'] ?>"
                                    >

                                    <button
                                        class="la-button la-button--danger"
                                        type="submit"
                                    >
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</section>

<?php endif; ?>

</div>

<?php
$content = ob_get_clean();

require __DIR__ . '/layout.php';
?>
