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
        'اعتبار فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.',

    'requester_project_not_found' =>
        'پروژه پشتیبانی پیدا نشد.',

    'requester_open_join_disabled' =>
        'عضویت آزاد برای این پروژه فعال نیست.',

    'requester_invite_invalid' =>
        'کد عضویت واردشده معتبر نیست.',

    'requester_invite_inactive' =>
        'این کد عضویت غیرفعال است.',

    'requester_invite_not_started' =>
        'زمان استفاده از این کد هنوز شروع نشده است.',

    'requester_invite_expired' =>
        'اعتبار این کد عضویت به پایان رسیده است.',

    'requester_invite_exhausted' =>
        'ظرفیت استفاده از این کد عضویت تکمیل شده است.',
];

ob_start();
?>

<style>
.requester-onboarding {
    display: grid;
    gap: 1rem;
}

.requester-onboarding__hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.requester-onboarding__hero-main {
    display: flex;
    align-items: center;
    gap: .9rem;
    min-width: 0;
}

.requester-onboarding__hero-icon {
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    flex: 0 0 54px;
    border-radius: 16px;
    background: color-mix(
        in srgb,
        var(--admin-accent, #258843) 12%,
        white
    );
    color: var(--admin-accent, #258843);
}

.requester-onboarding__hero-icon svg {
    width: 27px;
    height: 27px;
}

.requester-onboarding__hero h2,
.requester-onboarding__section h3 {
    margin: 0;
}

.requester-onboarding__hero p,
.requester-onboarding__section p {
    margin: .3rem 0 0;
}

.requester-onboarding__actions {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
}

.requester-project-grid {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(270px, 1fr));
    gap: .8rem;
    margin-top: .85rem;
}

.requester-project-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: .75rem;
    min-height: 178px;
    padding: 1rem;
    border: 1px solid
        var(--admin-border, #dce7e1);
    border-radius: 16px;
    background:
        linear-gradient(
            145deg,
            color-mix(
                in srgb,
                var(--admin-accent, #258843) 4%,
                white
            ),
            white 65%
        );
}

.requester-project-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .7rem;
}

.requester-project-card__identity {
    display: flex;
    align-items: center;
    gap: .65rem;
    min-width: 0;
}

.requester-project-card__icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    border-radius: 13px;
    color: #fff;
    background:
        var(--admin-accent, #258843);
}

.requester-project-card__icon svg {
    width: 21px;
    height: 21px;
}

.requester-project-card__title {
    display: grid;
    gap: .15rem;
}

.requester-project-card__title strong {
    font-size: .96rem;
}

.requester-project-card__title small {
    direction: ltr;
    text-align: right;
    color: var(--admin-muted, #708278);
}

.requester-project-card__description {
    color: var(--admin-muted, #708278);
    line-height: 1.9;
    flex: 1;
}

.requester-project-card form {
    margin-top: auto;
}

.requester-project-card .admin-button {
    width: 100%;
    justify-content: center;
}

.requester-membership-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .28rem .6rem;
    border-radius: 999px;
    background: #e9f7ef;
    color: #18733b;
    font-size: .76rem;
    font-weight: 700;
    white-space: nowrap;
}

.requester-invite {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: .7rem;
    align-items: end;
    max-width: 760px;
    margin-top: .9rem;
}

.requester-invite label {
    display: grid;
    gap: .38rem;
}

.requester-invite input {
    width: 100%;
}

.requester-invite__hint {
    display: flex;
    gap: .45rem;
    align-items: flex-start;
    color: var(--admin-muted, #708278);
    margin-top: .65rem;
    font-size: .82rem;
}

@media (max-width: 760px) {
    .requester-onboarding__hero {
        align-items: flex-start;
        flex-direction: column;
    }

    .requester-invite {
        grid-template-columns: 1fr;
    }

    .requester-invite .admin-button {
        width: 100%;
        justify-content: center;
    }
}
</style>


<div class="requester-onboarding">

    <section class="admin-section requester-onboarding__hero">

        <div class="requester-onboarding__hero-main">

            <span class="requester-onboarding__hero-icon">
                <?= \App\Support\AdminIcon::html(
                    'headset'
                ) ?>
            </span>

            <div>
                <h2>پشتیبانی و تیکتینگ</h2>

                <p class="admin-muted">
                    برای ثبت و پیگیری درخواست،
                    پروژه پشتیبانی موردنظر را انتخاب کنید.
                </p>
            </div>

        </div>


        <?php if ($memberships !== []): ?>

            <div class="requester-onboarding__actions">

                <a
                    class="admin-button admin-button--soft"
                    href="<?= admin_h(
                        $page[
                            'my_tickets_url'
                        ]
                        ?? '#'
                    ) ?>"
                >
                    <?= \App\Support\AdminIcon::html(
                        'document'
                    ) ?>
                    تیکت‌های من
                </a>

                <a
                    class="admin-button"
                    href="<?= admin_h(
                        $page[
                            'create_ticket_url'
                        ]
                        ?? '#'
                    ) ?>"
                >
                    <?= \App\Support\AdminIcon::html(
                        'plus'
                    ) ?>
                    تیکت جدید
                </a>

            </div>

        <?php endif; ?>

    </section>


    <?php if ($status === 'joined'): ?>

        <div class="admin-alert admin-alert--success">
            عضویت شما در پروژه پشتیبانی با موفقیت فعال شد.
        </div>

    <?php elseif ($status === 'already'): ?>

        <div class="admin-alert admin-alert--success">
            عضویت شما در این پروژه از قبل فعال بوده است.
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="admin-alert admin-alert--error">
            <?= admin_h(
                $errorMessages[$error]
                ?? 'عضویت انجام نشد. دوباره تلاش کنید.'
            ) ?>
        </div>

    <?php endif; ?>


    <?php if ($memberships !== []): ?>

        <section class="admin-section requester-onboarding__section">

            <div class="admin-section__header">
                <div>
                    <h3>پروژه‌های پشتیبانی من</h3>

                    <p class="admin-muted">
                        در این پروژه‌ها امکان ثبت و پیگیری تیکت دارید.
                    </p>
                </div>
            </div>


            <div class="requester-project-grid">

                <?php foreach ($memberships as $project): ?>

                    <article class="requester-project-card">

                        <div class="requester-project-card__head">

                            <div class="requester-project-card__identity">

                                <span class="requester-project-card__icon">
                                    <?= \App\Support\AdminIcon::html(
                                        'headset'
                                    ) ?>
                                </span>

                                <div class="requester-project-card__title">

                                    <strong>
                                        <?= admin_h(
                                            $project[
                                                'title'
                                            ]
                                            ?? ''
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= admin_h(
                                            $project[
                                                'code'
                                            ]
                                            ?? ''
                                        ) ?>
                                    </small>

                                </div>

                            </div>

                            <span class="requester-membership-badge">
                                عضو پروژه
                            </span>

                        </div>


                        <div class="requester-project-card__description">

                            <?= admin_h(
                                $project[
                                    'description'
                                ]
                                ?? ''
                            ) ?>

                        </div>


                        <div class="requester-onboarding__actions">

                            <a
                                class="admin-button"
                                href="<?= admin_h(
                                    $page[
                                        'create_ticket_url'
                                    ]
                                    ?? '#'
                                ) ?>"
                            >
                                ثبت تیکت جدید
                            </a>

                            <a
                                class="admin-button admin-button--soft"
                                href="<?= admin_h(
                                    $page[
                                        'my_tickets_url'
                                    ]
                                    ?? '#'
                                ) ?>"
                            >
                                پیگیری تیکت‌ها
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>


    <?php if ($openProjects !== []): ?>

        <section class="admin-section requester-onboarding__section">

            <div class="admin-section__header">
                <div>
                    <h3>پروژه‌های قابل عضویت</h3>

                    <p class="admin-muted">
                        پروژه موردنظر را انتخاب کنید؛
                        عضویت شما به‌عنوان درخواست‌کننده ثبت می‌شود.
                    </p>
                </div>
            </div>


            <div class="requester-project-grid">

                <?php foreach ($openProjects as $project): ?>

                    <article class="requester-project-card">

                        <div class="requester-project-card__head">

                            <div class="requester-project-card__identity">

                                <span class="requester-project-card__icon">
                                    <?= \App\Support\AdminIcon::html(
                                        'headset'
                                    ) ?>
                                </span>

                                <div class="requester-project-card__title">

                                    <strong>
                                        <?= admin_h(
                                            $project[
                                                'title'
                                            ]
                                            ?? ''
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= admin_h(
                                            $project[
                                                'code'
                                            ]
                                            ?? ''
                                        ) ?>
                                    </small>

                                </div>

                            </div>

                        </div>


                        <div class="requester-project-card__description">

                            <?= admin_h(
                                $project[
                                    'description'
                                ]
                                ?? ''
                            ) ?>

                        </div>


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
                                عضویت در پروژه
                            </button>

                        </form>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>


    <?php if (!empty($page['invite_enabled'])): ?>

        <section class="admin-section requester-onboarding__section">

            <div class="admin-section__header">
                <div>
                    <h3>عضویت با کد دعوت</h3>

                    <p class="admin-muted">
                        اگر مدیر پروژه برای شما کد عضویت ارسال کرده،
                        آن را در این بخش وارد کنید.
                    </p>
                </div>
            </div>


            <form
                method="post"
                action="/admin/support/ticketing/invite"
                class="requester-invite"
            >

                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h(
                        $csrf
                    ) ?>"
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
                    class="admin-button"
                    type="submit"
                >
                    عضویت با کد
                </button>

            </form>


            <div class="requester-invite__hint">
                کد دعوت فقط عضویت درخواست‌کننده در همان پروژه
                را فعال می‌کند و دسترسی کارشناسی ایجاد نمی‌کند.
            </div>

        </section>

    <?php endif; ?>

</div>

<?php

$content =
    ob_get_clean();

require __DIR__ . '/layout.php';
