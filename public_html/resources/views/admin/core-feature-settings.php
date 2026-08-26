<?php

declare(strict_types=1);

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

$coreFeatures =
    $coreFeatures
    ?? [
        'available' => false,
        'items' => [],
        'icons' => [],
    ];

$status =
    (string) (
        $status
        ?? ''
    );

$error =
    (string) (
        $error
        ?? ''
    );

$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();

ob_start();
?>

<style>
.core-feature-settings-grid {
    display: grid;
    gap: 14px;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
}

.core-feature-settings-card {
    margin: 0;
}

.core-feature-settings-card__head {
    align-items: center;
    display: flex;
    gap: 12px;
    justify-content: space-between;
    margin-bottom: 14px;
}

.core-feature-settings-card__head h3 {
    margin: 0;
}

.core-feature-settings-card__head code {
    direction: ltr;
    font-size: .78rem;
}

.core-feature-settings-color {
    display: grid;
    gap: 6px;
    grid-template-columns: 52px minmax(0, 1fr);
}

.core-feature-settings-color input[type="color"] {
    cursor: pointer;
    height: 42px;
    padding: 3px;
    width: 52px;
}

.core-feature-settings-locked {
    opacity: .72;
}

.core-feature-settings-permissions {
    direction: ltr;
    font-size: .75rem;
    line-height: 1.7;
    word-break: break-word;
}

.core-feature-settings-toggles {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

@media (max-width: 900px) {
    .core-feature-settings-grid {
        grid-template-columns:
            minmax(0, 1fr);
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

    <a href="/admin/settings">
        تنظیمات
    </a>

    <span>/</span>

    <span>
        بخش‌های پنل
    </span>
</nav>

<div class="admin-page">

    <div class="admin-page-header">
        <div>
            <h1>
                تنظیمات بخش‌های داخلی پنل
            </h1>

            <p>
                عنوان، توضیح، آیکون، رنگ و محل نمایش
                بخش‌های داخلی پنل را مدیریت کنید.
            </p>
        </div>
    </div>

    <?php if (
        $status === 'saved'
    ): ?>
        <div
            class="admin-alert admin-alert--success"
        >
            تنظیمات بخش ذخیره شد.
        </div>
    <?php endif; ?>

    <?php if (
        $status === 'invalid'
    ): ?>
        <div
            class="admin-alert admin-alert--danger"
        >
            <?= admin_h(
                $error !== ''
                    ? $error
                    : 'اطلاعات معتبر نیست.'
            ) ?>
        </div>
    <?php endif; ?>

    <?php if (
        ($coreFeatures['available'] ?? false)
        !== true
    ): ?>
        <div
            class="admin-alert admin-alert--warning"
        >
            Metadata لازم برای بخش‌های پنل
            هنوز Migration نشده است.
        </div>
    <?php endif; ?>

    <section class="admin-section">

        <div class="admin-panel-heading">
            <div>
                <h2>
                    بخش‌های داخلی سامانه
                </h2>

                <p>
                    کلید، مسیر و مجوزهای امنیتی قفل هستند.
                    مخفی‌کردن یک بخش فقط Presentation را
                    تغییر می‌دهد و Route یا Permission
                    امنیتی را حذف نمی‌کند.
                </p>
            </div>
        </div>

        <div class="core-feature-settings-grid">

            <?php foreach (
                $coreFeatures['items']
                ?? []
                as $feature
            ): ?>

                <?php
                $permissions =
                    $feature[
                        'permission_codes'
                    ]
                    ?? [];

                $color =
                    (string) (
                        $feature[
                            'color_hex'
                        ]
                        ?? '#2563eb'
                    );
                ?>

                <form
                    class="admin-card core-feature-settings-card"
                    method="post"
                    action="/admin/settings/core-features"
                    data-core-feature-form
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="feature_key"
                        value="<?= admin_h(
                            $feature['item_key']
                            ?? ''
                        ) ?>"
                    >

                    <div
                        class="core-feature-settings-card__head"
                    >
                        <h3>
                            <?= admin_h(
                                $feature['title']
                                ?? ''
                            ) ?>
                        </h3>

                        <code>
                            <?= admin_h(
                                $feature['item_key']
                                ?? ''
                            ) ?>
                        </code>
                    </div>

                    <div class="admin-form-grid">

                        <label>
                            <span>
                                عنوان
                            </span>

                            <input
                                name="title"
                                maxlength="190"
                                required
                                value="<?= admin_h(
                                    $feature['title']
                                    ?? ''
                                ) ?>"
                            >
                        </label>

                        <label>
                            <span>
                                کد آیکون
                            </span>

                            <input
                                name="icon_code"
                                maxlength="60"
                                dir="ltr"
                                list="admin-core-feature-icons"
                                required
                                value="<?= admin_h(
                                    $feature['icon_code']
                                    ?? ''
                                ) ?>"
                            >
                        </label>

                        <label>
                            <span>
                                رنگ
                            </span>

                            <div
                                class="core-feature-settings-color"
                            >
                                <input
                                    type="color"
                                    name="color_code"
                                    value="<?= admin_h(
                                        $color
                                    ) ?>"
                                    data-core-feature-color
                                >

                                <input
                                    type="text"
                                    value="<?= admin_h(
                                        strtoupper($color)
                                    ) ?>"
                                    maxlength="7"
                                    dir="ltr"
                                    spellcheck="false"
                                    autocomplete="off"
                                    data-core-feature-color-text
                                >
                            </div>
                        </label>

                        <label>
                            <span>
                                ترتیب نمایش
                            </span>

                            <input
                                type="number"
                                name="sort_order"
                                min="0"
                                max="10000"
                                value="<?= admin_h(
                                    $feature['sort_order']
                                    ?? 0
                                ) ?>"
                            >
                        </label>

                        <label
                            class="admin-form-grid__wide"
                        >
                            <span>
                                توضیح
                            </span>

                            <textarea
                                name="description"
                                rows="2"
                                maxlength="500"
                            ><?= admin_h(
                                $feature['description']
                                ?? ''
                            ) ?></textarea>
                        </label>

                        <label
                            class="admin-form-grid__wide core-feature-settings-locked"
                        >
                            <span>
                                مسیر سیستمی
                            </span>

                            <input
                                readonly
                                dir="ltr"
                                value="<?= admin_h(
                                    $feature['route_path']
                                    ?? ''
                                ) ?>"
                            >
                        </label>

                        <div
                            class="admin-form-grid__wide core-feature-settings-locked"
                        >
                            <span>
                                مجوزهای سیستمی
                            </span>

                            <div
                                class="core-feature-settings-permissions"
                            >
                                <?= admin_h(
                                    $permissions !== []
                                        ? implode(
                                            ', ',
                                            $permissions
                                        )
                                        : '—'
                                ) ?>
                            </div>
                        </div>

                        <div
                            class="admin-form-grid__wide core-feature-settings-toggles"
                        >
                            <label
                                class="admin-check-field"
                            >
                                <input
                                    type="checkbox"
                                    name="sidebar_enabled"
                                    value="1"
                                    <?= (int) (
                                        $feature['is_active']
                                        ?? 0
                                    ) === 1
                                        ? ' checked'
                                        : '' ?>
                                >

                                <span>
                                    نمایش در سایدبار
                                </span>
                            </label>

                            <label
                                class="admin-check-field"
                            >
                                <input
                                    type="checkbox"
                                    name="dashboard_enabled"
                                    value="1"
                                    <?= (int) (
                                        $feature[
                                            'dashboard_enabled'
                                        ]
                                        ?? 0
                                    ) === 1
                                        ? ' checked'
                                        : '' ?>
                                >

                                <span>
                                    نمایش در داشبورد
                                </span>
                            </label>
                        </div>

                    </div>

                    <div class="admin-form-actions">
                        <button
                            class="admin-button"
                            type="submit"
                        >
                            ذخیره این بخش
                        </button>
                    </div>

                </form>

            <?php endforeach; ?>

        </div>

        <?php if (
            ($coreFeatures['items'] ?? [])
            === []
        ): ?>
            <div class="admin-empty-state">
                بخش قابل مدیریتی پیدا نشد.
            </div>
        <?php endif; ?>

    </section>

</div>

<datalist id="admin-core-feature-icons">
    <?php foreach (
        $coreFeatures['icons']
        ?? []
        as $icon
    ): ?>
        <option
            value="<?= admin_h($icon) ?>"
        ></option>
    <?php endforeach; ?>
</datalist>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {
        document
            .querySelectorAll(
                '[data-core-feature-form]'
            )
            .forEach(function (form) {
                const picker =
                    form.querySelector(
                        '[data-core-feature-color]'
                    );

                const text =
                    form.querySelector(
                        '[data-core-feature-color-text]'
                    );

                if (!picker || !text) {
                    return;
                }

                const normalize =
                    function (value) {
                        const raw =
                            String(
                                value || ''
                            )
                            .trim()
                            .toLowerCase();

                        return (
                            /^#[0-9a-f]{6}$/
                                .test(raw)
                        )
                            ? raw
                            : null;
                    };

                picker.addEventListener(
                    'input',
                    function () {
                        text.value =
                            picker.value
                                .toUpperCase();
                    }
                );

                text.addEventListener(
                    'change',
                    function () {
                        const color =
                            normalize(
                                text.value
                            );

                        if (!color) {
                            text.value =
                                picker.value
                                    .toUpperCase();

                            return;
                        }

                        picker.value = color;

                        text.value =
                            color.toUpperCase();
                    }
                );
            });
    }
);
</script>

<?php
$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';
