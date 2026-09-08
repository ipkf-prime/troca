<?php

declare(strict_types=1);


if (
    !function_exists(
        'ticketing_portal_admin_h'
    )
) {
    function ticketing_portal_admin_h(
        mixed $value
    ): string {

        return
            htmlspecialchars(
                (string) (
                    $value
                    ?? ''
                ),
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8',
                false
            );
    }
}


if (
    !function_exists(
        'ticketing_portal_admin_map'
    )
) {
    function ticketing_portal_admin_map(
        array $rows
    ): array {

        $result = [];


        foreach ($rows as $row) {

            if (!is_array($row)) {
                continue;
            }


            $key =
                trim(
                    (string) (
                        $row[
                            'setting_key'
                        ]
                        ?? ''
                    )
                );


            if ($key === '') {
                continue;
            }


            $result[$key] =
                $row;
        }


        return $result;
    }
}


if (
    !function_exists(
        'ticketing_portal_admin_digits'
    )
) {
    function ticketing_portal_admin_digits(
        mixed $value
    ): string {

        return
            \App\Support\AdminFormat::digits(
                $value
            );
    }
}


if (
    !function_exists(
        'ticketing_portal_admin_scope_label'
    )
) {
    function ticketing_portal_admin_scope_label(
        mixed $value
    ): string {

        return match (
            strtolower(
                trim(
                    (string) $value
                )
            )
        ) {
            'global' =>
                'سراسری',

            'project' =>
                'پروژه',

            'portal' =>
                'پورتال',

            default =>
                trim(
                    (string) $value
                ),
        };
    }
}


if (
    !function_exists(
        'ticketing_portal_admin_jalali_datetime'
    )
) {
    function ticketing_portal_admin_jalali_datetime(
        mixed $value
    ): string {

        if (
            trim(
                (string) (
                    $value
                    ?? ''
                )
            ) === ''
        ) {
            return '';
        }


        return
            \App\Support\AdminFormat::jalaliDateTime(
                $value
            );
    }
}


/*
 * TICKETING_PORTAL_ADMIN_FA_LOCALIZATION_V1
 *
 * - User-facing digits are Persian.
 * - User-facing dates are Jalali.
 * - Generic UI terminology is Persian.
 * - URLs, hostnames, formats and technical identifiers remain Latin.
 */

/*
 * TICKETING_PORTAL_ADMIN_FORMS_V1
 *
 * TICKETING_PORTAL_ADMIN_LAYOUT_COMPOSITION_V1
 *
 * This feature view composes through the shared Admin
 * layout using ob_start()/ob_get_clean()/layout.php.
 */


$page =
    is_array(
        $page
        ?? null
    )
        ? $page
        : [];


$portals =
    is_array(
        $page[
            'portals'
        ]
        ?? null
    )
        ? $page[
            'portals'
        ]
        : [];


$selected =
    is_array(
        $page[
            'selected'
        ]
        ?? null
    )
        ? $page[
            'selected'
        ]
        : [];


$hosts =
    is_array(
        $page[
            'hosts'
        ]
        ?? null
    )
        ? $page[
            'hosts'
        ]
        : [];


$projectBrandRows =
    is_array(
        $page[
            'project_brand_overrides'
        ]
        ?? null
    )
        ? $page[
            'project_brand_overrides'
        ]
        : [];


$portalBrandRows =
    is_array(
        $page[
            'portal_brand_overrides'
        ]
        ?? null
    )
        ? $page[
            'portal_brand_overrides'
        ]
        : [];


$landingSettingRows =
    is_array(
        $page[
            'landing_setting_overrides'
        ]
        ?? null
    )
        ? $page[
            'landing_setting_overrides'
        ]
        : [];


$landingItems =
    is_array(
        $page[
            'landing_item_overrides'
        ]
        ?? null
    )
        ? $page[
            'landing_item_overrides'
        ]
        : [];


$editingItem =
    is_array(
        $page[
            'editing_item'
        ]
        ?? null
    )
        ? $page[
            'editing_item'
        ]
        : [];


$effectivePage =
    is_array(
        $page[
            'effective_page'
        ]
        ?? null
    )
        ? $page[
            'effective_page'
        ]
        : [];


$effectiveTheme =
    is_array(
        $page[
            'effective_theme'
        ]
        ?? null
    )
        ? $page[
            'effective_theme'
        ]
        : [];


$themePresets =
    is_array(
        $page[
            'theme_presets'
        ]
        ?? null
    )
        ? $page[
            'theme_presets'
        ]
        : [];


$effectiveSettings =
    is_array(
        $effectivePage[
            'settings'
        ]
        ?? null
    )
        ? $effectivePage[
            'settings'
        ]
        : [];


$projectBrand =
    ticketing_portal_admin_map(
        $projectBrandRows
    );


$portalBrand =
    ticketing_portal_admin_map(
        $portalBrandRows
    );


$landingSettings =
    ticketing_portal_admin_map(
        $landingSettingRows
    );


$portalReference =
    (string) (
        $selected[
            'portal_reference'
        ]
        ?? ''
    );


$status =
    trim(
        (string) (
            $status
            ?? ''
        )
    );


$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();


$title =
    'مدیریت پورتال‌های تیکتینگ';


$tabs = [
    'overview' =>
        'نمای کلی',

    'host' =>
        'دامنه و میزبان',

    'project-brand' =>
        'برند پروژه',

    'portal-brand' =>
        'برند پورتال',

    'settings' =>
        'تنظیمات صفحه',

    'items' =>
        'محتوای صفحه',
];


$activeTab =
    trim(
        (string) (
            $_GET[
                'tab'
            ]
            ?? 'overview'
        )
    );


if (
    !isset(
        $tabs[
            $activeTab
        ]
    )
) {
    $activeTab =
        'overview';
}


if ($editingItem !== []) {
    $activeTab =
        'items';
}


$primaryHost =
    null;


foreach ($hosts as $host) {

    if (
        (string) (
            $host[
                'status'
            ]
            ?? ''
        )
        === 'active'
    ) {
        $primaryHost =
            $host;

        break;
    }
}


if (
    !is_array(
        $primaryHost
    )
    &&
    isset(
        $hosts[0]
    )
    &&
    is_array(
        $hosts[0]
    )
) {
    $primaryHost =
        $hosts[0];
}


$previewUrl =
    is_array(
        $primaryHost
    )
        ? (
            (
                (int) (
                    $primaryHost[
                        'requires_https'
                    ]
                    ?? 1
                )
                === 1
            )
                ? 'https://'
                : 'http://'
        )
        . (string) (
            $primaryHost[
                'hostname'
            ]
            ?? ''
        )
        . '/'
        : '';


$notices = [
    'host_saved' =>
        [
            'ok',
            'تنظیم Host ذخیره شد.',
        ],

    'brand_saved' =>
        [
            'ok',
            'تنظیمات برند ذخیره شد.',
        ],

    'settings_saved' =>
        [
            'ok',
            'تنظیمات اختصاصی صفحه ذخیره شد.',
        ],

    'item_saved' =>
        [
            'ok',
            'تنظیم اختصاصی محتوای صفحه ذخیره شد.',
        ],

    'item_deleted' =>
        [
            'ok',
            'تنظیم اختصاصی حذف شد و ارث‌بری مجدداً برقرار شد.',
        ],

    'invalid_csrf' =>
        [
            'error',
            'نشست فرم معتبر نیست. صفحه را تازه‌سازی کنید.',
        ],

    'portal_management_forbidden' =>
        [
            'error',
            'دسترسی لازم برای مدیریت پورتال وجود ندارد.',
        ],

    'portal_hostname_invalid' =>
        [
            'error',
            'Host واردشده معتبر نیست.',
        ],

    'portal_theme_preset_invalid' =>
        [
            'error',
            'پوسته انتخاب‌شده معتبر نیست.',
        ],

    'portal_item_code_invalid' =>
        [
            'error',
            'کد آیتم معتبر نیست.',
        ],

    'portal_item_title_invalid' =>
        [
            'error',
            'عنوان آیتم معتبر نیست.',
        ],

    'portal_url_invalid' =>
        [
            'error',
            'آدرس واردشده معتبر نیست.',
        ],

    'portal_media_infected' =>
        [
            'error',
            'فایل بارگذاری‌شده توسط آنتی‌ویروس رد شد.',
        ],

    'portal_media_scan_failed' =>
        [
            'error',
            'اسکن امنیتی فایل انجام نشد؛ فایل ذخیره نشد.',
        ],
];


$notice =
    (
        $status !== ''
        &&
        isset(
            $notices[
                $status
            ]
        )
    )
        ? $notices[
            $status
        ]
        : null;


$brandLabels = [
    'active_preset' =>
        'پوسته',

    'brand_name' =>
        'نام سامانه',

    'brand_subtitle' =>
        'زیرعنوان',

    'logo_url' =>
        'لوگو',

    'footer_text' =>
        'متن فوتر',

    'footer_enabled' =>
        'نمایش فوتر',
];


$landingLabels = [
    'meta_description' =>
        'توضیحات متا',

    'status_text' =>
        'متن وضعیت',

    'show_status' =>
        'نمایش وضعیت',

    'show_version' =>
        'نمایش نسخه',

    'show_deploy_date' =>
        'نمایش تاریخ استقرار',

    'show_register' =>
        'نمایش ثبت‌نام',

    'login_label' =>
        'عنوان دکمه ورود',

    'register_label' =>
        'عنوان دکمه ثبت‌نام',

    'register_title' =>
        'عنوان بخش ثبت‌نام',

    'register_url' =>
        'آدرس ثبت‌نام',

    'runtime_status_position' =>
        'جایگاه وضعیت',

    'runtime_online_position' =>
        'جایگاه کاربران آنلاین',

    'runtime_datetime_position' =>
        'جایگاه تاریخ و ساعت',

    'runtime_version_position' =>
        'جایگاه نسخه',

    'runtime_deploy_position' =>
        'جایگاه تاریخ استقرار',
];


$itemTypeLabels = [
    'nav' =>
        'منوی بالا',

    'slide' =>
        'اسلایدر',

    'announcement' =>
        'اطلاعیه',

    'card' =>
        'کارت',

    'footer_link' =>
        'لینک فوتر',
];


$effectiveBrandName =
    (string) (
        $effectiveTheme[
            'brand_name'
        ]
        ?? ''
    );


ob_start();
?>


<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/ticketing">
        تیکتینگ
    </a>

    <span>/</span>

    <span>
        مدیریت پورتال‌ها
    </span>
</nav>


<section class="admin-module-hub admin-module-hub--green">

    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html(
            'globe'
        ) ?>
    </div>

    <div>
        <h2>
            مدیریت پورتال‌های تیکتینگ
        </h2>

        <p>
            مدیریت دامنه، برند، ظاهر و محتوای صفحه عمومی هر پورتال
        </p>
    </div>


    <?php if ($previewUrl !== ''): ?>

        <a
            class="admin-module-hub__back"
            href="<?= ticketing_portal_admin_h(
                $previewUrl
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            مشاهده پورتال
        </a>

    <?php endif; ?>

</section>


<style>
.portal-admin{
    display:grid;
    gap:16px
}
.portal-admin__tabs{
    display:flex;
    flex-wrap:wrap;
    gap:8px
}
.portal-admin__tabs a{
    display:inline-flex;
    min-height:40px;
    align-items:center;
    padding:8px 14px;
    border-radius:11px;
    border:1px solid var(--admin-border,#d9e7df);
    background:#fff;
    color:inherit;
    text-decoration:none;
    font-weight:700
}
.portal-admin__tabs a.is-active{
    background:#e8f6ee;
    border-color:#b8ddc7;
    color:#176b43
}
.portal-admin__summary{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px
}
.portal-admin__summary article{
    padding:14px;
    border:1px solid var(--admin-border,#dce7e1);
    border-radius:14px;
    background:#fff
}
.portal-admin__summary span{
    display:block;
    color:#748491;
    font-size:.78rem;
    margin-bottom:5px
}
.portal-admin__summary strong{
    display:block;
    line-height:1.8
}
.portal-admin__grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px
}
.portal-admin__override{
    display:grid;
    grid-template-columns:34px minmax(160px,.75fr) minmax(0,2fr);
    gap:10px;
    align-items:center;
    padding:8px 0;
    border-bottom:1px solid #edf2ef
}
.portal-admin__table{
    width:100%;
    border-collapse:collapse
}
.portal-admin__table th,
.portal-admin__table td{
    padding:9px 10px;
    text-align:right;
    border-bottom:1px solid #e7eeea;
    vertical-align:top
}
.portal-admin__muted{
    color:#71818e;
    font-size:.8rem
}
.portal-admin__warning{
    padding:12px 14px;
    border:1px solid #efd8a8;
    border-radius:12px;
    background:#fff9ed
}
.portal-admin__actions{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:14px
}
.portal-admin__file-preview{
    margin-top:6px;
    font-size:.78rem;
    color:#71818e;
    word-break:break-all
}.portal-admin__file-control{
    position:relative;
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:8px;
    margin-top:6px
}
.portal-admin__file-input{
    position:absolute;
    width:1px;
    height:1px;
    overflow:hidden;
    opacity:0;
    pointer-events:none
}
.portal-admin__file-button{
    display:inline-flex;
    min-height:36px;
    align-items:center;
    justify-content:center;
    padding:7px 12px;
    border:1px solid #cfe1d7;
    border-radius:9px;
    background:#f4faf6;
    color:#176b43;
    cursor:pointer;
    font-weight:700
}
.portal-admin__file-name{
    color:#71818e;
    font-size:.82rem;
    overflow-wrap:anywhere
}
@media(max-width:900px){
    .portal-admin__summary,
    .portal-admin__grid{
        grid-template-columns:1fr
    }
    .portal-admin__override{
        grid-template-columns:30px 1fr
    }
    .portal-admin__override > :nth-child(3){
        grid-column:2
    }
}
</style>


<div class="portal-admin">

    <?php if (is_array($notice)): ?>

        <div
            class="admin-alert <?= $notice[0] === 'ok'
                ? 'admin-alert--success'
                : 'admin-alert--danger' ?>"
        >
            <?= ticketing_portal_admin_h(
                $notice[1]
            ) ?>
        </div>

    <?php endif; ?>


    <section class="admin-card">

        <div class="admin-card-body">

            <form
                method="get"
                action="/admin/ticketing/portals"
                class="admin-form"
            >

                <label>
                    پورتال

                    <select
                        name="portal"
                        onchange="this.form.submit()"
                    >

                        <?php foreach (
                            $portals
                            as $portal
                        ): ?>

                            <option
                                value="<?= ticketing_portal_admin_h(
                                    $portal[
                                        'portal_reference'
                                    ]
                                    ?? ''
                                ) ?>"
                                <?= (
                                    (string) (
                                        $portal[
                                            'portal_reference'
                                        ]
                                        ?? ''
                                    )
                                    === $portalReference
                                )
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= ticketing_portal_admin_h(
                                    (
                                        $portal[
                                            'project_title'
                                        ]
                                        ?? ''
                                    )
                                    . ' / '
                                    . (
                                        $portal[
                                            'realm_title'
                                        ]
                                        ?? ''
                                    )
                                    . ' / '
                                    . (
                                        $portal[
                                            'portal_title'
                                        ]
                                        ?? ''
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </label>

            </form>

        </div>

    </section>


    <section class="portal-admin__summary">

        <article>
            <span>پروژه</span>
            <strong>
                <?= ticketing_portal_admin_h(
                    $selected[
                        'project_title'
                    ]
                    ?? ''
                ) ?>
            </strong>
        </article>

        <article>
            <span>قلمرو</span>
            <strong>
                <?= ticketing_portal_admin_h(
                    $selected[
                        'realm_title'
                    ]
                    ?? ''
                ) ?>
            </strong>
        </article>

        <article>
            <span>پورتال</span>
            <strong>
                <?= ticketing_portal_admin_h(
                    $selected[
                        'portal_title'
                    ]
                    ?? ''
                ) ?>
            </strong>
        </article>

        <article>
            <span>برند مؤثر</span>
            <strong>
                <?= ticketing_portal_admin_h(
                    $effectiveBrandName
                ) ?>
            </strong>
        </article>

        <article>
            <span>دامنه فعال</span>
            <strong>
                <?= ticketing_portal_admin_h(
                    $primaryHost[
                        'hostname'
                    ]
                    ?? 'تعریف نشده'
                ) ?>
            </strong>
        </article>

        <article>
            <span>سطح برند مؤثر</span>
            <strong>
                <?= ticketing_portal_admin_h(
                    ticketing_portal_admin_scope_label(
                        $effectiveTheme[
                            'branding_scope'
                        ]
                        ?? 'global'
                    )
                ) ?>
            </strong>
        </article>

    </section>


    <nav class="portal-admin__tabs">

        <?php foreach ($tabs as $key => $label): ?>

            <a
                href="/admin/ticketing/portals?portal=<?= rawurlencode(
                    $portalReference
                ) ?>&tab=<?= rawurlencode(
                    $key
                ) ?>"
                class="<?= $activeTab === $key
                    ? 'is-active'
                    : '' ?>"
            >
                <?= ticketing_portal_admin_h(
                    $label
                ) ?>
            </a>

        <?php endforeach; ?>

    </nav>


    <?php if ($activeTab === 'overview'): ?>

        <section class="admin-card">

            <div class="admin-card-header">
                <h3>وضعیت تنظیمات اختصاصی</h3>
            </div>

            <div class="admin-card-body">

                <div class="portal-admin__summary">

                    <article>
                        <span>برند پروژه</span>
                        <strong>
                            <?= ticketing_portal_admin_digits(
                                count(
                                                                $projectBrandRows
                                                            )
                            ) ?>
                        </strong>
                    </article>

                    <article>
                        <span>برند پورتال</span>
                        <strong>
                            <?= ticketing_portal_admin_digits(
                                count(
                                                                $portalBrandRows
                                                            )
                            ) ?>
                        </strong>
                    </article>

                    <article>
                        <span>تنظیمات اختصاصی Landing</span>
                        <strong>
                            <?= ticketing_portal_admin_digits(
                                count(
                                                                $landingSettingRows
                                                            )
                            ) ?>
                        </strong>
                    </article>

                    <article>
                        <span>محتوای اختصاصی Landing</span>
                        <strong>
                            <?= ticketing_portal_admin_digits(
                                count(
                                                                $landingItems
                                                            )
                            ) ?>
                        </strong>
                    </article>

                </div>

            </div>

        </section>


    <?php elseif ($activeTab === 'host'): ?>

        <section class="admin-card">

            <div class="admin-card-header">
                <h3>دامنه و میزبان</h3>
            </div>

            <div class="admin-card-body">

                <div class="portal-admin__warning">
                    تغییر دامنه یا میزبان می‌تواند دسترسی به صفحه عمومی همین پورتال را تغییر دهد.
                    برای دامنه واقعی محیط توسعه فعلاً مقدار موجود را تغییر ندهید مگر در تست کنترل‌شده.
                </div>


                <form
                    method="post"
                    action="/admin/ticketing/portals/host"
                    class="admin-form"
                >

                    <input
                        type="hidden"
                        name="_token"
                        value="<?= ticketing_portal_admin_h(
                            $csrf
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="portal_reference"
                        value="<?= ticketing_portal_admin_h(
                            $portalReference
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="host_id"
                        value="<?= (int) (
                            $primaryHost[
                                'id'
                            ]
                            ?? 0
                        ) ?>"
                    >


                    <div class="portal-admin__grid">

                        <label>
                            نام دامنه / میزبان

                            <input
                                type="text"
                                name="hostname"
                                required
                                value="<?= ticketing_portal_admin_h(
                                    $primaryHost[
                                        'hostname'
                                    ]
                                    ?? ''
                                ) ?>"
                            >
                        </label>


                        <label>
                            وضعیت

                            <select name="status">
                                <option
                                    value="active"
                                    <?= (
                                        ($primaryHost['status'] ?? 'active')
                                        === 'active'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    فعال
                                </option>

                                <option
                                    value="inactive"
                                    <?= (
                                        ($primaryHost['status'] ?? '')
                                        === 'inactive'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    غیرفعال
                                </option>
                            </select>
                        </label>


                        <label>
                            اولویت میزبان اصلی

                            <input
                                type="text"
                                inputmode="numeric"
                                name="canonical_host_slot"
                                value="<?= ticketing_portal_admin_h(
                                    ticketing_portal_admin_digits(
                                        $primaryHost[
                                            'canonical_host_slot'
                                        ]
                                        ?? '1'
                                    )
                                ) ?>"
                            >
                        </label>


                        <label>
                            <input
                                type="checkbox"
                                name="requires_https"
                                value="1"
                                <?= (
                                    (int) (
                                        $primaryHost[
                                            'requires_https'
                                        ]
                                        ?? 1
                                    )
                                    === 1
                                )
                                    ? 'checked'
                                    : '' ?>
                            >

                            الزام اتصال امن (HTTPS)
                        </label>

                    </div>


                    <div class="portal-admin__actions">

                        <button
                            type="submit"
                            class="admin-button admin-button--primary"
                        >
                            ذخیره دامنه / میزبان
                        </button>

                    </div>

                </form>

            </div>

        </section>


    <?php elseif (
        in_array(
            $activeTab,
            [
                'project-brand',
                'portal-brand',
            ],
            true
        )
    ): ?>

        <?php
        $isProjectBrand =
            $activeTab === 'project-brand';

        $scope =
            $isProjectBrand
                ? 'project'
                : 'portal';

        $brandRows =
            $isProjectBrand
                ? $projectBrand
                : $portalBrand;
        ?>

        <section class="admin-card">

            <div class="admin-card-header">
                <h3>
                    <?= $isProjectBrand
                        ? 'برند پروژه'
                        : 'برند اختصاصی پورتال' ?>
                </h3>
            </div>

            <div class="admin-card-body">

                <form
                    method="post"
                    action="/admin/ticketing/portals/brand"
                    enctype="multipart/form-data"
                    class="admin-form"
                >

                    <input
                        type="hidden"
                        name="_token"
                        value="<?= ticketing_portal_admin_h(
                            $csrf
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="portal_reference"
                        value="<?= ticketing_portal_admin_h(
                            $portalReference
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="brand_scope"
                        value="<?= $scope ?>"
                    >


                    <?php foreach (
                        [
                            'brand_name',
                            'brand_subtitle',
                        ]
                        as $key
                    ): ?>

                        <?php
                        $enabled =
                            isset(
                                $brandRows[
                                    $key
                                ]
                            );

                        $value =
                            $enabled
                                ? (
                                    $brandRows[
                                        $key
                                    ][
                                        'setting_value'
                                    ]
                                    ?? ''
                                )
                                : (
                                    $effectiveTheme[
                                        $key
                                    ]
                                    ?? ''
                                );
                        ?>

                        <div class="portal-admin__override">

                            <input
                                type="checkbox"
                                name="override_keys[]"
                                value="<?= $key ?>"
                                <?= $enabled
                                    ? 'checked'
                                    : '' ?>
                            >

                            <strong>
                                <?= ticketing_portal_admin_h(
                                    $brandLabels[
                                        $key
                                    ]
                                ) ?>
                            </strong>

                            <input
                                type="text"
                                name="<?= $key ?>"
                                value="<?= ticketing_portal_admin_h(
                                    $value
                                ) ?>"
                            >

                        </div>

                    <?php endforeach; ?>


                    <?php
                    $presetEnabled =
                        isset(
                            $brandRows[
                                'active_preset'
                            ]
                        );

                    $currentPreset =
                        $presetEnabled
                            ? (
                                $brandRows[
                                    'active_preset'
                                ][
                                    'setting_value'
                                ]
                                ?? ''
                            )
                            : (
                                $effectiveTheme[
                                    'active_preset'
                                ]
                                ?? ''
                            );
                    ?>

                    <div class="portal-admin__override">

                        <input
                            type="checkbox"
                            name="override_keys[]"
                            value="active_preset"
                            <?= $presetEnabled
                                ? 'checked'
                                : '' ?>
                        >

                        <strong>پوسته</strong>

                        <select name="active_preset">

                            <?php foreach (
                                $themePresets
                                as $presetKey => $preset
                            ): ?>

                                <option
                                    value="<?= ticketing_portal_admin_h(
                                        $presetKey
                                    ) ?>"
                                    <?= (
                                        (string) $currentPreset
                                        === (string) $presetKey
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= ticketing_portal_admin_h(
                                        $preset[
                                            'title'
                                        ]
                                        ?? $presetKey
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <?php
                    $logoEnabled =
                        isset(
                            $brandRows[
                                'logo_url'
                            ]
                        );

                    $logoValue =
                        $logoEnabled
                            ? (
                                $brandRows[
                                    'logo_url'
                                ][
                                    'setting_value'
                                ]
                                ?? ''
                            )
                            : (
                                $effectiveTheme[
                                    'logo_url'
                                ]
                                ?? ''
                            );
                    ?>

                    <div class="portal-admin__override">

                        <input
                            type="checkbox"
                            name="override_keys[]"
                            value="logo_url"
                            <?= $logoEnabled
                                ? 'checked'
                                : '' ?>
                        >

                        <strong>لوگو</strong>

                        <div>

                            <input
                                type="text"
                                name="logo_url"
                                value="<?= ticketing_portal_admin_h(
                                    $logoValue
                                ) ?>"
                                placeholder="/uploads/..."
                            >

                            <div class="portal-admin__file-control">

                                <input
                                    id="portal-brand-logo-file"
                                    class="portal-admin__file-input"
                                    type="file"
                                    name="logo"
                                    accept="image/jpeg,image/png,image/webp"
                                >

                                <label
                                    class="portal-admin__file-button"
                                    for="portal-brand-logo-file"
                                >
                                    انتخاب فایل
                                </label>

                                <span
                                    class="portal-admin__file-name"
                                    data-file-name-for="portal-brand-logo-file"
                                >
                                    فایلی انتخاب نشده است
                                </span>

                            </div>

                            <div class="portal-admin__file-preview">
                                JPEG / PNG / WEBP — حداکثر ۸ مگابایت — اسکن ClamAV
                            </div>

                        </div>

                    </div>


                    <?php
                    $footerTextEnabled =
                        isset(
                            $brandRows[
                                'footer_text'
                            ]
                        );

                    $footerText =
                        $footerTextEnabled
                            ? (
                                $brandRows[
                                    'footer_text'
                                ][
                                    'setting_value'
                                ]
                                ?? ''
                            )
                            : (
                                $effectiveTheme[
                                    'footer_text'
                                ]
                                ?? ''
                            );
                    ?>

                    <div class="portal-admin__override">

                        <input
                            type="checkbox"
                            name="override_keys[]"
                            value="footer_text"
                            <?= $footerTextEnabled
                                ? 'checked'
                                : '' ?>
                        >

                        <strong>متن فوتر</strong>

                        <textarea
                            name="footer_text"
                            rows="3"
                        ><?= ticketing_portal_admin_h(
                            $footerText
                        ) ?></textarea>

                    </div>


                    <?php
                    $footerEnabledOverride =
                        isset(
                            $brandRows[
                                'footer_enabled'
                            ]
                        );

                    $footerEnabledValue =
                        $footerEnabledOverride
                            ? (
                                $brandRows[
                                    'footer_enabled'
                                ][
                                    'setting_value'
                                ]
                                ?? '1'
                            )
                            : (
                                $effectiveTheme[
                                    'footer_enabled'
                                ]
                                ?? '1'
                            );
                    ?>

                    <div class="portal-admin__override">

                        <input
                            type="checkbox"
                            name="override_keys[]"
                            value="footer_enabled"
                            <?= $footerEnabledOverride
                                ? 'checked'
                                : '' ?>
                        >

                        <strong>نمایش فوتر</strong>

                        <label>
                            <input
                                type="checkbox"
                                name="footer_enabled"
                                value="1"
                                <?= (
                                    (string) $footerEnabledValue
                                    === '1'
                                    ||
                                    $footerEnabledValue === true
                                )
                                    ? 'checked'
                                    : '' ?>
                            >

                            فعال
                        </label>

                    </div>


                    <p class="portal-admin__muted">
                        تیک کنار هر گزینه یعنی تنظیم اختصاصی فعال است.
                        برداشتن تیک یعنی بازگشت به مقدار ارث‌بری‌شده.
                    </p>


                    <div class="portal-admin__actions">

                        <button
                            type="submit"
                            class="admin-button admin-button--primary"
                        >
                            ذخیره برند
                        </button>

                    </div>

                </form>

            </div>

        </section>


    <?php elseif ($activeTab === 'settings'): ?>

        <section class="admin-card">

            <div class="admin-card-header">
                <h3>تنظیمات اختصاصی صفحه عمومی</h3>
            </div>

            <div class="admin-card-body">

                <form
                    method="post"
                    action="/admin/ticketing/portals/settings"
                    class="admin-form"
                >

                    <input
                        type="hidden"
                        name="_token"
                        value="<?= ticketing_portal_admin_h(
                            $csrf
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="portal_reference"
                        value="<?= ticketing_portal_admin_h(
                            $portalReference
                        ) ?>"
                    >


                    <?php foreach (
                        $landingLabels
                        as $key => $label
                    ): ?>

                        <?php
                        $enabled =
                            isset(
                                $landingSettings[
                                    $key
                                ]
                            );

                        $value =
                            $enabled
                                ? (
                                    $landingSettings[
                                        $key
                                    ][
                                        'setting_value'
                                    ]
                                    ?? ''
                                )
                                : (
                                    $effectiveSettings[
                                        $key
                                    ]
                                    ?? ''
                                );
                        ?>

                        <div class="portal-admin__override">

                            <input
                                type="checkbox"
                                name="override_keys[]"
                                value="<?= ticketing_portal_admin_h(
                                    $key
                                ) ?>"
                                <?= $enabled
                                    ? 'checked'
                                    : '' ?>
                            >

                            <strong>
                                <?= ticketing_portal_admin_h(
                                    $label
                                ) ?>
                            </strong>


                            <?php if (
                                str_starts_with(
                                    $key,
                                    'show_'
                                )
                            ): ?>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="<?= ticketing_portal_admin_h(
                                            $key
                                        ) ?>"
                                        value="1"
                                        <?= (
                                            (string) $value
                                            === '1'
                                            ||
                                            $value === true
                                        )
                                            ? 'checked'
                                            : '' ?>
                                    >

                                    نمایش
                                </label>


                            <?php elseif (
                                str_starts_with(
                                    $key,
                                    'runtime_'
                                )
                                &&
                                str_ends_with(
                                    $key,
                                    '_position'
                                )
                            ): ?>

                                <select
                                    name="<?= ticketing_portal_admin_h(
                                        $key
                                    ) ?>"
                                >

                                    <?php foreach (
                                        [
                                            'right' => 'راست',
                                            'center' => 'وسط',
                                            'left' => 'چپ',
                                            'hidden' => 'مخفی',
                                        ]
                                        as $position => $positionLabel
                                    ): ?>

                                        <option
                                            value="<?= $position ?>"
                                            <?= (
                                                (string) $value
                                                === $position
                                            )
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $positionLabel ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>


                            <?php elseif (
                                in_array(
                                    $key,
                                    [
                                        'meta_description',
                                        'status_text',
                                    ],
                                    true
                                )
                            ): ?>

                                <textarea
                                    name="<?= ticketing_portal_admin_h(
                                        $key
                                    ) ?>"
                                    rows="3"
                                ><?= ticketing_portal_admin_h(
                                    $value
                                ) ?></textarea>


                            <?php else: ?>

                                <input
                                    type="text"
                                    name="<?= ticketing_portal_admin_h(
                                        $key
                                    ) ?>"
                                    value="<?= ticketing_portal_admin_h(
                                        $value
                                    ) ?>"
                                >

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>


                    <p class="portal-admin__muted">
                        فقط گزینه‌های تیک‌خورده در تیکتینگ ذخیره می‌شوند.
                        سایر مقادیر از صفحه عمومی سراسری هسته ارث می‌برند.
                    </p>


                    <div class="portal-admin__actions">

                        <button
                            type="submit"
                            class="admin-button admin-button--primary"
                        >
                            ذخیره تنظیمات صفحه
                        </button>

                    </div>

                </form>

            </div>

        </section>


    <?php elseif ($activeTab === 'items'): ?>

        <section class="admin-card">

            <div class="admin-card-header">
                <h3>تنظیمات اختصاصی محتوای صفحه</h3>
            </div>

            <div class="admin-card-body">

                <?php if ($landingItems === []): ?>

                    <p class="portal-admin__muted">
                        هنوز تنظیم اختصاصی ثبت نشده و تمام محتوا از هسته ارث می‌رسد.
                    </p>

                <?php else: ?>

                    <table class="portal-admin__table">

                        <thead>
                            <tr>
                                <th>نوع</th>
                                <th>کد</th>
                                <th>حالت</th>
                                <th>عنوان</th>
                                <th>ترتیب</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach (
                                $landingItems
                                as $item
                            ): ?>

                                <tr>
                                    <td>
                                        <?= ticketing_portal_admin_h(
                                            $itemTypeLabels[
                                                (string) (
                                                    $item[
                                                        'item_type'
                                                    ]
                                                    ?? ''
                                                )
                                            ]
                                            ?? (
                                                $item[
                                                    'item_type'
                                                ]
                                                ?? ''
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <code>
                                            <?= ticketing_portal_admin_h(
                                                $item[
                                                    'code'
                                                ]
                                                ?? ''
                                            ) ?>
                                        </code>
                                    </td>

                                    <td>
                                        <?= (
                                            ($item['override_mode'] ?? '')
                                            === 'hide'
                                        )
                                            ? 'مخفی‌سازی'
                                            : 'جایگزینی / افزودن' ?>
                                    </td>

                                    <td>
                                        <?= ticketing_portal_admin_h(
                                            $item[
                                                'title'
                                            ]
                                            ?? ''
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= ticketing_portal_admin_h(
                                            ticketing_portal_admin_digits(
                                                (int) (
                                                    $item[
                                                        'sort_order'
                                                    ]
                                                    ?? 0
                                                )
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (
                                            (int) (
                                                $item[
                                                    'is_active'
                                                ]
                                                ?? 0
                                            )
                                            === 1
                                        )
                                            ? 'فعال'
                                            : 'غیرفعال' ?>
                                    </td>

                                    <td>
                                        <a
                                            href="/admin/ticketing/portals?portal=<?= rawurlencode(
                                                $portalReference
                                            ) ?>&tab=items&edit_id=<?= (int) (
                                                $item[
                                                    'id'
                                                ]
                                                ?? 0
                                            ) ?>"
                                        >
                                            ویرایش
                                        </a>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </section>


        <section class="admin-card">

            <div class="admin-card-header">
                <h3>
                    <?= $editingItem !== []
                        ? 'ویرایش تنظیم اختصاصی'
                        : 'تنظیم اختصاصی جدید' ?>
                </h3>
            </div>

            <div class="admin-card-body">

                <form
                    method="post"
                    action="/admin/ticketing/portals/items"
                    enctype="multipart/form-data"
                    class="admin-form"
                >

                    <input
                        type="hidden"
                        name="_token"
                        value="<?= ticketing_portal_admin_h(
                            $csrf
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="portal_reference"
                        value="<?= ticketing_portal_admin_h(
                            $portalReference
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) (
                            $editingItem[
                                'id'
                            ]
                            ?? 0
                        ) ?>"
                    >


                    <div class="portal-admin__grid">

                        <label>
                            نوع آیتم

                            <select name="item_type">

                                <?php foreach (
                                    $itemTypeLabels
                                    as $key => $label
                                ): ?>

                                    <option
                                        value="<?= $key ?>"
                                        <?= (
                                            ($editingItem['item_type'] ?? 'card')
                                            === $key
                                        )
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= $label ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>
                        </label>


                        <label>
                            کد آیتم

                            <input
                                type="text"
                                name="code"
                                required
                                placeholder="card-main"
                                value="<?= ticketing_portal_admin_h(
                                    $editingItem[
                                        'code'
                                    ]
                                    ?? ''
                                ) ?>"
                            >
                            <small class="portal-admin__muted">
                                شناسه فنی با حروف لاتین؛ مانند:
                                card-main
                            </small>

                        </label>


                        <label>
                            نحوه اعمال

                            <select name="override_mode">
                                <option
                                    value="upsert"
                                    <?= (
                                        ($editingItem['override_mode'] ?? 'upsert')
                                        === 'upsert'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    جایگزینی / افزودن
                                </option>

                                <option
                                    value="hide"
                                    <?= (
                                        ($editingItem['override_mode'] ?? '')
                                        === 'hide'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    مخفی‌سازی آیتم سراسری
                                </option>
                            </select>
                        </label>


                        <label>
                            عنوان

                            <input
                                type="text"
                                name="title"
                                value="<?= ticketing_portal_admin_h(
                                    $editingItem[
                                        'title'
                                    ]
                                    ?? ''
                                ) ?>"
                            >
                        </label>


                        <label>
                            تیتر کوچک

                            <input
                                type="text"
                                name="eyebrow"
                                value="<?= ticketing_portal_admin_h(
                                    $editingItem[
                                        'eyebrow'
                                    ]
                                    ?? ''
                                ) ?>"
                            >
                        </label>


                        <label>
                            آیکن

                            <input
                                type="text"
                                name="icon"
                                value="<?= ticketing_portal_admin_h(
                                    $editingItem[
                                        'icon'
                                    ]
                                    ?? ''
                                ) ?>"
                            >
                        </label>


                        <label>
                            متن دکمه

                            <input
                                type="text"
                                name="action_text"
                                value="<?= ticketing_portal_admin_h(
                                    $editingItem[
                                        'action_text'
                                    ]
                                    ?? ''
                                ) ?>"
                            >
                        </label>


                        <label>
                            لینک دکمه

                            <input
                                type="text"
                                name="action_url"
                                value="<?= ticketing_portal_admin_h(
                                    $editingItem[
                                        'action_url'
                                    ]
                                    ?? ''
                                ) ?>"
                            >
                        </label>


                        <label>
                            نحوه بازشدن لینک

                            <select name="action_target">
                                <option
                                    value="_self"
                                    <?= (
                                        ($editingItem['action_target'] ?? '_self')
                                        === '_self'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    همین صفحه
                                </option>

                                <option
                                    value="_blank"
                                    <?= (
                                        ($editingItem['action_target'] ?? '')
                                        === '_blank'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    صفحه جدید
                                </option>
                            </select>
                        </label>


                        <label>
                            ترتیب

                            <input
                                type="text"
                                inputmode="numeric"
                                name="sort_order"
                                value="<?= ticketing_portal_admin_h(
                                    ticketing_portal_admin_digits(
                                        (int) (
                                            $editingItem[
                                                'sort_order'
                                            ]
                                            ?? 100
                                        )
                                    )
                                ) ?>"
                            >
                        </label>


                        <label>
                            شروع نمایش

                            <input
                                type="text"
                                name="starts_at"
                                inputmode="numeric"
                                dir="ltr"
                                placeholder="۱۴۰۵/۰۶/۱۷ ۱۴:۳۰"
                                value="<?= ticketing_portal_admin_h(
                                    ticketing_portal_admin_jalali_datetime(
                                        $editingItem[
                                            'starts_at'
                                        ]
                                        ?? ''
                                    )
                                ) ?>"
                            >

                            <small class="portal-admin__muted">
                                تاریخ شمسی؛ نمونه:
                                ۱۴۰۵/۰۶/۱۷ ۱۴:۳۰
                            </small>
                        </label>


                        <label>
                            پایان نمایش

                            <input
                                type="text"
                                name="ends_at"
                                inputmode="numeric"
                                dir="ltr"
                                placeholder="۱۴۰۵/۰۶/۱۷ ۱۸:۰۰"
                                value="<?= ticketing_portal_admin_h(
                                    ticketing_portal_admin_jalali_datetime(
                                        $editingItem[
                                            'ends_at'
                                        ]
                                        ?? ''
                                    )
                                ) ?>"
                            >

                            <small class="portal-admin__muted">
                                تاریخ شمسی؛ نمونه:
                                ۱۴۰۵/۰۶/۱۷ ۱۸:۰۰
                            </small>
                        </label>

                    </div>


                    <label>
                        متن

                        <textarea
                            name="body"
                            rows="5"
                        ><?= ticketing_portal_admin_h(
                            $editingItem[
                                'body'
                            ]
                            ?? ''
                        ) ?></textarea>
                    </label>


                    <div class="portal-admin__grid">

                        <label>
                            تصویر

                            <div class="portal-admin__file-control">

                                <input
                                    id="portal-item-image-file"
                                    class="portal-admin__file-input"
                                    type="file"
                                    name="image"
                                    accept="image/jpeg,image/png,image/webp"
                                >

                                <label
                                    class="portal-admin__file-button"
                                    for="portal-item-image-file"
                                >
                                    انتخاب فایل
                                </label>

                                <span
                                    class="portal-admin__file-name"
                                    data-file-name-for="portal-item-image-file"
                                >
                                    فایلی انتخاب نشده است
                                </span>

                            </div>

                            <div class="portal-admin__file-preview">
                                <?= ticketing_portal_admin_h(
                                    $editingItem[
                                        'image_url'
                                    ]
                                    ?? ''
                                ) ?>
                            </div>

                            <label>
                                <input
                                    type="checkbox"
                                    name="clear_image"
                                    value="1"
                                >

                                حذف تصویر فعلی
                            </label>
                        </label>


                        <label>
                            تصویر موبایل

                            <div class="portal-admin__file-control">

                                <input
                                    id="portal-item-mobile-image-file"
                                    class="portal-admin__file-input"
                                    type="file"
                                    name="mobile_image"
                                    accept="image/jpeg,image/png,image/webp"
                                >

                                <label
                                    class="portal-admin__file-button"
                                    for="portal-item-mobile-image-file"
                                >
                                    انتخاب فایل
                                </label>

                                <span
                                    class="portal-admin__file-name"
                                    data-file-name-for="portal-item-mobile-image-file"
                                >
                                    فایلی انتخاب نشده است
                                </span>

                            </div>

                            <div class="portal-admin__file-preview">
                                <?= ticketing_portal_admin_h(
                                    $editingItem[
                                        'mobile_image_url'
                                    ]
                                    ?? ''
                                ) ?>
                            </div>

                            <label>
                                <input
                                    type="checkbox"
                                    name="clear_mobile_image"
                                    value="1"
                                >

                                حذف تصویر موبایل فعلی
                            </label>
                        </label>

                    </div>


                    <label>
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= (
                                $editingItem === []
                                ||
                                (int) (
                                    $editingItem[
                                        'is_active'
                                    ]
                                    ?? 0
                                )
                                === 1
                            )
                                ? 'checked'
                                : '' ?>
                        >

                        فعال
                    </label>


                    <div class="portal-admin__actions">

                        <button
                            type="submit"
                            class="admin-button admin-button--primary"
                        >
                            ذخیره تنظیم اختصاصی
                        </button>


                        <?php if ($editingItem !== []): ?>

                            <a
                                class="admin-button"
                                href="/admin/ticketing/portals?portal=<?= rawurlencode(
                                    $portalReference
                                ) ?>&tab=items"
                            >
                                انصراف از ویرایش
                            </a>

                        <?php endif; ?>

                    </div>

                </form>


                <?php if ($editingItem !== []): ?>

                    <form
                        method="post"
                        action="/admin/ticketing/portals/items/delete"
                        style="margin-top:12px"
                    >

                        <input
                            type="hidden"
                            name="_token"
                            value="<?= ticketing_portal_admin_h(
                                $csrf
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="portal_reference"
                            value="<?= ticketing_portal_admin_h(
                                $portalReference
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) (
                                $editingItem[
                                    'id'
                                ]
                                ?? 0
                            ) ?>"
                        >

                        <button
                            type="submit"
                            class="admin-button admin-button--danger"
                        >
                            حذف تنظیم اختصاصی و بازگشت به ارث‌بری
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </section>

    <?php endif; ?>

</div>


<script>
document.addEventListener(
    'change',
    function (event) {

        const input =
            event.target.closest(
                '.portal-admin__file-input'
            );


        if (!input) {
            return;
        }


        const target =
            document.querySelector(
                '[data-file-name-for="'
                + input.id
                + '"]'
            );


        if (!target) {
            return;
        }


        target.textContent =
            input.files
            && input.files.length > 0
                ? input.files[0].name
                : 'فایلی انتخاب نشده است';
    }
);
</script>

<?php

$content =
    ob_get_clean();

require __DIR__ . '/layout.php';
