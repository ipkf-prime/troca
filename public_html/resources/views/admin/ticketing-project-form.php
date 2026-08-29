<?php

declare(strict_types=1);

if (!function_exists('ticketing_h')) {
    function ticketing_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$mode =
    $mode
    ?? 'create';

$project =
    $project
    ?? null;

$form =
    is_array(
        $form
        ?? null
    )
        ? $form
        : [];

$errors =
    is_array(
        $errors
        ?? null
    )
        ? $errors
        : [];

$iconOptions =
    is_array(
        $icon_options
        ?? null
    )
        ? $icon_options
        : [];

$isEdit =
    $mode === 'edit'
    &&
    is_array(
        $project
    );

$reference =
    $isEdit
        ? trim(
            (string) (
                $project[
                    'public_reference'
                ]
                ?? ''
            )
        )
        : '';

if (
    $isEdit
    &&
    $reference === ''
) {
    throw new RuntimeException(
        'Project public reference is missing.'
    );
}

$formAction =
    $isEdit
        ? '/admin/ticketing/projects/'
            . rawurlencode(
                $reference
            )
        : '/admin/ticketing/projects';

$activeProjectTab =
    strtolower(
        trim(
            (string) (
                $_GET['tab']
                ?? 'base'
            )
        )
    );

if (
    !$isEdit
    ||
    !in_array(
        $activeProjectTab,
        [
            'base',
            'membership',
        ],
        true
    )
) {
    $activeProjectTab =
        'base';
}

ob_start();
?>

<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/dashboard">
        داشبورد
    </a>

    <span>/</span>

    <a href="/admin/ticketing">
        پشتیبانی و تیکتینگ
    </a>

    <span>/</span>

    <a href="/admin/ticketing/projects">
        پروژه‌های پشتیبانی
    </a>

    <span>/</span>

    <span>
        <?= $isEdit
            ? 'ویرایش پروژه'
            : 'پروژه جدید' ?>
    </span>
</nav>


<div class="admin-page ticketing-page">

    <div class="admin-page-header">
        <div>
            <h1>
                <?= $isEdit
                    ? 'ویرایش پروژه پشتیبانی'
                    : 'ایجاد پروژه پشتیبانی' ?>
            </h1>

            <p>
                <?= $isEdit
                    ? 'مشخصات پروژه و سیاست عضویت کاربران را مدیریت کنید.'
                    : 'مشخصات پایه پروژه پشتیبانی را تعیین کنید.' ?>
            </p>
        </div>
    </div>


    <section
        class="admin-section ticketing-project-workspace"
    >

        <?php if ($isEdit): ?>

            <nav
                class="ticketing-project-tabs"
                role="tablist"
                aria-label="بخش‌های پروژه"
            >
                <a
                    href="/admin/ticketing/projects/<?= ticketing_h(
                        rawurlencode(
                            $reference
                        )
                    ) ?>/edit?tab=base"
                    class="<?= $activeProjectTab === 'base'
                        ? 'is-active'
                        : '' ?>"
                    data-project-tab="base"
                    role="tab"
                    aria-selected="<?= $activeProjectTab === 'base'
                        ? 'true'
                        : 'false' ?>"
                >
                    <strong>
                        مشخصات پروژه
                    </strong>

                    <small>
                        اطلاعات عمومی و وضعیت
                    </small>
                </a>


                <a
                    href="/admin/ticketing/projects/<?= ticketing_h(
                        rawurlencode(
                            $reference
                        )
                    ) ?>/edit?tab=membership"
                    class="<?= $activeProjectTab === 'membership'
                        ? 'is-active'
                        : '' ?>"
                    data-project-tab="membership"
                    role="tab"
                    aria-selected="<?= $activeProjectTab === 'membership'
                        ? 'true'
                        : 'false' ?>"
                >
                    <strong>
                        تنظیمات عضویت
                    </strong>

                    <small>
                        سیاست ورود و فرم عضویت
                    </small>
                </a>
            </nav>

        <?php endif; ?>


        <div
            class="ticketing-project-panel"
            data-project-tab-panel="base"
            <?= $activeProjectTab === 'base'
                ? ''
                : 'hidden' ?>
        >

            <?php if ($errors !== []): ?>

                <div
                    class="admin-alert admin-alert--danger"
                    role="alert"
                >
                    <strong>
                        اطلاعات پروژه ذخیره نشد.
                    </strong>

                    <ul>
                        <?php foreach (
                            $errors
                            as $error
                        ): ?>
                            <li>
                                <?= ticketing_h(
                                    is_scalar($error)
                                        ? $error
                                        : ''
                                ) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            <?php endif; ?>


            <header class="ticketing-project-panel__header">
                <div>
                    <h2>
                        مشخصات پروژه
                    </h2>

                    <p class="admin-muted">
                        اطلاعات عمومی و نحوه نمایش پروژه در سامانه.
                    </p>
                </div>
            </header>


            <form
                method="post"
                action="<?= ticketing_h(
                    $formAction
                ) ?>"
            >

                <input
                    type="hidden"
                    name="_token"
                    value="<?= ticketing_h(
                        (
                            new \IPKF\Security\Csrf()
                        )->token()
                    ) ?>"
                >


                <div class="ticketing-project-form-grid">

                    <label class="ticketing-project-field--wide">
                        <span>
                            عنوان پروژه
                        </span>

                        <input
                            type="text"
                            name="title"
                            maxlength="255"
                            required
                            value="<?= ticketing_h(
                                $form['title']
                                ?? ''
                            ) ?>"
                        >
                    </label>


                    <label>
                        <span>
                            کد پروژه
                        </span>

                        <input
                            type="text"
                            <?= $isEdit
                                ? 'readonly'
                                : 'name="code" required' ?>
                            dir="ltr"
                            maxlength="80"
                            value="<?= ticketing_h(
                                $form['code']
                                ?? ''
                            ) ?>"
                        >

                        <?php if ($isEdit): ?>
                            <small class="admin-muted">
                                کد پروژه پس از ایجاد قابل تغییر نیست.
                            </small>
                        <?php endif; ?>
                    </label>


                    <label>
                        <span>
                            ترتیب نمایش
                        </span>

                        <input
                            type="number"
                            name="sort_order"
                            min="0"
                            max="100000"
                            value="<?= ticketing_h(
                                $form['sort_order']
                                ?? 10
                            ) ?>"
                        >
                    </label>


                    <label>
                        <span>
                            آیکون
                        </span>

                        <input
                            type="text"
                            name="icon_code"
                            list="ticketing-project-icon-list"
                            dir="ltr"
                            maxlength="60"
                            value="<?= ticketing_h(
                                $form['icon_code']
                                ?? 'sitemap'
                            ) ?>"
                        >

                        <datalist
                            id="ticketing-project-icon-list"
                        >
                            <?php foreach (
                                $iconOptions
                                as $icon
                            ): ?>
                                <option
                                    value="<?= ticketing_h(
                                        $icon
                                    ) ?>"
                                ></option>
                            <?php endforeach; ?>
                        </datalist>
                    </label>


                    <label>
                        <span>
                            رنگ
                        </span>

                        <div class="ticketing-project-color-field">
                            <?php
                            $color =
                                preg_match(
                                    '/^#[0-9a-fA-F]{6}$/',
                                    (string) (
                                        $form[
                                            'color_code'
                                        ]
                                        ?? ''
                                    )
                                ) === 1
                                    ? (string) $form[
                                        'color_code'
                                    ]
                                    : '#258843';
                            ?>

                            <input
                                type="color"
                                value="<?= ticketing_h(
                                    $color
                                ) ?>"
                                data-ticketing-color-picker
                                aria-label="رنگ پروژه"
                            >

                            <input
                                type="text"
                                name="color_code"
                                dir="ltr"
                                maxlength="20"
                                value="<?= ticketing_h(
                                    $form[
                                        'color_code'
                                    ]
                                    ?? $color
                                ) ?>"
                                data-ticketing-color-value
                            >
                        </div>
                    </label>


                    <label>
                        <span>
                            وضعیت
                        </span>

                        <span class="ticketing-switch">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                <?= !empty(
                                    $form['is_active']
                                )
                                    ? 'checked'
                                    : '' ?>
                            >

                            <span
                                class="ticketing-switch__track"
                                aria-hidden="true"
                            ></span>

                            <span class="ticketing-switch__label">
                                پروژه فعال باشد
                            </span>
                        </span>
                    </label>


                    <label class="ticketing-project-field--full">
                        <span>
                            توضیحات
                        </span>

                        <textarea
                            name="description"
                            rows="4"
                            maxlength="5000"
                            placeholder="توضیح کوتاه درباره کاربرد پروژه"
                        ><?= ticketing_h(
                            $form[
                                'description'
                            ]
                            ?? ''
                        ) ?></textarea>
                    </label>

                </div>


                <div class="admin-form-actions">
                    <button
                        type="submit"
                        class="admin-button"
                    >
                        ذخیره پروژه
                    </button>

                    <a
                        class="admin-button admin-button--soft"
                        href="/admin/ticketing/projects"
                    >
                        انصراف
                    </a>
                </div>

            </form>

        </div>


        <?php if ($isEdit): ?>

            <div
                class="ticketing-project-panel"
                data-project-tab-panel="membership"
                <?= $activeProjectTab === 'membership'
                    ? ''
                    : 'hidden' ?>
            >
                <?php
                require
                    __DIR__
                    . '/partials/'
                    . 'ticketing-project-membership-config.php';
                ?>
            </div>

        <?php endif; ?>

    </section>

</div>


<style>
.ticketing-project-workspace {
    overflow: hidden;
    padding: 0;
}

.ticketing-project-tabs {
    display: flex;
    gap: .35rem;
    overflow-x: auto;
    padding: .65rem .75rem 0;
    border-bottom: 1px solid #dfe7e2;
    background: #f8fbf9;
}

.ticketing-project-tabs a {
    display: grid;
    gap: .1rem;
    min-width: 175px;
    padding: .62rem .9rem;
    border: 1px solid transparent;
    border-bottom: 0;
    border-radius: 10px 10px 0 0;
    color: #56675d;
    text-decoration: none;
}

.ticketing-project-tabs a strong {
    font-size: .82rem;
}

.ticketing-project-tabs a small {
    color: #7b8981;
    font-size: .65rem;
}

.ticketing-project-tabs a.is-active {
    margin-bottom: -1px;
    border-color: #dfe7e2;
    background: #fff;
    color: #258843;
}

.ticketing-project-panel {
    padding: 1rem;
}

.ticketing-project-panel[hidden] {
    display: none !important;
}

.ticketing-project-panel__header {
    margin-bottom: .85rem;
}

.ticketing-project-panel__header h2,
.ticketing-project-panel__header p {
    margin: 0;
}

.ticketing-project-panel__header h2 {
    margin-bottom: .18rem;
    font-size: .95rem;
}

.ticketing-project-form-grid {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: .75rem;
}

.ticketing-project-form-grid > label {
    display: grid;
    align-content: start;
    gap: .32rem;
    min-width: 0;
    margin: 0;
}

.ticketing-project-form-grid
> label
> span:first-child {
    font-size: .75rem;
    font-weight: 700;
}

.ticketing-project-field--wide {
    grid-column: span 2;
}

.ticketing-project-field--full {
    grid-column: 1 / -1;
}

.ticketing-project-color-field {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    gap: .4rem;
}

.ticketing-project-color-field
input[type="color"] {
    width: 42px;
    height: 39px;
    padding: .2rem;
}

.ticketing-switch {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: .48rem;
    min-height: 39px;
    cursor: pointer;
}

.ticketing-switch > input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.ticketing-switch__track {
    position: relative;
    display: inline-block;
    flex: 0 0 auto;
    width: 38px;
    height: 22px;
    border-radius: 999px;
    background: #cbd5d0;
    transition: .18s ease;
}

.ticketing-switch__track::after {
    content: "";
    position: absolute;
    top: 3px;
    inset-inline-start: 3px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 4px rgba(15, 23, 42, .18);
    transition: .18s ease;
}

.ticketing-switch
> input:checked
+ .ticketing-switch__track {
    background: #258843;
}

.ticketing-switch
> input:checked
+ .ticketing-switch__track::after {
    transform: translateX(-16px);
}

.ticketing-switch__label {
    font-size: .7rem;
    font-weight: 600;
}

@media (max-width: 1100px) {
    .ticketing-project-form-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .ticketing-project-form-grid {
        grid-template-columns: 1fr;
    }

    .ticketing-project-field--wide,
    .ticketing-project-field--full {
        grid-column: auto;
    }
}
</style>


<script>
(() => {
    const picker =
        document.querySelector(
            '[data-ticketing-color-picker]'
        );

    const value =
        document.querySelector(
            '[data-ticketing-color-value]'
        );

    if (!picker || !value) {
        return;
    }

    picker.addEventListener(
        'input',
        () => {
            value.value =
                picker.value;
        }
    );

    value.addEventListener(
        'input',
        () => {
            if (
                /^#[0-9a-fA-F]{6}$/.test(
                    value.value
                )
            ) {
                picker.value =
                    value.value;
            }
        }
    );
})();
</script>

<?php

$content =
    ob_get_clean()
    ?: '';

require __DIR__
    . '/layout.php';
