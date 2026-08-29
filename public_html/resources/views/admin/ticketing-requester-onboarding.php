<?php

declare(strict_types=1);

$page =
    is_array($page ?? null)
        ? $page
        : [];

$memberships =
    is_array($page['memberships'] ?? null)
        ? $page['memberships']
        : [];

$openProjects =
    is_array($page['open_projects'] ?? null)
        ? $page['open_projects']
        : [];

$status =
    trim((string) ($status ?? ''));

$error =
    trim((string) ($error ?? ''));

$csrf =
    (new \IPKF\Security\Csrf())->token();

$errors = [
    'csrf' =>
        'اعتبار فرم منقضی شده است.',

    'requester_project_not_found' =>
        'پروژه پشتیبانی پیدا نشد.',

    'requester_open_join_disabled' =>
        'عضویت آزاد برای این پروژه فعال نیست.',

    'requester_invite_invalid' =>
        'کد عضویت معتبر نیست.',

    'requester_invite_inactive' =>
        'این کد عضویت غیرفعال است.',

    'requester_invite_not_started' =>
        'زمان استفاده از این کد هنوز شروع نشده است.',

    'requester_invite_expired' =>
        'اعتبار این کد به پایان رسیده است.',

    'requester_invite_exhausted' =>
        'ظرفیت استفاده از این کد تکمیل شده است.',
];
?>

<div class="admin-stack">

    <section class="admin-section">
        <h2>پشتیبانی و تیکتینگ</h2>

        <p class="admin-muted">
            برای ثبت و پیگیری درخواست،
            ابتدا پروژه پشتیبانی موردنظر را انتخاب کنید.
        </p>

        <?php if ($memberships !== []): ?>
            <div class="admin-form-actions">
                <a
                    class="admin-button admin-button--soft"
                    href="<?= admin_h(
                        $page['my_tickets_url']
                        ?? '#'
                    ) ?>"
                >
                    تیکت‌های من
                </a>

                <a
                    class="admin-button"
                    href="<?= admin_h(
                        $page['create_ticket_url']
                        ?? '#'
                    ) ?>"
                >
                    تیکت جدید
                </a>
            </div>
        <?php endif; ?>
    </section>


    <?php if ($status === 'joined'): ?>
        <div class="admin-alert admin-alert--success">
            عضویت شما با موفقیت فعال شد.
        </div>
    <?php elseif ($status === 'already'): ?>
        <div class="admin-alert admin-alert--success">
            عضویت شما از قبل فعال بوده است.
        </div>
    <?php endif; ?>


    <?php if ($error !== ''): ?>
        <div class="admin-alert admin-alert--error">
            <?= admin_h(
                $errors[$error]
                ?? 'عضویت انجام نشد.'
            ) ?>
        </div>
    <?php endif; ?>


    <?php if ($memberships !== []): ?>

        <section class="admin-section">
            <h3>پروژه‌های پشتیبانی من</h3>

            <?php foreach ($memberships as $project): ?>
                <div class="admin-card">
                    <strong>
                        <?= admin_h(
                            $project['title']
                            ?? ''
                        ) ?>
                    </strong>

                    <p class="admin-muted">
                        <?= admin_h(
                            $project['description']
                            ?? ''
                        ) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </section>

    <?php endif; ?>


    <?php if ($openProjects !== []): ?>

        <section class="admin-section">
            <h3>پروژه‌های قابل عضویت</h3>

            <div class="admin-grid">

                <?php foreach ($openProjects as $project): ?>

                    <div class="admin-card">

                        <strong>
                            <?= admin_h(
                                $project['title']
                                ?? ''
                            ) ?>
                        </strong>

                        <p class="admin-muted">
                            <?= admin_h(
                                $project['description']
                                ?? ''
                            ) ?>
                        </p>

                        <form
                            method="post"
                            action="/admin/support/ticketing/join"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value="<?= admin_h($csrf) ?>"
                            >

                            <input
                                type="hidden"
                                name="project_reference"
                                value="<?= admin_h(
                                    $project['public_reference']
                                    ?? ''
                                ) ?>"
                            >

                            <button
                                class="admin-button"
                                type="submit"
                            >
                                عضویت در پروژه
                            </button>
                        </form>

                    </div>

                <?php endforeach; ?>

            </div>
        </section>

    <?php endif; ?>


    <?php if (!empty($page['invite_enabled'])): ?>

        <section class="admin-section">
            <h3>کد عضویت دارید؟</h3>

            <form
                method="post"
                action="/admin/support/ticketing/invite"
                class="admin-form-grid"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($csrf) ?>"
                >

                <label>
                    <span>کد عضویت</span>

                    <input
                        type="text"
                        name="invite_code"
                        dir="ltr"
                        maxlength="80"
                        autocomplete="off"
                        required
                    >
                </label>

                <div class="admin-form-actions">
                    <button
                        class="admin-button"
                        type="submit"
                    >
                        عضویت با کد
                    </button>
                </div>
            </form>
        </section>

    <?php endif; ?>

</div>
