<?php

declare(strict_types=1);

$title =
    'مدیریت راهنماها و پیام‌های سیستمی';

$escape =
    static fn (
        mixed $value
    ): string =>
        htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES
            | ENT_SUBSTITUTE,
            'UTF-8'
        );

$digits =
    static fn (
        mixed $value
    ): string =>
        \App\Support\AdminFormat::digits(
            $value
        );

$jalaliDateTime =
    static fn (
        mixed $value
    ): string =>
        \App\Support\AdminFormat::jalaliDateTime(
            $value
        );

$page =
    is_array($page ?? null)
        ? $page
        : [];

$items =
    is_array($page['items'] ?? null)
        ? $page['items']
        : [];

$filters =
    is_array($page['filters'] ?? null)
        ? $page['filters']
        : [];

$selected =
    is_array($page['selected'] ?? null)
        ? $page['selected']
        : null;

$overrides =
    is_array($page['overrides'] ?? null)
        ? $page['overrides']
        : [];

$selectedOverride =
    is_array(
        $page['selected_override']
        ?? null
    )
        ? $page['selected_override']
        : null;

$modules =
    is_array($page['modules'] ?? null)
        ? $page['modules']
        : [];

$contentTypes =
    is_array(
        $page['content_types']
        ?? null
    )
        ? $page['content_types']
        : [];

$severityCodes =
    is_array(
        $page['severity_codes']
        ?? null
    )
        ? $page['severity_codes']
        : [];

$layoutVariants =
    is_array(
        $page['layout_variants']
        ?? null
    )
        ? $page['layout_variants']
        : [];

$actionCodes =
    is_array(
        $page['action_codes']
        ?? null
    )
        ? $page['action_codes']
        : [];

$newDefinition =
    (bool) (
        $page['new_definition']
        ?? false
    );

$newOverride =
    (bool) (
        $page['new_override']
        ?? false
    );

$status =
    trim(
        (string) (
            $status
            ?? ''
        )
    );

$statusMessages = [
    'definition_saved' =>
        'تعریف محتوا با موفقیت ذخیره شد.',

    'override_saved' =>
        'متن و تنظیمات محدوده با موفقیت ذخیره شد.',

    'invalid_form' =>
        'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.',

    'save_failed' =>
        'ذخیره انجام نشد. مقادیر فرم، کلیدها و محدوده انتخاب‌شده را بررسی کنید.',
];

$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();

$definition =
    $newDefinition
        ? null
        : $selected;

$definitionKey =
    (string) (
        $definition[
            'content_key'
        ]
        ?? ''
    );

$definitionReference =
    (string) (
        $definition[
            'public_reference'
        ]
        ?? ''
    );

$override =
    $newOverride
        ? null
        : $selectedOverride;

$scopeType =
    (string) (
        $override[
            'scope_type'
        ]
        ?? ''
    );

$scopeMode =
    $scopeType === 'global'
        ? 'global'
        : (
            $scopeType === 'module'
                ? 'module'
                : (
                    $override !== null
                        ? 'fine'
                        : 'global'
                )
        );

$overrideModule =
    (string) (
        $override[
            'module_key'
        ]
        ?? ''
    );

$scopePathJson =
    (string) (
        $override[
            'scope_path_json'
        ]
        ?? ''
    );

$overrideLocale =
    (string) (
        $override['locale']
        ?? $definition[
            'default_locale'
        ]
        ?? 'fa'
    );

$scheduleParts =
    static function (
        mixed $value
    ) use (
        $digits
    ): array {

        $local =
            \IPKF\Support\Clock::formatDateTime(
                $value,
                'Y-m-d H:i'
            )
            ?? '';

        if ($local === '') {
            return [
                'jalali' => '',
                'gregorian' => '',
                'time' => '',
            ];
        }

        $gregorian =
            substr(
                $local,
                0,
                10
            );

        $time =
            substr(
                $local,
                11,
                5
            );

        return [
            'jalali' =>
                \IPKF\Support\PersianDate::fromGregorianDate(
                    $gregorian,
                    true
                ),

            'gregorian' =>
                $gregorian,

            'time' =>
                $digits(
                    $time
                ),
        ];
    };

$startsSchedule =
    $scheduleParts(
        $override[
            'starts_at'
        ]
        ?? null
    );

$endsSchedule =
    $scheduleParts(
        $override[
            'ends_at'
        ]
        ?? null
    );


ob_start();
?>

<style>
.ui-content-layout {
    display: grid;
    grid-template-columns:
        minmax(280px, .85fr)
        minmax(0, 1.65fr);
    gap: 18px;
    align-items: start;
}

.ui-content-list {
    display: grid;
    gap: 8px;
}

.ui-content-item {
    display: block;
    padding: 12px 14px;
    border: 1px solid
        var(--admin-border, #dfe4ea);
    border-radius: 10px;
    color: inherit;
    text-decoration: none;
    background:
        var(--admin-surface, #fff);
}

.ui-content-item:hover,
.ui-content-item.is-active {
    border-color:
        var(--admin-primary, #27845b);
}

.ui-content-code,
.ui-content-scope-code {
    direction: ltr;
    unicode-bidi: plaintext;
    font-family: monospace;
    font-size: .82rem;
    overflow-wrap: anywhere;
}

.ui-content-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px 12px;
    margin-top: 7px;
    font-size: .82rem;
    opacity: .78;
}

.ui-content-filter {
    grid-template-columns:
        minmax(220px, 1.4fr)
        minmax(150px, .8fr)
        minmax(150px, .8fr)
        auto;
    align-items: end;
}

.ui-content-editor {
    display: grid;
    gap: 16px;
}

.ui-content-fields {
    display: grid;
    gap: 13px;
}

.ui-content-fields textarea {
    min-height: 110px;
    resize: vertical;
}

.ui-content-fields
textarea.ui-content-body {
    min-height: 180px;
}

.ui-content-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.ui-content-two-column {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.ui-content-three-column {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.ui-content-note {
    font-size: .84rem;
    opacity: .76;
    line-height: 1.9;
}

.ui-content-code--title {
    direction: rtl;
    font-family: inherit;
    font-size: .9rem;
}

.ui-content-schedule-field {
    display: grid;
    grid-template-columns:
        minmax(0, 1.45fr)
        minmax(110px, .55fr);
    gap: 8px;
    align-items: start;
}

@media (max-width: 640px) {
    .ui-content-schedule-field {
        grid-template-columns: 1fr;
    }
}

.ui-content-divider {
    height: 1px;
    background:
        var(--admin-border, #e3e8e5);
    margin: 6px 0;
}

@media (max-width: 1100px) {
    .ui-content-filter {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .ui-content-three-column {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .ui-content-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .ui-content-filter,
    .ui-content-two-column,
    .ui-content-three-column {
        grid-template-columns: 1fr;
    }
}
</style>


<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/dashboard">
        داشبورد
    </a>

    <span>/</span>

    <a href="/admin/modules/system">
        مدیریت سامانه
    </a>

    <span>/</span>

    <span>
        راهنماها و پیام‌های سیستمی
    </span>
</nav>


<section
    class="admin-module-hub admin-module-hub--green"
>
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html(
            'book-open'
        ) ?>
    </div>

    <div>
        <h2>
            مدیریت راهنما، اعلان و خطا
        </h2>

        <p>
            مدیریت متمرکز متن‌های قابل نمایش
            با پشتیبانی از سطح عمومی، ماژول
            و محدوده‌های تخصصی هر ماژول
        </p>
    </div>
</section>


<?php if (
    $status !== ''
    && isset(
        $statusMessages[$status]
    )
): ?>

    <div class="admin-alert">
        <?= $escape(
            $statusMessages[$status]
        ) ?>
    </div>

<?php endif; ?>


<section class="admin-section">

    <div class="admin-section__header">

        <div>
            <h2>
                جستجو و فیلتر
            </h2>
        </div>

        <a
            href="/admin/system/help-texts?new=1"
            class="admin-btn admin-btn--primary"
        >
            تعریف محتوای جدید
        </a>

    </div>


    <form
        method="get"
        action="/admin/system/help-texts"
        class="admin-form-grid ui-content-filter"
    >

        <div class="admin-field">

            <label for="ui-content-q">
                جستجو
            </label>

            <input
                id="ui-content-q"
                type="search"
                name="q"
                value="<?= $escape(
                    $filters['q']
                    ?? ''
                ) ?>"
                placeholder="کلید یا توضیحات"
            >

        </div>


        <div class="admin-field">

            <label for="ui-content-type">
                نوع محتوا
            </label>

            <select
                id="ui-content-type"
                name="content_type"
            >

                <option value="">
                    همه
                </option>

                <?php foreach (
                    $contentTypes
                    as $code => $label
                ): ?>

                    <option
                        value="<?= $escape(
                            $code
                        ) ?>"
                        <?= (
                            ($filters['type'] ?? '')
                            === $code
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= $escape(
                            $label
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="admin-field">

            <label for="ui-content-status">
                وضعیت
            </label>

            <select
                id="ui-content-status"
                name="content_status"
            >

                <option value="">
                    همه
                </option>

                <option
                    value="active"
                    <?= (
                        ($filters['status'] ?? '')
                        === 'active'
                    )
                        ? 'selected'
                        : ''
                    ?>
                >
                    فعال
                </option>

                <option
                    value="inactive"
                    <?= (
                        ($filters['status'] ?? '')
                        === 'inactive'
                    )
                        ? 'selected'
                        : ''
                    ?>
                >
                    غیرفعال
                </option>

            </select>

        </div>


        <div class="admin-field">

            <label>&nbsp;</label>

            <button
                type="submit"
                class="admin-btn admin-btn--primary"
            >
                اعمال فیلتر
            </button>

        </div>

    </form>

</section>


<div class="ui-content-layout">

    <section class="admin-section">

        <div class="admin-section__header">

            <div>

                <h2>
                    محتواها
                </h2>

                <p class="admin-muted">
                    <?= $escape(
                        $digits(
                            count($items)
                        )
                    ) ?>
                    مورد
                </p>

            </div>

        </div>


        <div class="ui-content-list">

            <?php foreach (
                $items
                as $item
            ): ?>

                <?php

                $itemKey =
                    (string) (
                        $item[
                            'content_key'
                        ]
                        ?? ''
                    );

                $link =
                    '/admin/system/help-texts?'
                    . http_build_query(
                        [
                            'key' =>
                                $itemKey,
                        ],
                        '',
                        '&',
                        PHP_QUERY_RFC3986
                    );

                $isSelected =
                    is_array($definition)
                    && $definitionKey
                        === $itemKey;

                $typeCode =
                    (string) (
                        $item[
                            'content_type'
                        ]
                        ?? ''
                    );

                ?>

                <a
                    href="<?= $escape(
                        $link
                    ) ?>"
                    class="ui-content-item<?= $isSelected ? ' is-active' : '' ?>"
                >

                    <strong>
                        <?= $escape(
                            $contentTypes[
                                $typeCode
                            ]
                            ?? $typeCode
                        ) ?>
                    </strong>

                    <div class="ui-content-code ui-content-code--title">
                        <?= $escape(
                            $item[
                                'display_title'
                            ]
                            ?? $item[
                                'description'
                            ]
                            ?? 'محتوای سیستمی'
                        ) ?>
                    </div>

                    <div class="ui-content-meta">

                        <span>
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
                                : 'غیرفعال'
                            ?>
                        </span>

                        <span>
                            محدوده‌ها:
                            <?= $escape(
                                $digits(
                                    $item[
                                        'override_count'
                                    ]
                                    ?? 0
                                )
                            ) ?>
                        </span>

                        <?php if (
                            !empty(
                                $item[
                                    'http_status'
                                ]
                            )
                        ): ?>

                            <span>
                                کد وضعیت
                                <?= $escape(
                                    $digits(
                                        $item[
                                            'http_status'
                                        ]
                                    )
                                ) ?>
                            </span>

                        <?php endif; ?>

                    </div>

                </a>

            <?php endforeach; ?>


            <?php if (
                $items === []
            ): ?>

                <div class="admin-empty-state">
                    محتوایی با این فیلتر پیدا نشد.
                </div>

            <?php endif; ?>

        </div>

    </section>


    <div class="ui-content-editor">

        <section class="admin-section">

            <div class="admin-section__header">

                <div>
                    <h2>
                        <?= $newDefinition
                            ? 'تعریف محتوای جدید'
                            : 'مشخصات محتوا'
                        ?>
                    </h2>
                </div>

            </div>


            <form
                method="post"
                action="/admin/system/help-texts/definition/save"
                class="ui-content-fields"
            >

                <input
                    type="hidden"
                    name="_token"
                    value="<?= $escape(
                        $csrf
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="definition_reference"
                    value="<?= $escape(
                        $definitionReference
                    ) ?>"
                >


                <div class="ui-content-two-column">

                    <div class="admin-field">

                        <label for="definition-key">
                            کلید یکتا
                        </label>

                        <input
                            id="definition-key"
                            name="content_key"
                            type="text"
                            dir="ltr"
                            required
                            <?= (
                                !$newDefinition
                                && $definitionReference !== ''
                            )
                                ? 'readonly'
                                : ''
                            ?>
                            value="<?= $escape(
                                $definitionKey
                            ) ?>"
                            placeholder="شناسه فنی محتوا"
                        >

                    </div>


                    <div class="admin-field">

                        <label for="definition-type">
                            نوع محتوا
                        </label>

                        <select
                            id="definition-type"
                            name="content_type"
                            required
                        >

                            <?php foreach (
                                $contentTypes
                                as $code => $label
                            ): ?>

                                <option
                                    value="<?= $escape(
                                        $code
                                    ) ?>"
                                    <?= (
                                        (string) (
                                            $definition[
                                                'content_type'
                                            ]
                                            ?? 'guide'
                                        )
                                        === $code
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= $escape(
                                        $label
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>


                <div class="ui-content-two-column">

                    <div class="admin-field">

                        <label for="definition-locale">
                            زبان پیش‌فرض
                        </label>

                        <input
                            type="hidden"
                            name="default_locale"
                            value="fa"
                        >

                        <input
                            id="definition-locale"
                            type="text"
                            value="فارسی"
                            readonly
                            aria-readonly="true"
                        >

                    </div>


                    <div class="admin-field">

                        <label for="definition-http">
                            کد وضعیت وب
                        </label>

                        <input
                            id="definition-http"
                            name="http_status"
                            type="text"
                            inputmode="numeric"
                            data-persian-number-input
                            value="<?= $escape(
                                $digits(
                                    $definition[
                                        'http_status'
                                    ]
                                    ?? ''
                                )
                            ) ?>"
                        >

                        <div class="ui-content-note">
                            فقط برای نوع «خطا» استفاده می‌شود.
                        </div>

                    </div>

                </div>


                <div class="admin-field">

                    <label for="definition-description">
                        توضیحات مدیریتی
                    </label>

                    <textarea
                        id="definition-description"
                        name="description"
                    ><?= $escape(
                        $definition[
                            'description'
                        ]
                        ?? ''
                    ) ?></textarea>

                </div>


                <div class="admin-field">

                    <label for="definition-metadata">
                        اطلاعات تکمیلی فنی
                    </label>

                    <textarea
                        id="definition-metadata"
                        name="metadata_json"
                        dir="ltr"
                        placeholder="{}"
                    ><?= $escape(
                        $definition[
                            'metadata_json'
                        ]
                        ?? ''
                    ) ?></textarea>

                </div>


                <input
                    type="hidden"
                    name="is_active"
                    value="0"
                >


                <label class="admin-field">

                    <span>
                        وضعیت
                    </span>

                    <span>

                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= (
                                $newDefinition
                                || (int) (
                                    $definition[
                                        'is_active'
                                    ]
                                    ?? 0
                                )
                                === 1
                            )
                                ? 'checked'
                                : ''
                            ?>
                        >

                        فعال باشد

                    </span>

                </label>


                <div class="ui-content-actions">

                    <button
                        type="submit"
                        class="admin-btn admin-btn--primary"
                    >
                        ذخیره تعریف
                    </button>

                </div>

            </form>

        </section>


        <?php if (
            is_array($selected)
            && !$newDefinition
        ): ?>

            <section class="admin-section">

                <div class="admin-section__header">

                    <div>

                        <h2>
                            محدوده‌های محتوا
                        </h2>

                        <p class="admin-muted">
                            مقدار خالی یعنی ارث‌بری از
                            محدوده عمومی‌تر.
                        </p>

                    </div>


                    <a
                        href="<?= $escape(
                            '/admin/system/help-texts?'
                            . http_build_query(
                                [
                                    'key' =>
                                        $definitionKey,

                                    'new_override' =>
                                        '1',
                                ],
                                '',
                                '&',
                                PHP_QUERY_RFC3986
                            )
                        ) ?>"
                        class="admin-btn admin-btn--primary"
                    >
                        محدوده جدید
                    </a>

                </div>


                <div class="ui-content-list">

                    <?php foreach (
                        $overrides
                        as $item
                    ): ?>

                        <?php

                        $itemReference =
                            (string) (
                                $item[
                                    'public_reference'
                                ]
                                ?? ''
                            );

                        $overrideLink =
                            '/admin/system/help-texts?'
                            . http_build_query(
                                [
                                    'key' =>
                                        $definitionKey,

                                    'override' =>
                                        $itemReference,
                                ],
                                '',
                                '&',
                                PHP_QUERY_RFC3986
                            );

                        $overrideSelected =
                            is_array($override)
                            && (
                                (string) (
                                    $override[
                                        'public_reference'
                                    ]
                                    ?? ''
                                )
                                === $itemReference
                            );

                        ?>

                        <a
                            href="<?= $escape(
                                $overrideLink
                            ) ?>"
                            class="ui-content-item<?= $overrideSelected ? ' is-active' : '' ?>"
                        >

                            <?php
                            $itemScopeType =
                                (string) (
                                    $item[
                                        'scope_type'
                                    ]
                                    ?? ''
                                );

                            $itemScopeLabel =
                                match (
                                    $itemScopeType
                                ) {
                                    'global' =>
                                        'عمومی',

                                    'module' =>
                                        'ماژول',

                                    default =>
                                        'محدوده تخصصی',
                                };
                            ?>

                            <strong>
                                <?= $escape(
                                    $itemScopeLabel
                                ) ?>
                            </strong>

                            <div class="ui-content-meta">

                                <span>
                                    فارسی
                                </span>

                                <span>
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
                                        : 'غیرفعال'
                                    ?>
                                </span>

                                <?php if (
                                    !empty(
                                        $item[
                                            'updated_at'
                                        ]
                                    )
                                ): ?>

                                    <span>
                                        <?= $escape(
                                            $jalaliDateTime(
                                                $item[
                                                    'updated_at'
                                                ]
                                            )
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </a>

                    <?php endforeach; ?>


                    <?php if (
                        $overrides === []
                    ): ?>

                        <div class="admin-empty-state">
                            هنوز محدوده‌ای برای این محتوا ثبت نشده است.
                        </div>

                    <?php endif; ?>

                </div>

            </section>


            <section class="admin-section">

                <div class="admin-section__header">

                    <div>
                        <h2>
                            <?= (
                                $newOverride
                                || $override === null
                            )
                                ? 'تنظیم محدوده محتوا'
                                : 'ویرایش محدوده محتوا'
                            ?>
                        </h2>
                    </div>

                </div>


                <form
                    method="post"
                    action="/admin/system/help-texts/override/save"
                    class="ui-content-fields"
                >

                    <input
                        type="hidden"
                        name="_token"
                        value="<?= $escape(
                            $csrf
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="definition_key"
                        value="<?= $escape(
                            $definitionKey
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="override_reference"
                        value="<?= $escape(
                            $override[
                                'public_reference'
                            ]
                            ?? ''
                        ) ?>"
                    >


                    <div class="ui-content-three-column">

                        <div class="admin-field">

                            <label for="scope-mode">
                                نوع محدوده
                            </label>

                            <select
                                id="scope-mode"
                                name="scope_mode"
                            >

                                <option
                                    value="global"
                                    <?= $scopeMode === 'global'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    عمومی
                                </option>

                                <option
                                    value="module"
                                    <?= $scopeMode === 'module'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    ماژول
                                </option>

                                <option
                                    value="fine"
                                    <?= $scopeMode === 'fine'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    محدوده تخصصی
                                </option>

                            </select>

                        </div>


                        <div class="admin-field">

                            <label for="override-module">
                                ماژول
                            </label>

                            <select
                                id="override-module"
                                name="module_key"
                            >

                                <option value="">
                                    —
                                </option>

                                <?php foreach (
                                    $modules
                                    as $module
                                ): ?>

                                    <?php

                                    $moduleKey =
                                        (string) (
                                            $module[
                                                'module_key'
                                            ]
                                            ?? ''
                                        );

                                    ?>

                                    <option
                                        value="<?= $escape(
                                            $moduleKey
                                        ) ?>"
                                        <?= (
                                            $overrideModule
                                            === $moduleKey
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= $escape(
                                            $module[
                                                'display_name'
                                            ]
                                            ?? $moduleKey
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="admin-field">

                            <label for="override-locale">
                                زبان
                            </label>

                            <input
                                type="hidden"
                                name="locale"
                                value="fa"
                            >

                            <input
                                id="override-locale"
                                type="text"
                                value="فارسی"
                                readonly
                                aria-readonly="true"
                            >

                        </div>

                    </div>


                    <div class="admin-field">

                        <label for="scope-path">
                            ساختار فنی محدوده تخصصی
                        </label>

                        <textarea
                            id="scope-path"
                            name="scope_path_json"
                            dir="ltr"
                            placeholder='[{"type":"project","reference":"TSP-NEP"},{"type":"portal","reference":"TSPT-..."}]'
                        ><?= $escape(
                            $scopePathJson
                        ) ?></textarea>

                        <div class="ui-content-note">
                            فقط برای «محدوده تخصصی» لازم است.
                        </div>

                    </div>


                    <div class="ui-content-divider"></div>


                    <div class="admin-field">

                        <label for="override-title">
                            عنوان
                        </label>

                        <input
                            id="override-title"
                            name="title"
                            type="text"
                            value="<?= $escape(
                                $override[
                                    'title'
                                ]
                                ?? ''
                            ) ?>"
                        >

                    </div>


                    <div class="admin-field">

                        <label for="override-body">
                            متن
                        </label>

                        <textarea
                            id="override-body"
                            class="ui-content-body"
                            name="body"
                        ><?= $escape(
                            $override[
                                'body'
                            ]
                            ?? ''
                        ) ?></textarea>

                    </div>


                    <div class="ui-content-three-column">

                        <div class="admin-field">

                            <label for="override-icon">
                                شناسه آیکون
                            </label>

                            <input
                                id="override-icon"
                                name="icon_code"
                                type="text"
                                dir="ltr"
                                value="<?= $escape(
                                    $override[
                                        'icon_code'
                                    ]
                                    ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="admin-field">

                            <label for="override-severity">
                                شدت
                            </label>

                            <select
                                id="override-severity"
                                name="severity_code"
                            >

                                <option value="">
                                    ارث‌بری
                                </option>

                                <?php foreach (
                                    $severityCodes
                                    as $code => $label
                                ): ?>

                                    <option
                                        value="<?= $escape(
                                            $code
                                        ) ?>"
                                        <?= (
                                            (string) (
                                                $override[
                                                    'severity_code'
                                                ]
                                                ?? ''
                                            )
                                            === $code
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= $escape(
                                            $label
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="admin-field">

                            <label for="override-layout">
                                چیدمان
                            </label>

                            <select
                                id="override-layout"
                                name="layout_variant"
                            >

                                <option value="">
                                    ارث‌بری
                                </option>

                                <?php foreach (
                                    $layoutVariants
                                    as $code => $label
                                ): ?>

                                    <option
                                        value="<?= $escape(
                                            $code
                                        ) ?>"
                                        <?= (
                                            (string) (
                                                $override[
                                                    'layout_variant'
                                                ]
                                                ?? ''
                                            )
                                            === $code
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= $escape(
                                            $label
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <div class="admin-field">

                        <label for="override-visibility">
                            نمایش
                        </label>

                        <?php
                        $visibility =
                            (string) (
                                $override[
                                    'visibility_mode'
                                ]
                                ?? 'inherit'
                            );
                        ?>

                        <select
                            id="override-visibility"
                            name="visibility_mode"
                        >

                            <option
                                value="inherit"
                                <?= $visibility === 'inherit'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                ارث‌بری
                            </option>

                            <option
                                value="show"
                                <?= $visibility === 'show'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                نمایش
                            </option>

                            <option
                                value="hide"
                                <?= $visibility === 'hide'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                عدم نمایش
                            </option>

                        </select>

                    </div>


                    <div class="ui-content-two-column">

                        <div class="admin-field">

                            <label for="primary-action">
                                اقدام اصلی
                            </label>

                            <select
                                id="primary-action"
                                name="primary_action_code"
                            >

                                <option value="">
                                    بدون اقدام
                                </option>

                                <?php foreach (
                                    $actionCodes
                                    as $code => $label
                                ): ?>

                                    <option
                                        value="<?= $escape(
                                            $code
                                        ) ?>"
                                        <?= (
                                            (string) (
                                                $override[
                                                    'primary_action_code'
                                                ]
                                                ?? ''
                                            )
                                            === $code
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= $escape(
                                            $label
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <input
                                name="primary_action_label"
                                type="text"
                                placeholder="عنوان دکمه"
                                value="<?= $escape(
                                    $override[
                                        'primary_action_label'
                                    ]
                                    ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="admin-field">

                            <label for="secondary-action">
                                اقدام دوم
                            </label>

                            <select
                                id="secondary-action"
                                name="secondary_action_code"
                            >

                                <option value="">
                                    بدون اقدام
                                </option>

                                <?php foreach (
                                    $actionCodes
                                    as $code => $label
                                ): ?>

                                    <option
                                        value="<?= $escape(
                                            $code
                                        ) ?>"
                                        <?= (
                                            (string) (
                                                $override[
                                                    'secondary_action_code'
                                                ]
                                                ?? ''
                                            )
                                            === $code
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= $escape(
                                            $label
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <input
                                name="secondary_action_label"
                                type="text"
                                placeholder="عنوان دکمه"
                                value="<?= $escape(
                                    $override[
                                        'secondary_action_label'
                                    ]
                                    ?? ''
                                ) ?>"
                            >

                        </div>

                    </div>


                    <div class="ui-content-two-column">

                        <div class="admin-field">

                            <label for="starts-at-jalali">
                                شروع نمایش
                            </label>

                            <div class="ui-content-schedule-field">

                                <div
                                    class="admin-persian-date"
                                    data-persian-datepicker
                                >

                                    <input
                                        id="starts-at-jalali"
                                        name="starts_at_jalali"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        data-persian-date-input
                                        placeholder="۱۴۰۵/۰۶/۲۰"
                                        value="<?= $escape(
                                            $startsSchedule[
                                                'jalali'
                                            ]
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="starts_at_date"
                                        data-persian-date-output
                                        value="<?= $escape(
                                            $startsSchedule[
                                                'gregorian'
                                            ]
                                        ) ?>"
                                    >

                                    <button
                                        type="button"
                                        class="admin-persian-date__toggle"
                                        data-persian-date-toggle
                                        aria-label="انتخاب تاریخ شروع"
                                        title="انتخاب تاریخ شمسی"
                                    >📅</button>

                                </div>

                                <input
                                    name="starts_at_time"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    data-persian-number-input
                                    placeholder="ساعت، نمونه: ۰۹:۳۰"
                                    value="<?= $escape(
                                        $startsSchedule[
                                            'time'
                                        ]
                                    ) ?>"
                                >

                            </div>

                        </div>


                        <div class="admin-field">

                            <label for="ends-at-jalali">
                                پایان نمایش
                            </label>

                            <div class="ui-content-schedule-field">

                                <div
                                    class="admin-persian-date"
                                    data-persian-datepicker
                                >

                                    <input
                                        id="ends-at-jalali"
                                        name="ends_at_jalali"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        data-persian-date-input
                                        placeholder="۱۴۰۵/۰۶/۲۰"
                                        value="<?= $escape(
                                            $endsSchedule[
                                                'jalali'
                                            ]
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="ends_at_date"
                                        data-persian-date-output
                                        value="<?= $escape(
                                            $endsSchedule[
                                                'gregorian'
                                            ]
                                        ) ?>"
                                    >

                                    <button
                                        type="button"
                                        class="admin-persian-date__toggle"
                                        data-persian-date-toggle
                                        aria-label="انتخاب تاریخ پایان"
                                        title="انتخاب تاریخ شمسی"
                                    >📅</button>

                                </div>

                                <input
                                    name="ends_at_time"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    data-persian-number-input
                                    placeholder="ساعت، نمونه: ۱۷:۰۰"
                                    value="<?= $escape(
                                        $endsSchedule[
                                            'time'
                                        ]
                                    ) ?>"
                                >

                            </div>

                        </div>

                    </div>


                    <div class="admin-field">

                        <label for="override-metadata">
                            اطلاعات تکمیلی فنی
                        </label>

                        <textarea
                            id="override-metadata"
                            name="metadata_json"
                            dir="ltr"
                            placeholder="{}"
                        ><?= $escape(
                            $override[
                                'metadata_json'
                            ]
                            ?? ''
                        ) ?></textarea>

                    </div>


                    <input
                        type="hidden"
                        name="is_active"
                        value="0"
                    >


                    <label class="admin-field">

                        <span>
                            وضعیت محدوده
                        </span>

                        <span>

                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                <?= (
                                    $override === null
                                    || (int) (
                                        $override[
                                            'is_active'
                                        ]
                                        ?? 0
                                    )
                                    === 1
                                )
                                    ? 'checked'
                                    : ''
                                ?>
                            >

                            فعال باشد

                        </span>

                    </label>


                    <div class="ui-content-actions">

                        <button
                            type="submit"
                            class="admin-btn admin-btn--primary"
                        >
                            ذخیره محدوده
                        </button>

                    </div>

                </form>

            </section>

        <?php endif; ?>

    </div>

</div>

<?php

$content =
    ob_get_clean();

require
    __DIR__
    . '/layout.php';
