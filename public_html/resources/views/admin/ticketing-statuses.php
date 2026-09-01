<?php

if (!function_exists('ticketing_h')) {
    function ticketing_h($value): string
    {
        return htmlspecialchars(
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

$page =
    is_array(
        $page
        ?? null
    )
        ? $page
        : [];

$statuses =
    is_array(
        $page['statuses']
        ?? null
    )
        ? $page['statuses']
        : [];

$status =
    trim(
        (string) (
            $status
            ?? ''
        )
    );

$messages = [
    'status_title_updated' =>
        'عنوان وضعیت با موفقیت ذخیره شد.',

    'status_title_required' =>
        'عنوان وضعیت نمی‌تواند خالی باشد.',

    'status_title_too_long' =>
        'عنوان وضعیت بیش از حد مجاز طولانی است.',

    'status_title_persian_required' =>
        'عنوان وضعیت باید یک عنوان فارسی باشد.',

    'status_title_invalid' =>
        'درخواست ویرایش وضعیت معتبر نیست.',

    'status_title_not_found' =>
        'وضعیت مورد نظر پیدا نشد.',

    'status_title_invalid_csrf' =>
        'اعتبار فرم منقضی شده است.',

    'status_title_failed' =>
        'ذخیره عنوان وضعیت انجام نشد.',
];

$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();

$digits =
    static function ($value): string {
        return strtr(
            (string) $value,
            [
                '0' => '۰',
                '1' => '۱',
                '2' => '۲',
                '3' => '۳',
                '4' => '۴',
                '5' => '۵',
                '6' => '۶',
                '7' => '۷',
                '8' => '۸',
                '9' => '۹',
            ]
        );
    };

ob_start();
?>

<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/dashboard">داشبورد</a>
    <span>/</span>

    <a href="/admin/ticketing">
        پشتیبانی و تیکتینگ
    </a>

    <span>/</span>

    <a href="/admin/ticketing/projects">
        پروژه‌های پشتیبانی
    </a>

    <span>/</span>
    <span>عنوان وضعیت‌ها</span>
</nav>


<div class="admin-page ticketing-page">

    <div class="admin-page-header">

        <div>
            <h1>عنوان وضعیت‌های تیکتینگ</h1>

            <p>
                فقط عنوان فارسی قابل ویرایش است.
                کد و منطق چرخه وضعیت تغییر نمی‌کند.
            </p>
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-button admin-button--soft"
                href="/admin/ticketing/projects"
            >
                بازگشت به پروژه‌ها
            </a>
        </div>

    </div>


    <?php if (
        $status !== ''
        &&
        isset(
            $messages[$status]
        )
    ): ?>

        <div
            class="admin-alert <?= $status === 'status_title_updated'
                ? 'admin-alert--success'
                : 'admin-alert--warning' ?>"
        >
            <?= ticketing_h(
                $messages[$status]
            ) ?>
        </div>

    <?php endif; ?>


    <section class="admin-section">

        <div class="admin-section__header">

            <div>
                <h3>وضعیت‌های سیستم</h3>

                <p class="admin-muted">
                    کد، گروه، ترتیب و منطق باز/بسته بودن فقط نمایشی هستند.
                </p>
            </div>

        </div>


        <?php if ($statuses === []): ?>

            <div class="admin-empty-state">
                وضعیت تیکتینگ تعریف نشده است.
            </div>

        <?php else: ?>

            <div class="admin-table-wrap">

                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>کد</th>
                            <th>عنوان فارسی</th>
                            <th>گروه</th>
                            <th>ترتیب</th>
                            <th>چرخه</th>
                            <th>سیستمی</th>
                            <th>فعال</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach (
                        $statuses
                        as $row
                    ): ?>

                        <?php
                        $code =
                            trim(
                                (string) (
                                    $row['code']
                                    ?? ''
                                )
                            );

                        $category =
                            trim(
                                (string) (
                                    $row['category']
                                    ?? ''
                                )
                            );

                        $categoryTitle =
                            match ($category) {
                                'open' =>
                                    'باز',

                                'waiting' =>
                                    'در انتظار',

                                'closed' =>
                                    'بسته',

                                default =>
                                    $category,
                            };

                        $formId =
                            'ticketing-status-title-'
                            . preg_replace(
                                '/[^a-zA-Z0-9_-]+/',
                                '-',
                                $code
                            );
                        ?>

                        <tr>

                            <td>
                                <code dir="ltr">
                                    <?= ticketing_h(
                                        $code
                                    ) ?>
                                </code>
                            </td>

                            <td>

                                <form
                                    id="<?= ticketing_h(
                                        $formId
                                    ) ?>"
                                    method="post"
                                    action="/admin/ticketing/statuses"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        value="<?= ticketing_h(
                                            $csrf
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="code"
                                        value="<?= ticketing_h(
                                            $code
                                        ) ?>"
                                    >

                                    <input
                                        type="text"
                                        name="title"
                                        maxlength="120"
                                        required
                                        value="<?= ticketing_h(
                                            $row['title']
                                            ?? ''
                                        ) ?>"
                                    >
                                </form>

                            </td>

                            <td>
                                <?= ticketing_h(
                                    $categoryTitle
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $digits(
                                        $row['sort_order']
                                        ?? 0
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= !empty(
                                    $row['is_closed']
                                )
                                    ? 'بسته'
                                    : 'باز' ?>
                            </td>

                            <td>
                                <?= !empty(
                                    $row['is_system']
                                )
                                    ? 'بله'
                                    : 'خیر' ?>
                            </td>

                            <td>
                                <?= !empty(
                                    $row['is_active']
                                )
                                    ? 'فعال'
                                    : 'غیرفعال' ?>
                            </td>

                            <td>
                                <button
                                    class="admin-button admin-button--compact"
                                    type="submit"
                                    form="<?= ticketing_h(
                                        $formId
                                    ) ?>"
                                >
                                    ذخیره عنوان
                                </button>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php

$content =
    ob_get_clean();

require __DIR__ . '/layout.php';
