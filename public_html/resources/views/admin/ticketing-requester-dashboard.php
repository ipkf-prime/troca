<?php

declare(strict_types=1);

if (!function_exists('ticketing_requester_dashboard_h')) {
    function ticketing_requester_dashboard_h(
        mixed $value
    ): string {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
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

$memberships =
    is_array(
        $page['memberships']
        ?? null
    )
        ? array_values(
            $page['memberships']
        )
        : [];

$urls =
    new \IPKF\Support\ApplicationUrlRegistry();

$membershipUrl =
    $urls->core(
        '/admin/support/ticketing/membership'
    );

$myTicketsUrl =
    $urls->ticketingLaunch(
        '/admin/ticketing/tickets'
    );

$createTicketUrl =
    $urls->ticketingLaunch(
        '/admin/ticketing/tickets/create'
    );

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

    <span>
        پشتیبانی و تیکتینگ
    </span>
</nav>


<div class="admin-page ticketing-requester-dashboard">

    <header class="admin-page-header">
        <div>
            <h1>
                داشبورد تیکتینگ
            </h1>

            <p>
                نمای کلی پروژه‌های پشتیبانی و دسترسی‌های شما.
            </p>
        </div>
    </header>


    <?php if ($memberships === []): ?>

        <section class="admin-section">
            <div class="ticketing-requester-empty">

                <div class="ticketing-requester-empty__icon">
                    <?= \App\Support\AdminIcon::html(
                        'headset'
                    ) ?>
                </div>

                <div>
                    <h2>
                        هنوز عضو پروژه پشتیبانی نیستید
                    </h2>

                    <p class="admin-muted">
                        برای ثبت تیکت ابتدا از بخش عضویت در پروژه‌ها
                        پروژه موردنظر را انتخاب کنید.
                    </p>
                </div>

                <a
                    class="admin-button"
                    href="<?= ticketing_requester_dashboard_h(
                        $membershipUrl
                    ) ?>"
                >
                    عضویت در پروژه‌ها
                </a>

            </div>
        </section>

    <?php else: ?>

        <section class="admin-section">

            <div class="ticketing-requester-dashboard__heading">
                <div>
                    <h2>
                        پروژه‌های پشتیبانی من
                    </h2>

                    <p class="admin-muted">
                        عضویت‌های فعال شما در سامانه تیکتینگ.
                    </p>
                </div>

                <a
                    class="admin-button admin-button--soft"
                    href="<?= ticketing_requester_dashboard_h(
                        $membershipUrl
                    ) ?>"
                >
                    مدیریت عضویت‌ها
                </a>
            </div>


            <div class="ticketing-requester-projects">

                <?php foreach (
                    $memberships
                    as $project
                ): ?>

                    <article class="ticketing-requester-project">

                        <div class="ticketing-requester-project__icon">
                            <?= \App\Support\AdminIcon::html(
                                'headset'
                            ) ?>
                        </div>

                        <div class="ticketing-requester-project__body">

                            <div>
                                <strong>
                                    <?= ticketing_requester_dashboard_h(
                                        $project['title']
                                        ?? ''
                                    ) ?>
                                </strong>

                                <?php if (
                                    trim(
                                        (string) (
                                            $project['code']
                                            ?? ''
                                        )
                                    ) !== ''
                                ): ?>

                                    <small dir="ltr">
                                        <?= ticketing_requester_dashboard_h(
                                            $project['code']
                                        ) ?>
                                    </small>

                                <?php endif; ?>
                            </div>

                            <span class="admin-pill">
                                عضو
                            </span>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>


        <section class="admin-section">

            <div class="ticketing-requester-actions">

                <a
                    class="admin-action-card"
                    href="<?= ticketing_requester_dashboard_h(
                        $myTicketsUrl
                    ) ?>"
                >
                    <span class="admin-action-card__icon">
                        <?= \App\Support\AdminIcon::html(
                            'file-lines'
                        ) ?>
                    </span>

                    <span>
                        <strong>تیکت‌های من</strong>
                        <small>
                            مشاهده و پیگیری درخواست‌های ثبت‌شده
                        </small>
                    </span>
                </a>


                <a
                    class="admin-action-card"
                    href="<?= ticketing_requester_dashboard_h(
                        $createTicketUrl
                    ) ?>"
                >
                    <span class="admin-action-card__icon">
                        <?= \App\Support\AdminIcon::html(
                            'circle-check'
                        ) ?>
                    </span>

                    <span>
                        <strong>تیکت جدید</strong>
                        <small>
                            ثبت درخواست پشتیبانی جدید
                        </small>
                    </span>
                </a>


                <a
                    class="admin-action-card"
                    href="<?= ticketing_requester_dashboard_h(
                        $membershipUrl
                    ) ?>"
                >
                    <span class="admin-action-card__icon">
                        <?= \App\Support\AdminIcon::html(
                            'users'
                        ) ?>
                    </span>

                    <span>
                        <strong>عضویت در پروژه‌ها</strong>
                        <small>
                            مشاهده و مدیریت عضویت‌های پروژه
                        </small>
                    </span>
                </a>

            </div>

        </section>

    <?php endif; ?>

</div>


<style>
.ticketing-requester-dashboard {
    display: grid;
    gap: 1rem;
}

.ticketing-requester-dashboard__heading {
    align-items: center;
    display: flex;
    gap: .75rem;
    justify-content: space-between;
}

.ticketing-requester-dashboard__heading h2,
.ticketing-requester-dashboard__heading p,
.ticketing-requester-empty h2,
.ticketing-requester-empty p {
    margin: 0;
}

.ticketing-requester-dashboard__heading h2,
.ticketing-requester-empty h2 {
    font-size: .92rem;
}

.ticketing-requester-projects {
    display: grid;
    gap: .55rem;
    margin-top: .8rem;
}

.ticketing-requester-project {
    align-items: center;
    border: 1px solid var(--admin-border, #dfe7e2);
    border-radius: 10px;
    display: flex;
    gap: .7rem;
    min-height: 62px;
    padding: .55rem .7rem;
}

.ticketing-requester-project__icon,
.ticketing-requester-empty__icon {
    align-items: center;
    background: #fff7e6;
    border-radius: 9px;
    color: #d99c00;
    display: inline-flex;
    flex: 0 0 auto;
    justify-content: center;
}

.ticketing-requester-project__icon {
    height: 38px;
    width: 38px;
}

.ticketing-requester-empty__icon {
    height: 46px;
    width: 46px;
}

.ticketing-requester-project__body {
    align-items: center;
    display: flex;
    flex: 1 1 auto;
    gap: .6rem;
    justify-content: space-between;
    min-width: 0;
}

.ticketing-requester-project__body > div {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
}

.ticketing-requester-project__body strong {
    font-size: .8rem;
}

.ticketing-requester-project__body small {
    color: var(--admin-text-muted, #64748b);
}

.ticketing-requester-actions {
    display: grid;
    gap: .7rem;
    grid-template-columns:
        repeat(
            3,
            minmax(0, 1fr)
        );
}

.ticketing-requester-actions
.admin-action-card {
    min-height: 92px;
}

.ticketing-requester-actions
.admin-action-card > span:last-child {
    display: grid;
    gap: .18rem;
}

.ticketing-requester-actions small {
    color: var(--admin-text-muted, #64748b);
}

.ticketing-requester-empty {
    align-items: center;
    display: grid;
    gap: .8rem;
    grid-template-columns:
        auto
        minmax(0, 1fr)
        auto;
}

@media (max-width: 850px) {
    .ticketing-requester-actions {
        grid-template-columns: 1fr;
    }

    .ticketing-requester-empty {
        grid-template-columns:
            auto
            minmax(0, 1fr);
    }

    .ticketing-requester-empty .admin-button {
        grid-column: 1 / -1;
    }
}

@media (max-width: 650px) {
    .ticketing-requester-dashboard__heading {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>

<?php

$content =
    ob_get_clean()
    ?: '';

require __DIR__
    . '/layout.php';
