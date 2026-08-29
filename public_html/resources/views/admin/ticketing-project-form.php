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
    $form
    ?? [];

$errors =
    $errors
    ?? [];

$iconOptions =
    $icon_options
    ?? [];

$isEdit =
    $mode === 'edit'
    && is_array($project);

$reference =
    $isEdit
        ? (string) $project[
            'public_reference'
        ]
        : '';

$action =
    $isEdit
        ? '/admin/ticketing/projects/'
            . rawurlencode($reference)
        : '/admin/ticketing/projects';

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
                مشخصات پایه پروژه پشتیبانی
                را تعیین کنید.
            </p>
        </div>
    </div>


    <?php if ($errors !== []): ?>

        <section class="admin-section">
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
                            <?= ticketing_h($error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

    <?php endif; ?>


    <section class="admin-section">

        <form
            method="post"
            action="<?= ticketing_h($action) ?>"
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


            <div class="admin-form-grid">

                <label>
                    <span>
                        عنوان پروژه
                    </span>

                    <input
                        type="text"
                        name="title"
                        maxlength="255"
                        required
                        autofocus
                        value="<?= ticketing_h(
                            $form['title']
                            ?? ''
                        ) ?>"
                        placeholder="مثلاً پشتیبانی سامانه ..."
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
                        maxlength="80"
                        dir="ltr"
                        value="<?= ticketing_h(
                            $form['code']
                            ?? ''
                        ) ?>"
                        placeholder="project-code"
                    >

                    <?php if ($isEdit): ?>
                        <small class="admin-muted">
                            کد پروژه پس از ایجاد
                            قابل تغییر نیست.
                        </small>
                    <?php endif; ?>
                </label>


                <label>
                    <span>
                        آیکون
                    </span>

                    <input
                        type="text"
                        name="icon_code"
                        list="ticketing-project-icon-list"
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
                                value="<?= ticketing_h($icon) ?>"
                            ></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>


                <label>
                    <span>
                        رنگ
                    </span>

                    <input
                        type="color"
                        name="color_code"
                        value="<?= ticketing_h(
                            $form['color_code']
                            ?? '#258843'
                        ) ?>"
                    >
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
                        وضعیت
                    </span>

                    <span>
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= !empty(
                                $form['is_active']
                            )
                                ? ' checked'
                                : '' ?>
                        >
                        پروژه فعال باشد
                    </span>
                </label>


                <label class="admin-form-grid__wide">
                    <span>
                        توضیحات
                    </span>

                    <textarea
                        name="description"
                        rows="5"
                        maxlength="5000"
                        placeholder="توضیحات پروژه پشتیبانی"
                    ><?= ticketing_h(
                        $form['description']
                        ?? ''
                    ) ?></textarea>
                </label>

            </div>


            <div class="admin-form-actions">

                <button
                    class="admin-button"
                    type="submit"
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

    </section>

    <?php if ($isEdit): ?>

        <?php
        require
            __DIR__
            . '/partials/'
            . 'ticketing-project-membership-config.php';
        ?>

    <?php endif; ?>

</div>

<?php

$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';
