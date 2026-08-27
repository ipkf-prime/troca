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

$directory =
    $directory
    ?? [];

$items =
    $directory['items']
    ?? [];

$coreCandidates =
    $directory['core_candidates']
    ?? [];

$q =
    (string) (
        $directory['q']
        ?? ''
    );

$origin =
    (string) (
        $directory['origin']
        ?? ''
    );

$state =
    (string) (
        $directory['state']
        ?? ''
    );

$coreQ =
    (string) (
        $directory['core_q']
        ?? ''
    );

$total =
    (int) (
        $directory['total']
        ?? count($items)
    );

$errors =
    $errors
    ?? [];

$manualForm =
    $manual_form
    ?? [];

$notice =
    (string) (
        $notice
        ?? ''
    );

$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();

$originTitles = [
    'core' =>
        'عضو سامانه',

    'manual' =>
        'تعریف دستی',

    'import' =>
        'ورود از فایل',
];

$stateTitles = [
    'contact' =>
        'مخاطب',

    'invited' =>
        'دعوت‌شده',

    'linked' =>
        'عضو سامانه',

    'disabled' =>
        'غیرفعال',
];

ob_start();
?>

<style>
.ticketing-success-notice {
    margin: 0 0 18px;
    padding: 11px 14px;
    border: 1px solid #b9dec6;
    border-radius: 12px;
    background: #edf8f1;
    color: #17663b;
}

.ticketing-core-tools {
    display: grid;
    gap: 12px;
}

.ticketing-core-search-row,
.ticketing-core-add-row {
    display: grid;
    grid-template-columns:
        minmax(280px, 1fr)
        auto;
    gap: 10px;
    align-items: end;
}

.ticketing-core-tools label {
    margin: 0;
}

@media (max-width: 800px) {
    .ticketing-core-search-row,
    .ticketing-core-add-row {
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

    <a href="/admin/ticketing">
        پشتیبانی و تیکتینگ
    </a>
    <span>/</span>

    <span>
        مخاطبان تیکتینگ
    </span>
</nav>


<div class="admin-page ticketing-page">

    <div class="admin-page-header">

        <div>
            <h1>
                مخاطبان تیکتینگ
            </h1>

            <p>
                فهرست واحد هویت برای کاربران سامانه،
                مخاطبان مستقل و ورودی‌های گروهی
            </p>
        </div>

        <a
            class="admin-button admin-button--soft"
            href="/admin/ticketing/projects"
        >
            پروژه‌های پشتیبانی
        </a>

    </div>


    <?php if ($notice !== ''): ?>

        <div
            class="ticketing-success-notice"
            role="status"
        >
            <?= ticketing_h($notice) ?>
        </div>

    <?php endif; ?>


    <?php if ($errors !== []): ?>

        <div
            class="admin-alert admin-alert--danger"
            role="alert"
        >
            <?php foreach (
                $errors
                as $error
            ): ?>

                <div>
                    <?= ticketing_h($error) ?>
                </div>

            <?php endforeach; ?>
        </div>

    <?php endif; ?>


    <section class="admin-section">

        <div class="admin-page-header">
            <div>
                <h2>
                    افزودن از کاربران سامانه
                </h2>

                <p>
                    جستجو و انتخاب از میان کاربران فعال سامانه
                </p>
            </div>
        </div>


        <div class="ticketing-core-tools">

            <form
                method="get"
                action="/admin/ticketing/participants"
                class="ticketing-core-search-row"
            >
                <input
                    type="hidden"
                    name="q"
                    value="<?= ticketing_h($q) ?>"
                >

                <input
                    type="hidden"
                    name="origin"
                    value="<?= ticketing_h($origin) ?>"
                >

                <input
                    type="hidden"
                    name="state"
                    value="<?= ticketing_h($state) ?>"
                >

                <label>
                    <span>
                        جستجوی کاربران سامانه
                    </span>

                    <input
                        type="search"
                        name="core_q"
                        maxlength="120"
                        value="<?= ticketing_h($coreQ) ?>"
                        placeholder="نام و نام خانوادگی، نام کاربری، موبایل یا ایمیل"
                    >
                </label>

                <button
                    class="admin-button admin-button--soft"
                    type="submit"
                >
                    جستجو
                </button>
            </form>


            <form
                method="post"
                action="/admin/ticketing/participants/core"
                class="ticketing-core-add-row"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= ticketing_h($csrf) ?>"
                >

                <label>
                    <span>
                        کاربر سامانه
                    </span>

                    <select
                        name="user_id"
                        <?= $coreCandidates === []
                            ? ' disabled'
                            : ' required' ?>
                    >

                        <?php if (
                            $coreCandidates === []
                        ): ?>

                            <option value="">
                                کاربر دیگری برای افزودن یافت نشد
                            </option>

                        <?php else: ?>

                            <option value="">
                                انتخاب کاربر
                            </option>

                            <?php foreach (
                                $coreCandidates
                                as $user
                            ): ?>

                                <?php
                                $optionParts = [
                                    (string) $user[
                                        'display_name'
                                    ],
                                ];

                                $mobile =
                                    trim(
                                        (string) (
                                            $user['mobile']
                                            ?? ''
                                        )
                                    );

                                $email =
                                    trim(
                                        (string) (
                                            $user['email']
                                            ?? ''
                                        )
                                    );

                                if ($mobile !== '') {
                                    $optionParts[] =
                                        $mobile;
                                }

                                if ($email !== '') {
                                    $optionParts[] =
                                        $email;
                                }
                                ?>

                                <option
                                    value="<?= ticketing_h(
                                        $user['id']
                                    ) ?>"
                                >
                                    <?= ticketing_h(
                                        implode(
                                            ' — ',
                                            $optionParts
                                        )
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </select>
                </label>

                <button
                    class="admin-button"
                    type="submit"
                    <?= $coreCandidates === []
                        ? ' disabled'
                        : '' ?>
                >
                    افزودن کاربر
                </button>
            </form>

        </div>

    </section>


    <section class="admin-section">

        <div class="admin-page-header">
            <div>
                <h2>
                    تعریف مخاطب جدید
                </h2>

                <p>
                    این مخاطب الزاماً حساب کاربری
                    در IPKF ندارد.
                </p>
            </div>
        </div>

        <form
            method="post"
            action="/admin/ticketing/participants/manual"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= ticketing_h($csrf) ?>"
            >

            <div class="admin-form-grid">

                <label>
                    <span>
                        نام و نام خانوادگی
                    </span>

                    <input
                        type="text"
                        name="full_name"
                        maxlength="255"
                        required
                        value="<?= ticketing_h(
                            $manualForm[
                                'full_name'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>


                <label>
                    <span>
                        شماره همراه
                    </span>

                    <input
                        type="text"
                        name="mobile"
                        maxlength="50"
                        dir="ltr"
                        value="<?= ticketing_h(
                            $manualForm[
                                'mobile'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>


                <label>
                    <span>
                        ایمیل
                    </span>

                    <input
                        type="email"
                        name="email"
                        maxlength="255"
                        dir="ltr"
                        value="<?= ticketing_h(
                            $manualForm[
                                'email'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>





                <label>
                    <span>
                        شناسه خارجی
                    </span>

                    <input
                        type="text"
                        name="external_reference"
                        maxlength="190"
                        dir="ltr"
                        value="<?= ticketing_h(
                            $manualForm[
                                'external_reference'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>

            </div>

            <div class="admin-form-actions">
                <button
                    class="admin-button"
                    type="submit"
                >
                    ثبت مخاطب
                </button>
            </div>

        </form>

    </section>


    <section class="admin-section">

        <div class="admin-page-header">
            <div>
                <h2>
                    ورود گروهی از فایل
                </h2>

                <p>
                    زیرساخت Batch و Row آماده است.
                    رابط Preview و Import در مرحله بعد
                    فعال می‌شود.
                </p>
            </div>

            <span class="admin-pill">
                مرحله بعد
            </span>
        </div>

    </section>


    <section class="admin-section">

        <div class="admin-users-toolbar">

            <form
                class="admin-users-search"
                method="get"
                action="/admin/ticketing/participants"
            >

                <label for="participant-q">
                    جستجو
                </label>

                <div class="admin-users-search__row">

                    <input
                        id="participant-q"
                        type="search"
                        name="q"
                        maxlength="120"
                        value="<?= ticketing_h($q) ?>"
                        placeholder="نام، موبایل، ایمیل یا شناسه"
                    >

                    <select
                        name="origin"
                        aria-label="مبدأ مخاطب"
                    >
                        <option value="">
                            همه مبدأها
                        </option>

                        <?php foreach (
                            $originTitles
                            as $code
                            => $filterTitle
                        ): ?>

                            <option
                                value="<?= ticketing_h(
                                    $code
                                ) ?>"
                                <?= $origin === $code
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($filterTitle) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>


                    <select
                        name="state"
                        aria-label="وضعیت مخاطب"
                    >
                        <option value="">
                            همه وضعیت‌ها
                        </option>

                        <?php foreach (
                            $stateTitles
                            as $code
                            => $filterTitle
                        ): ?>

                            <option
                                value="<?= ticketing_h(
                                    $code
                                ) ?>"
                                <?= $state === $code
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($filterTitle) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>


                    <button
                        class="admin-button"
                        type="submit"
                    >
                        اعمال فیلتر
                    </button>


                    <?php if (
                        $q !== ''
                        || $origin !== ''
                        || $state !== ''
                    ): ?>

                        <a
                            class="admin-button admin-button--soft"
                            href="/admin/ticketing/participants"
                        >
                            بازنشانی
                        </a>

                    <?php endif; ?>

                </div>

            </form>


            <div class="admin-muted">
                تعداد:

                <strong>
                    <?= ticketing_h(
                        \App\Support\AdminFormat::digits(
                            $total
                        )
                    ) ?>
                </strong>
            </div>

        </div>


        <?php if ($items === []): ?>

            <div class="admin-empty-state">
                مخاطبی ثبت نشده است.
            </div>

        <?php else: ?>

            <div class="admin-table-wrap">

                <table class="admin-table">

                    <thead>
                    <tr>
                        <th>نام</th>
                        <th>مبدأ</th>
                        <th>وضعیت</th>
                        <th>موبایل</th>
                        <th>ایمیل</th>
                        <th>پروژه</th>
                        <th>تیکت</th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php foreach (
                        $items
                        as $participant
                    ): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= ticketing_h(
                                        $participant[
                                            'full_name'
                                        ]
                                    ) ?>
                                </strong>

                                <div class="admin-muted">
                                    <?= ticketing_h(
                                        $participant[
                                            'public_reference'
                                        ]
                                    ) ?>
                                </div>
                            </td>


                            <td>
                                <?= ticketing_h(
                                    $originTitles[
                                        $participant[
                                            'origin_code'
                                        ]
                                    ]
                                    ?? $participant[
                                        'origin_code'
                                    ]
                                ) ?>
                            </td>


                            <td>
                                <span class="admin-pill">
                                    <?= ticketing_h(
                                        $stateTitles[
                                            $participant[
                                                'account_state'
                                            ]
                                        ]
                                        ?? $participant[
                                            'account_state'
                                        ]
                                    ) ?>
                                </span>
                            </td>


                            <td dir="ltr">
                                <?= ticketing_h(
                                    $participant[
                                        'mobile'
                                    ]
                                    ?: '—'
                                ) ?>
                            </td>


                            <td dir="ltr">
                                <?= ticketing_h(
                                    $participant[
                                        'email'
                                    ]
                                    ?: '—'
                                ) ?>
                            </td>





                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (int) $participant[
                                            'project_count'
                                        ]
                                    )
                                ) ?>
                            </td>


                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (int) $participant[
                                            'ticket_count'
                                        ]
                                    )
                                ) ?>
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
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';
