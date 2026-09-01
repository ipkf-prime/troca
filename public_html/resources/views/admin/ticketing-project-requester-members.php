<?php

declare(strict_types=1);

if (
    !function_exists(
        'ticketing_requester_member_h'
    )
) {
    function ticketing_requester_member_h(
        $value
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

$project =
    is_array(
        $page['project']
        ?? null
    )
        ? $page['project']
        : [];

$members =
    is_array(
        $page['members']
        ?? null
    )
        ? $page['members']
        : [];

$reference =
    trim(
        (string) (
            $project[
                'public_reference'
            ]
            ?? ''
        )
    );

$status =
    strtolower(
        trim(
            (string) (
                $_GET[
                    'membership_status'
                ]
                ?? ''
            )
        )
    );

$messages = [
    'requester_revoked' => [
        'success',
        'عضویت متقاضی با موفقیت لغو شد.',
    ],

    'requester_open_tickets' => [
        'error',
        'این متقاضی تیکت باز دارد. ابتدا تیکت‌های باز بسته شوند.',
    ],

    'requester_membership_not_found' => [
        'error',
        'عضویت فعال موردنظر پیدا نشد.',
    ],

    'requester_revoke_forbidden' => [
        'error',
        'از این بخش فقط عضویت متقاضی قابل لغو است.',
    ],

    'csrf' => [
        'error',
        'اعتبار فرم منقضی شده است.',
    ],

    'requester_revoke_failed' => [
        'error',
        'لغو عضویت انجام نشد.',
    ],
];

$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();

ob_start();
?>

<style>
.ticketing-requester-members {
    display: grid;
    gap: .75rem;
}

.ticketing-requester-members__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
}

.ticketing-requester-members__person {
    display: grid;
    gap: .1rem;
}

.ticketing-requester-members__person small {
    direction: ltr;
    color: var(--admin-muted, #738179);
}

.ticketing-requester-members__blocked {
    display: grid;
    gap: .12rem;
}

.ticketing-requester-members__blocked small {
    color: var(--admin-muted, #738179);
}

.admin-button.ticketing-requester-members__danger {
    color: #fff;
    border-color: #b42318;
    background: #c43227;
}

.admin-button.ticketing-requester-members__danger:hover,
.admin-button.ticketing-requester-members__danger:focus {
    color: #fff;
    border-color: #8f1d15;
    background: #a8241b;
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

    <a href="/admin/ticketing/projects">
        پروژه‌های پشتیبانی
    </a>

    <span>/</span>

    <a
        href="/admin/ticketing/projects/<?= ticketing_requester_member_h(
            rawurlencode(
                $reference
            )
        ) ?>/edit?tab=membership"
    >
        تنظیمات عضویت
    </a>

    <span>/</span>

    <span>
        مدیریت اعضای متقاضی
    </span>
</nav>


<div class="ticketing-requester-members">

    <div class="admin-page-header">

        <div>
            <h1>
                مدیریت اعضای متقاضی
            </h1>

            <p>
                <?= ticketing_requester_member_h(
                    $project[
                        'title'
                    ]
                    ?? ''
                ) ?>
            </p>
        </div>

        <a
            class="admin-button admin-button--soft"
            href="/admin/ticketing/projects/<?= ticketing_requester_member_h(
                rawurlencode(
                    $reference
                )
            ) ?>/edit?tab=membership"
        >
            بازگشت
        </a>

    </div>


    <?php if (
        isset(
            $messages[
                $status
            ]
        )
    ): ?>

        <?php
        [$type, $message] =
            $messages[
                $status
            ];
        ?>

        <div
            class="admin-alert <?= $type === 'success'
                ? 'admin-alert--success'
                : 'admin-alert--error' ?>"
        >
            <?= ticketing_requester_member_h(
                $message
            ) ?>
        </div>

    <?php endif; ?>


    <section class="admin-section">

        <div class="ticketing-requester-members__header">

            <div>
                <h2>
                    متقاضیان فعال
                </h2>

                <p class="admin-muted">
                    لغو عضویت فقط برای نقش متقاضی انجام می‌شود.
                </p>
            </div>

        </div>


        <?php if ($members === []): ?>

            <div class="admin-empty-state">
                متقاضی فعالی وجود ندارد.
            </div>

        <?php else: ?>

            <div class="admin-table-wrap">

                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>متقاضی</th>
                            <th>نقش</th>
                            <th>تیکت باز</th>
                            <th>تاریخ عضویت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            $members
                            as $member
                        ): ?>

                            <?php
                            $memberId =
                                (int) (
                                    $member['id']
                                    ?? 0
                                );

                            $openTicketCount =
                                max(
                                    0,
                                    (int) (
                                        $member[
                                            'open_ticket_count'
                                        ]
                                        ?? 0
                                    )
                                );
                            ?>

                            <tr>

                                <td>
                                    <div class="ticketing-requester-members__person">

                                        <strong>
                                            <?= ticketing_requester_member_h(
                                                $member[
                                                    'display_name_snapshot'
                                                ]
                                                ?? '—'
                                            ) ?>
                                        </strong>

                                        <?php if (
                                            trim(
                                                (string) (
                                                    $member[
                                                        'user_reference'
                                                    ]
                                                    ?? ''
                                                )
                                            ) !== ''
                                        ): ?>

                                            <small>
                                                <?= ticketing_requester_member_h(
                                                    $member[
                                                        'user_reference'
                                                    ]
                                                ) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>
                                </td>


                                <td>
                                    متقاضی
                                </td>


                                <td>
                                    <?= ticketing_requester_member_h(
                                        \App\Support\AdminFormat::digits(
                                            $openTicketCount
                                        )
                                    ) ?>
                                </td>


                                <td>
                                    <?= ticketing_requester_member_h(
                                        $member[
                                            'joined_at'
                                        ]
                                        ?? '—'
                                    ) ?>
                                </td>


                                <td>

                                    <?php if (
                                        $openTicketCount > 0
                                    ): ?>

                                        <div class="ticketing-requester-members__blocked">

                                            <strong>
                                                لغو عضویت مسدود
                                            </strong>

                                            <small>
                                                ابتدا تیکت‌های باز بسته شوند.
                                            </small>

                                        </div>

                                    <?php else: ?>

                                        <form
                                            method="post"
                                            action="/admin/ticketing/projects/<?= ticketing_requester_member_h(
                                                rawurlencode(
                                                    $reference
                                                )
                                            ) ?>/requesters/<?= $memberId ?>/revoke"
                                            onsubmit="return confirm('عضویت این متقاضی لغو شود؟');"
                                        >

                                            <input
                                                type="hidden"
                                                name="_token"
                                                value="<?= ticketing_requester_member_h(
                                                    $csrf
                                                ) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="admin-button admin-button--soft ticketing-requester-members__danger"
                                            >
                                                لغو عضویت
                                            </button>

                                        </form>

                                    <?php endif; ?>

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

require __DIR__
    . '/layout.php';
