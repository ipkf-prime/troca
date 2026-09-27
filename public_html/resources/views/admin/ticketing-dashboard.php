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

/** @var array $context */
/** @var array $dashboard */
/** @var bool $is_staff */
/** @var array $staff_dashboard */

$dashboard =
    is_array(
        $dashboard
        ?? null
    )
        ? $dashboard
        : [];

$recent =
    is_array(
        $dashboard['recent']
        ?? null
    )
        ? $dashboard['recent']
        : [];

$isStaff =
    !empty(
        $is_staff
        ?? false
    );

$staffDashboard =
    is_array(
        $staff_dashboard
        ?? null
    )
        ? $staff_dashboard
        : [];

$staffKpis =
    is_array(
        $staffDashboard['kpis']
        ?? null
    )
        ? $staffDashboard['kpis']
        : [];

$uiText =
    static function (
        string $contentKey
    ): string {
        return
            \App\Services\UiContent\UiContentInlineGuide::bodyText(
                $contentKey,
                'ticketing',
                'ticketing-dashboard'
            );
    };


ob_start();
?>

<style>
.ticketing-dashboard-page {
    display:grid;
    gap:.8rem;
}

.ticketing-dashboard-head {
    align-items:center;
    display:flex;
    gap:.8rem;
    justify-content:space-between;
}

.ticketing-dashboard-role {
    align-items:center;
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:.85rem;
    display:flex;
    gap:.65rem;
    justify-content:space-between;
    padding:.62rem .78rem;
}

.ticketing-dashboard-role__caption {
    color:var(--admin-text-muted);
    font-size:.7rem;
}

.ticketing-dashboard-role__badge {
    align-items:center;
    border:1px solid var(--admin-border);
    border-radius:999px;
    color:var(--admin-primary);
    display:inline-flex;
    font-size:.72rem;
    font-weight:900;
    min-height:2rem;
    padding:.25rem .72rem;
    white-space:nowrap;
}

.ticketing-dashboard-metrics {
    display:grid;
    gap:.68rem;
}

.ticketing-dashboard-metrics--staff {
    grid-template-columns:
        repeat(4,minmax(0,1fr));
}

.ticketing-dashboard-metrics--requester {
    grid-template-columns:
        repeat(4,minmax(0,1fr));
}

.ticketing-dashboard-metric {
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:.9rem;
    padding:.82rem .9rem;
}

.ticketing-dashboard-metric__label {
    color:var(--admin-text-muted);
    font-size:.72rem;
    font-weight:800;
}

.ticketing-dashboard-metric__value {
    font-size:1.45rem;
    font-weight:950;
    margin-top:.2rem;
}


.ticketing-dashboard-metric--staff {
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    min-height:7.8rem;
    overflow:hidden;
    padding:1rem 1.05rem;
    position:relative;
}

.ticketing-dashboard-metric--staff::before {
    background:var(--admin-primary);
    border-radius:999px;
    content:"";
    inset-block:.9rem;
    inset-inline-start:0;
    opacity:.72;
    position:absolute;
    width:.22rem;
}

.ticketing-dashboard-metric--staff
.ticketing-dashboard-metric__label {
    color:var(--admin-text);
    font-size:.78rem;
    font-weight:900;
}

.ticketing-dashboard-metric--staff
.ticketing-dashboard-metric__value {
    font-size:2rem;
    line-height:1.1;
    margin-top:.7rem;
}

.ticketing-dashboard-metric__hint {
    color:var(--admin-text-muted);
    font-size:.66rem;
    line-height:1.7;
    margin-top:.55rem;
}

.ticketing-dashboard-section {
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:.9rem;
    padding:.9rem .95rem;
}

.ticketing-dashboard-section__head {
    align-items:center;
    display:flex;
    gap:.65rem;
    justify-content:space-between;
    margin-bottom:.7rem;
}

.ticketing-dashboard-section__head h2 {
    font-size:1rem;
    margin:0;
}

.ticketing-dashboard-section__head p {
    color:var(--admin-text-muted);
    font-size:.7rem;
    margin:.12rem 0 0;
}

.ticketing-dashboard-table {
    table-layout:fixed;
    width:100%;
}

.ticketing-dashboard-table th,
.ticketing-dashboard-table td {
    font-size:.68rem;
    line-height:1.55;
    overflow:hidden;
    padding:.44rem .4rem;
    text-overflow:ellipsis;
    vertical-align:middle;
    white-space:nowrap;
}

.ticketing-dashboard-subject {
    white-space:normal !important;
}

.ticketing-dashboard-empty {
    color:var(--admin-text-muted);
    font-size:.75rem;
    padding:1rem .25rem;
    text-align:center;
}

@media (max-width:1050px) {
    .ticketing-dashboard-metrics--staff,
    .ticketing-dashboard-metrics--requester {
        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }
}

@media (max-width:720px) {
    .ticketing-dashboard-head,
    .ticketing-dashboard-role,
    .ticketing-dashboard-section__head {
        align-items:stretch;
        display:grid;
    }

    .ticketing-dashboard-metrics--staff,
    .ticketing-dashboard-metrics--requester {
        grid-template-columns:1fr;
    }
}
</style>

<nav
    class="admin-breadcrumb"
    aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.61ee8f376a7683645d04')) ?>"
>
    <a href="/admin/dashboard">
        <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.60ad1a5db38273765301')) ?>
    </a>

    <span>/</span>

    <span>
        <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.ba33f14e6f6b94bb03ff')) ?>
    </span>
</nav>

<div
    class="admin-page
           ticketing-page
           ticketing-dashboard-page"
>

    <div
        class="admin-page-header
               ticketing-page-head
               ticketing-dashboard-head"
    >
        <div>

            <?php if ($isStaff): ?>

                <h1><?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.d857044fbaacab1d4917')) ?></h1>

                <p>
                    <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.bf484426d0fcf8631ba7')) ?>
                </p>

            <?php else: ?>

                <h1><?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.ba33f14e6f6b94bb03ff')) ?></h1>

                <p>
                    <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.2609f2a8fc4664d2b2df')) ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="admin-form-actions">

            <?php if ($isStaff): ?>

                <a
                    class="admin-button"
                    href="/admin/ticketing/staff"
                >
                    <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.eaacde8745546c3322b8')) ?>
                </a>

            <?php else: ?>

                <a
                    class="admin-button
                           admin-button--soft"
                    href="/admin/ticketing/tickets"
                >
                    <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.99d3b0530436453852be')) ?>
                </a>

                <a
                    class="admin-button"
                    href="/admin/ticketing/tickets/create"
                >
                    <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.d0b92358fdfb020b7c6b')) ?>
                </a>

            <?php endif; ?>

        </div>
    </div>

    <section class="ticketing-dashboard-role">

        <div class="ticketing-dashboard-role__caption">
            <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.9f657a11ccb7617a8db3')) ?>
        </div>

        <span class="ticketing-dashboard-role__badge">
            <?= $isStaff
                ? $uiText('ticketing.t2.ticketing-dashboard.ui.62266d56b51138b91422')
                : $uiText('ticketing.t2.ticketing-dashboard.ui.e177dc229a4b384d8293') ?>
        </span>

    </section>

    <?php if ($isStaff): ?>

        <div
            class="ticketing-dashboard-metrics
                   ticketing-dashboard-metrics--staff"
        >

            <?php foreach ([
                [
                    'label' =>
                        $uiText('ticketing.t2.ticketing-dashboard.ui.8f34079d600df45d8a98'),

                    'value' =>
                        $staffKpis['open']
                        ?? 0,

                    'hint' =>
                        \App\Services\UiContent\UiContentInlineGuide::bodyText('ticketing.ticketing-dashboard.guide.01', 'ticketing', 'ticketing-dashboard'),
                ],
                [
                    'label' =>
                        $uiText('ticketing.t2.ticketing-dashboard.ui.98e31f95411090b08e8b'),

                    'value' =>
                        $staffKpis[
                            'in_progress'
                        ]
                        ?? 0,

                    'hint' =>
                        \App\Services\UiContent\UiContentInlineGuide::bodyText('ticketing.ticketing-dashboard.guide.02', 'ticketing', 'ticketing-dashboard'),
                ],
                [
                    'label' =>
                        $uiText('ticketing.t2.ticketing-dashboard.ui.61c2fd097ec51152299b'),

                    'value' =>
                        $staffKpis['resolved']
                        ?? 0,

                    'hint' =>
                        \App\Services\UiContent\UiContentInlineGuide::bodyText('ticketing.ticketing-dashboard.guide.03', 'ticketing', 'ticketing-dashboard'),
                ],
                [
                    'label' =>
                        $uiText('ticketing.t2.ticketing-dashboard.ui.c1b7513ef28ea4e040da'),

                    'value' =>
                        $staffKpis['closed']
                        ?? 0,

                    'hint' =>
                        \App\Services\UiContent\UiContentInlineGuide::bodyText('ticketing.ticketing-dashboard.guide.04', 'ticketing', 'ticketing-dashboard'),
                ],
            ] as $metric): ?>

                <section
                    class="ticketing-dashboard-metric
                           ticketing-dashboard-metric--staff"
                >

                    <div
                        class="ticketing-dashboard-metric__label"
                    >
                        <?= ticketing_h(
                            $metric['label']
                        ) ?>
                    </div>

                    <div
                        class="ticketing-dashboard-metric__value"
                    >
                        <?= ticketing_h(
                            \App\Support\AdminFormat::digits(
                                (int) $metric['value']
                            )
                        ) ?>
                    </div>

                    <div
                        class="ticketing-dashboard-metric__hint"
                    >
                        <?= ticketing_h(
                            $metric['hint']
                        ) ?>
                    </div>

                </section>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div
            class="ticketing-dashboard-metrics
                   ticketing-dashboard-metrics--requester"
        >

            <?php foreach ([
                [
                    'label' =>
                        $uiText('ticketing.t2.ticketing-dashboard.ui.8e357d8f377da95c4141'),

                    'value' =>
                        $dashboard['total']
                        ?? 0,
                ],
                [
                    'label' =>
                        $uiText('ticketing.t2.ticketing-dashboard.ui.e5a6fb7e7e34ba103c68'),

                    'value' =>
                        $dashboard['open']
                        ?? 0,
                ],
                [
                    'label' =>
                        $uiText('ticketing.t2.ticketing-dashboard.ui.21ce5f83c396748d7d29'),

                    'value' =>
                        $dashboard['waiting']
                        ?? 0,
                ],
                [
                    'label' =>
                        $uiText('ticketing.t2.ticketing-dashboard.ui.c1b7513ef28ea4e040da'),

                    'value' =>
                        $dashboard['closed']
                        ?? 0,
                ],
            ] as $metric): ?>

                <section class="ticketing-dashboard-metric">

                    <div class="ticketing-dashboard-metric__label">
                        <?= ticketing_h(
                            $metric['label']
                        ) ?>
                    </div>

                    <div class="ticketing-dashboard-metric__value">
                        <?= ticketing_h(
                            \App\Support\AdminFormat::digits(
                                (int) $metric['value']
                            )
                        ) ?>
                    </div>

                </section>

            <?php endforeach; ?>

        </div>

        <section class="ticketing-dashboard-section">

            <div class="ticketing-dashboard-section__head">

                <div>
                    <h2>
                        <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.8e87d3cac151bd0971c4')) ?>
                    </h2>

                    <p>
                        <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.0d989d9b78f7168b47ca')) ?>
                    </p>
                </div>

            </div>

            <?php if ($recent === []): ?>

                <div class="ticketing-dashboard-empty">
                    <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.f337ee72e8e7c22967a3')) ?>
                </div>

            <?php else: ?>

                <div class="admin-table-wrap">

                    <table
                        class="admin-table
                               ticketing-dashboard-table"
                    >
                        <thead>
                        <tr>
                            <th><?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.7e172b54cddef7f93977')) ?></th>
                            <th><?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.1dcb961aaf2ee3b05247')) ?></th>
                            <th><?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.8fd58259c995ae3084eb')) ?></th>
                            <th><?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.cf447d70cd0fc83a4148')) ?></th>
                            <th><?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.8c691eeb0204e9bdd7a8')) ?></th>
                            <th><?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.7414b32403bcc5f9c451')) ?></th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach (
                            $recent
                            as $ticket
                        ): ?>

                            <?php
                            $url =
                                '/admin/ticketing/tickets/'
                                . rawurlencode(
                                    (string) (
                                        $ticket[
                                            'public_reference'
                                        ]
                                        ?? ''
                                    )
                                );
                            ?>

                            <tr>

                                <td>
                                    <?= ticketing_h(
                                        \App\Support\TicketingDisplay::ticketNumberFromRow(
                                                $ticket
                                            )
                                    ) ?>
                                </td>

                                <td class="ticketing-dashboard-subject">

                                    <a
                                        href="<?= ticketing_h(
                                            $url
                                        ) ?>"
                                    >
                                        <strong>
                                            <?= ticketing_h(
                                                $ticket[
                                                    'subject'
                                                ]
                                                ?? ''
                                            ) ?>
                                        </strong>
                                    </a>

                                </td>

                                <td>
                                    <?= ticketing_h(
                                        $ticket[
                                            'priority_title'
                                        ]
                                        ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <span class="admin-pill">
                                        <?= ticketing_h(
                                            $ticket[
                                                'status_title'
                                            ]
                                            ?? ''
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= ticketing_h(
                                        \App\Support\AdminFormat
                                            ::jalaliDateTime(
                                                (string) (
                                                    $ticket[
                                                        'last_activity_at'
                                                    ]
                                                    ?? ''
                                                )
                                            )
                                        ?: $uiText('ticketing.t2.ticketing-dashboard.ui.a320be13613666d9e453')
                                    ) ?>
                                </td>

                                <td>
                                    <a
                                        class="admin-button
                                               admin-button--soft
                                               admin-button--compact"
                                        href="<?= ticketing_h(
                                            $url
                                        ) ?>"
                                    >
                                        <?= ticketing_h($uiText('ticketing.t2.ticketing-dashboard.ui.d0e23273c39110db6185')) ?>
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

<?php

$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';
