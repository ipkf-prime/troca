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
    trim(
        (string) ($status ?? '')
    );

$error =
    trim(
        (string) ($error ?? '')
    );

$csrf =
    (new \IPKF\Security\Csrf())
        ->token();

$errorMessages = [
    'csrf' =>
        'اعتبار فرم منقضی شده است.',

    'requester_project_not_found' =>
        'پروژه پشتیبانی پیدا نشد.',

    'requester_open_join_disabled' =>
        'عضویت آزاد برای این پروژه فعال نیست.',

    'requester_invite_invalid' =>
        'کد عضویت معتبر نیست.',

    'requester_invite_inactive' =>
        'کد عضویت غیرفعال است.',

    'requester_invite_not_started' =>
        'زمان استفاده از کد هنوز شروع نشده است.',

    'requester_invite_expired' =>
        'اعتبار کد عضویت به پایان رسیده است.',

    'requester_invite_exhausted' =>
        'ظرفیت استفاده از کد عضویت تکمیل شده است.',

    'requester_membership_not_found' =>
        'عضویت فعال موردنظر پیدا نشد.',

    'requester_self_leave_forbidden' =>
        'لغو عضویت این نقش از این بخش مجاز نیست.',

    'requester_open_tickets' =>
        'تا زمانی که در این پروژه تیکت باز دارید، لغو عضویت امکان‌پذیر نیست.',

    'requester_leave_failed' =>
        'لغو عضویت انجام نشد.',
];

ob_start();
?>

<style>
.ticketing-requester {
    display: grid;
    gap: .7rem;
}

.ticketing-requester .admin-section {
    padding: .9rem 1rem;
}

.ticketing-requester__section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .6rem;
}

.ticketing-requester__section-head h2 {
    margin: 0;
    font-size: .98rem;
}

.ticketing-requester__section-head p {
    margin: .12rem 0 0;
    font-size: .76rem;
}

.ticketing-project-list {
    display: grid;
    gap: .45rem;
}

.ticketing-project-row {
    display: grid;
    grid-template-columns:
        minmax(0, 1fr)
        auto;

    align-items: center;
    gap: .7rem;

    padding: .65rem .72rem;

    border:
        1px solid
        var(--admin-border, #dfe7e2);

    border-radius: 12px;
    background: #fff;
}

.ticketing-project-row__main {
    display: flex;
    align-items: center;
    gap: .6rem;
    min-width: 0;
}

.ticketing-project-row__icon {
    display: grid;
    place-items: center;

    width: 36px;
    height: 36px;
    flex: 0 0 36px;

    border-radius: 10px;

    color:
        var(--admin-accent, #258843);

    background:
        color-mix(
            in srgb,
            var(--admin-accent, #258843) 11%,
            white
        );
}

.ticketing-project-row__icon svg {
    width: 19px;
    height: 19px;
}

.ticketing-project-row__content {
    display: grid;
    gap: .12rem;
    min-width: 0;
}

.ticketing-project-row__title {
    display: flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
}

.ticketing-project-row__title strong {
    font-size: .88rem;
}

.ticketing-project-row__code {
    display: inline-flex;
    align-items: center;

    padding: .08rem .35rem;

    border-radius: 999px;

    background: #f1f4f2;
    color: #718078;

    direction: ltr;
    font-size: .66rem;
}

.ticketing-project-row__description {
    overflow: hidden;

    color:
        var(--admin-muted, #738179);

    font-size: .74rem;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.ticketing-project-row__actions {
    display: flex;
    align-items: center;
    gap: .35rem;
    flex: 0 0 auto;
}

.ticketing-project-row__actions form {
    margin: 0;
}

.ticketing-project-row__actions .admin-button {
    min-height: 32px;
    padding: .34rem .65rem;
    white-space: nowrap;
}

.ticketing-member-badge {
    padding: .1rem .38rem;

    border-radius: 999px;

    background: #e9f7ef;
    color: #176e3a;

    font-size: .65rem;
    font-weight: 700;
}

.ticketing-invite {
    display: flex;
    align-items: end;
    gap: .45rem;

    max-width: 620px;
}

.ticketing-invite label {
    display: grid;
    gap: .22rem;
    flex: 1;
}

.ticketing-invite label span {
    font-size: .75rem;
}

.ticketing-invite input {
    width: 100%;
    min-height: 36px;
}

.ticketing-invite .admin-button {
    min-height: 36px;
    white-space: nowrap;
}

/* TICKETING_REQUESTER_SELF_LEAVE_UI */

.ticketing-project-row__actions .ticketing-leave-button {
    color: #fff;
    border-color: #b42318;
    background: #c43227;
}

.ticketing-project-row__actions .ticketing-leave-button:hover,
.ticketing-project-row__actions .ticketing-leave-button:focus {
    color: #fff;
    border-color: #8f1d15;
    background: #a8241b;
}

@media (max-width: 720px) {
    .ticketing-project-row {
        grid-template-columns: 1fr;
    }

    .ticketing-project-row__actions {
        width: 100%;
    }

    .ticketing-project-row__actions form,
    .ticketing-project-row__actions .admin-button {
        flex: 1;
        width: 100%;
    }

    .ticketing-invite {
        display: grid;
        grid-template-columns: 1fr;
        max-width: none;
    }

    .ticketing-invite .admin-button {
        width: 100%;
        justify-content: center;
    }
}
</style>


<div class="ticketing-requester">

    <?php if ($status === 'joined'): ?>
        <div class="admin-alert admin-alert--success">
            عضویت شما با موفقیت فعال شد.
        </div>
    <?php elseif ($status === 'already'): ?>
        <div class="admin-alert admin-alert--success">
            عضویت شما از قبل فعال بوده است.
        </div>
    <?php elseif ($status === 'left'): ?>
        <div class="admin-alert admin-alert--success">
            عضویت شما در پروژه با موفقیت لغو شد.
        </div>
    <?php endif; ?>


    <?php if ($error !== ''): ?>
        <div class="admin-alert admin-alert--error">
            <?= admin_h(
                $errorMessages[$error]
                ?? 'عضویت انجام نشد.'
            ) ?>
        </div>
    <?php endif; ?>


    <?php if ($memberships !== []): ?>

        <section class="admin-section">

            <div class="ticketing-requester__section-head">
                <div>
                    <h2>پروژه‌های من</h2>

                    <p class="admin-muted">
                        ثبت و پیگیری درخواست‌های پشتیبانی
                    </p>
                </div>
            </div>


            <div class="ticketing-project-list">

                <?php foreach ($memberships as $project): ?>

                    <div class="ticketing-project-row">

                        <div class="ticketing-project-row__main">

                            <span class="ticketing-project-row__icon">
                                <?= \App\Support\AdminIcon::html(
                                    'headset'
                                ) ?>
                            </span>


                            <div class="ticketing-project-row__content">

                                <div class="ticketing-project-row__title">

                                    <strong>
                                        <?= admin_h(
                                            $project['title']
                                            ?? ''
                                        ) ?>
                                    </strong>

                                    <span class="ticketing-project-row__code">
                                        <?= admin_h(
                                            $project['code']
                                            ?? ''
                                        ) ?>
                                    </span>

                                    <span class="ticketing-member-badge">
                                        عضو
                                    </span>

                                </div>


                                <?php if (
                                    trim(
                                        (string) (
                                            $project['description']
                                            ?? ''
                                        )
                                    ) !== ''
                                ): ?>

                                    <div class="ticketing-project-row__description">
                                        <?= admin_h(
                                            $project['description']
                                            ?? ''
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="ticketing-project-row__actions">

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

                            <?php if (
                                (string) (
                                    $project['role_code']
                                    ?? ''
                                ) === 'requester'
                            ): ?>

                                <form
                                    method="post"
                                    action="/admin/support/ticketing/leave"
                                    onsubmit="return confirm('عضویت شما در این پروژه لغو شود؟');"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        value="<?= admin_h(
                                            $csrf
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="project_reference"
                                        value="<?= admin_h(
                                            $project[
                                                'public_reference'
                                            ]
                                            ?? ''
                                        ) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="admin-button admin-button--soft ticketing-leave-button"
                                    >
                                        لغو عضویت
                                    </button>
                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>


    <?php if ($openProjects !== []): ?>

        <section class="admin-section">

            <div class="ticketing-requester__section-head">
                <div>
                    <h2>عضویت در پروژه</h2>

                    <p class="admin-muted">
                        پروژه موردنظر را انتخاب کنید.
                    </p>
                </div>
            </div>


            <div class="ticketing-project-list">

                <?php foreach ($openProjects as $project): ?>

                    <div class="ticketing-project-row">

                        <div class="ticketing-project-row__main">

                            <span class="ticketing-project-row__icon">
                                <?= \App\Support\AdminIcon::html(
                                    'headset'
                                ) ?>
                            </span>


                            <div class="ticketing-project-row__content">

                                <div class="ticketing-project-row__title">

                                    <strong>
                                        <?= admin_h(
                                            $project['title']
                                            ?? ''
                                        ) ?>
                                    </strong>

                                    <span class="ticketing-project-row__code">
                                        <?= admin_h(
                                            $project['code']
                                            ?? ''
                                        ) ?>
                                    </span>

                                </div>


                                <?php if (
                                    trim(
                                        (string) (
                                            $project['description']
                                            ?? ''
                                        )
                                    ) !== ''
                                ): ?>

                                    <div class="ticketing-project-row__description">
                                        <?= admin_h(
                                            $project['description']
                                            ?? ''
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="ticketing-project-row__actions">

                            <form
                                method="post"
                                action="/admin/support/ticketing/join"
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    value="<?= admin_h(
                                        $csrf
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="project_reference"
                                    value="<?= admin_h(
                                        $project[
                                            'public_reference'
                                        ]
                                        ?? ''
                                    ) ?>"
                                >

                                <button
                                    type="submit"
                                    class="admin-button"
                                >
                                    عضویت
                                </button>

                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>


    <?php if (!empty($page['invite_enabled'])): ?>

        <section class="admin-section">

            <div class="ticketing-requester__section-head">
                <div>
                    <h2>کد دعوت</h2>

                    <p class="admin-muted">
                        اگر کد عضویت دریافت کرده‌اید، آن را وارد کنید.
                    </p>
                </div>
            </div>


            <form
                method="post"
                action="/admin/support/ticketing/invite"
                class="ticketing-invite"
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
                        placeholder="NP-XXXX-XXXX-XXXX-XXXX"
                        required
                    >
                </label>


                <button
                    type="submit"
                    class="admin-button"
                >
                    عضویت با کد
                </button>

            </form>

        </section>

    <?php endif; ?>

</div>

<?php

$content =
    ob_get_clean();

require __DIR__ . '/layout.php';
