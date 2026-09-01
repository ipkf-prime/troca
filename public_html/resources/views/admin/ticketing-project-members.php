<?php

declare(strict_types=1);

if (
    !function_exists(
        'ticketing_project_members_h'
    )
) {
    function ticketing_project_members_h(
        mixed $value
    ): string {
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


if (
    !function_exists(
        'ticketing_project_member_icon'
    )
) {
    function ticketing_project_member_icon(
        string $name
    ): string {
        $icons = [
            'role' =>
                '<path d="M12 20h9"/>'
                . '<path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',

            'team' =>
                '<circle cx="9" cy="7" r="4"/>'
                . '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>'
                . '<path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',

            'revoke' =>
                '<circle cx="8.5" cy="7" r="4"/>'
                . '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>'
                . '<path d="m18 8 5 5"/>'
                . '<path d="m23 8-5 5"/>',

            'restore' =>
                '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>'
                . '<path d="M3 3v5h5"/>',

            'close' =>
                '<path d="m6 6 12 12"/>'
                . '<path d="m18 6-12 12"/>',
        ];

        $body =
            $icons[$name]
            ?? $icons['role'];

        return
            '<svg '
            . 'viewBox="0 0 24 24" '
            . 'fill="none" '
            . 'stroke="currentColor" '
            . 'stroke-width="1.8" '
            . 'stroke-linecap="round" '
            . 'stroke-linejoin="round" '
            . 'aria-hidden="true">'
            . $body
            . '</svg>';
    }
}


$page =
    is_array($page ?? null)
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

$teams =
    is_array(
        $page['teams']
        ?? null
    )
        ? $page['teams']
        : [];

$projectRoles =
    is_array(
        $page[
            'project_role_options'
        ]
        ?? null
    )
        ? $page[
            'project_role_options'
        ]
        : [];

$staffRoles =
    is_array(
        $page[
            'staff_role_options'
        ]
        ?? null
    )
        ? $page[
            'staff_role_options'
        ]
        : [];

$summary =
    is_array(
        $page['summary']
        ?? null
    )
        ? $page['summary']
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

$projectUrl =
    '/admin/ticketing/projects/'
    . rawurlencode(
        $reference
    );

$membersUrl =
    $projectUrl
    . '/members';

$status =
    trim(
        (string) (
            $_GET['status']
            ?? ''
        )
    );

$statusMessages = [
    'member_role_saved' => [
        'success',
        'نقش پروژه با موفقیت ذخیره شد.',
    ],

    'member_revoked' => [
        'success',
        'عضویت پروژه با حفظ سوابق غیرفعال شد.',
    ],

    'member_restored' => [
        'success',
        'عضویت پروژه فعال شد.',
    ],

    'team_saved' => [
        'success',
        'دسترسی تیمی با موفقیت ذخیره شد.',
    ],

    'team_removed' => [
        'success',
        'دسترسی تیمی غیرفعال شد.',
    ],

    'requester_open_tickets' => [
        'danger',
        'این عضو تیکت باز به‌عنوان درخواست‌کننده دارد و فعلاً امکان این عملیات وجود ندارد.',
    ],

    'member_owned_open_tickets' => [
        'danger',
        'این عضو هنوز تیکت باز در اختیار دارد. ابتدا تیکت جاری باید انتقال یا خاتمه داده شود.',
    ],

    'member_inactive' => [
        'danger',
        'عضویت غیرفعال است.',
    ],

    'member_invalid' => [
        'danger',
        'عضو انتخاب‌شده معتبر نیست.',
    ],

    'team_invalid' => [
        'danger',
        'تیم یا نقش تیمی انتخاب‌شده معتبر نیست.',
    ],

    'team_staff_role_required' => [
        'danger',
        'دسترسی تیمی فقط برای نقش کارشناس یا مدیر پروژه قابل ثبت است.',
    ],

    'project_not_found' => [
        'danger',
        'پروژه موردنظر پیدا نشد.',
    ],

    'csrf' => [
        'danger',
        'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید.',
    ],

    'failed' => [
        'danger',
        'عملیات موردنظر انجام نشد.',
    ],
];

$csrf =
    (new \IPKF\Security\Csrf())
        ->token();

$digits =
    static function (
        int $value
    ): string {
        return
            \App\Support\AdminFormat::digits(
                $value
            );
    };


ob_start();
?>

<!-- TICKETING_PROJECT_MEMBER_ACCESS_CENTER_UI -->
<!-- TICKETING_PROJECT_MEMBER_ACCESS_TABLE_UI -->
<!-- TICKETING_PROJECT_MEMBER_ACCESS_NO_TECH_IDENTIFIERS -->

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

    <span>
        اعضا و دسترسی‌ها
    </span>
</nav>


<!-- TICKETING_TICKETING_UI_CONSISTENCY_CONTRACT -->
<section class="ticketing-standard-page-head">

    <div class="ticketing-standard-page-head__main">
        <h2>
            اعضا و دسترسی‌ها
        </h2>

        <p>
            مدیریت اعضا، نقش‌ها و دسترسی‌های پشتیبانی
            —
            <?= ticketing_project_members_h(
                $project['title']
                ?? ''
            ) ?>
        </p>
    </div>

    <a
        class="admin-button admin-button--soft"
        href="<?= ticketing_project_members_h(
            $projectUrl
            . '/edit?tab=membership'
        ) ?>"
    >
        بازگشت به پروژه
    </a>

</section>


<?php if (
    $status !== ''
    &&
    isset(
        $statusMessages[$status]
    )
): ?>
    <?php
    [$statusType, $statusText] =
        $statusMessages[$status];
    ?>

    <section class="admin-section ticketing-member-status">
        <div
            class="admin-alert admin-alert--<?= ticketing_project_members_h($statusType) ?>"
            role="status"
        >
            <?= ticketing_project_members_h($statusText) ?>
        </div>
    </section>
<?php endif; ?>


<section class="admin-section ticketing-member-summary">
    <?php
    $summaryItems = [
        'total' =>
            'کل سوابق',

        'active' =>
            'عضو فعال',

        'requester' =>
            'متقاضی',

        'staff' =>
            'کارکنان پشتیبانی',

        'manager' =>
            'مدیر پروژه',

        'inactive' =>
            'غیرفعال',
    ];
    ?>

    <div class="ticketing-member-summary__grid">

        <?php foreach (
            $summaryItems
            as $key => $label
        ): ?>
            <article>
                <span>
                    <?= ticketing_project_members_h($label) ?>
                </span>

                <strong>
                    <?= ticketing_project_members_h(
                        $digits(
                            (int) (
                                $summary[$key]
                                ?? 0
                            )
                        )
                    ) ?>
                </strong>
            </article>
        <?php endforeach; ?>

    </div>
</section>


<section class="admin-section ticketing-member-browser">

    <div class="ticketing-member-browser__header">

        <div>
            <h3>
                اعضای پروژه
            </h3>

            <p class="admin-muted">
                اطلاعات فنی هویت در این صفحه نمایش داده نمی‌شود.
            </p>
        </div>

        <div class="ticketing-member-result-count">
            <strong data-member-result-count>
                <?= ticketing_project_members_h(
                    $digits(
                        count($members)
                    )
                ) ?>
            </strong>

            <span>
                نتیجه
            </span>
        </div>

    </div>


    <div class="ticketing-member-filters">

        <label class="ticketing-member-filter-search">
            <span>
                جستجو
            </span>

            <input
                type="search"
                data-member-search
                placeholder="نام عضو..."
                autocomplete="off"
            >
        </label>

        <label>
            <span>
                نقش
            </span>

            <select data-member-role-filter>
                <option value="">
                    همه نقش‌ها
                </option>

                <?php foreach (
                    $projectRoles
                    as $roleCode => $roleTitle
                ): ?>
                    <option
                        value="<?= ticketing_project_members_h($roleCode) ?>"
                    >
                        <?= ticketing_project_members_h($roleTitle) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>
                وضعیت
            </span>

            <select data-member-status-filter>
                <option value="">
                    همه وضعیت‌ها
                </option>

                <option value="active">
                    فعال
                </option>

                <option value="inactive">
                    غیرفعال
                </option>
            </select>
        </label>

    </div>



    <!-- TICKETING_PROJECT_MEMBER_BULK_SELECTION_UI -->
    <div
        class="ticketing-member-selection-toolbar"
        data-member-selection-toolbar
    >
        <div class="ticketing-member-selection-toolbar__count">
            <strong data-member-selected-count>
                ۰
            </strong>

            <span>
                انتخاب‌شده
            </span>
        </div>

        <button
            type="button"
            class="admin-button admin-button--soft"
            data-member-select-filtered
        >
            انتخاب همه نتایج
        </button>

        <button
            type="button"
            class="admin-button admin-button--soft"
            data-member-clear-selection
            disabled
        >
            لغو انتخاب همه
        </button>

        <span class="admin-muted ticketing-member-selection-toolbar__hint">
            انتخاب‌ها برای عملیات گروهی بعدی آماده می‌شوند.
        </span>
    </div>

    <?php if ($members === []): ?>

        <div class="admin-empty-state">
            هنوز عضوی برای این پروژه ثبت نشده است.
        </div>

    <?php else: ?>

        <div class="ticketing-member-table-wrap">

            <table class="ticketing-member-table">

                <thead>
                    <tr>
                        <th class="ticketing-member-select-col">
                            <input
                                type="checkbox"
                                data-member-select-all
                                title="انتخاب همه نتایج فیلترشده"
                                aria-label="انتخاب همه نتایج فیلترشده"
                            >
                        </th>

                        <th>
                            نام
                        </th>

                        <th>
                            نقش
                        </th>

                        <th>
                            وضعیت
                        </th>

                        <th class="ticketing-member-number">
                            تیکت‌ها
                        </th>

                        <th class="ticketing-member-number">
                            باز درخواست‌کننده
                        </th>

                        <th class="ticketing-member-number">
                            در اختیار
                        </th>

                        <th class="ticketing-member-number">
                            تیم فعال
                        </th>

                        <th class="ticketing-member-actions-col">
                            عملیات
                        </th>
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

                    $roleCode =
                        trim(
                            (string) (
                                $member[
                                    'role_code'
                                ]
                                ?? ''
                            )
                        );

                    $active =
                        !empty(
                            $member['active']
                        );

                    $displayName =
                        trim(
                            (string) (
                                $member[
                                    'participant_name'
                                ]
                                ?? ''
                            )
                        );

                    if ($displayName === '') {
                        $displayName =
                            trim(
                                (string) (
                                    $member[
                                        'display_name_snapshot'
                                    ]
                                    ?? ''
                                )
                            );
                    }

                    if ($displayName === '') {
                        $displayName =
                            'بدون نام';
                    }

                    $memberTeams =
                        is_array(
                            $member['teams']
                            ?? null
                        )
                            ? $member['teams']
                            : [];

                    $activeTeams =
                        array_values(
                            array_filter(
                                $memberTeams,
                                static fn (
                                    array $team
                                ): bool =>
                                    !empty(
                                        $team['active']
                                    )
                            )
                        );

                    $rowStatus =
                        $active
                            ? 'active'
                            : 'inactive';
                    ?>


                    <tr
                        class="ticketing-member-main-row"
                        data-member-row
                        data-member-id="<?= ticketing_project_members_h($memberId) ?>"
                        data-member-name="<?= ticketing_project_members_h(
                            mb_strtolower(
                                $displayName,
                                'UTF-8'
                            )
                        ) ?>"
                        data-member-role="<?= ticketing_project_members_h($roleCode) ?>"
                        data-member-status="<?= ticketing_project_members_h($rowStatus) ?>"
                    >

                        <td class="ticketing-member-select-col">
                            <input
                                type="checkbox"
                                name="member_ids[]"
                                value="<?= ticketing_project_members_h($memberId) ?>"
                                data-member-select
                                data-member-select-id="<?= ticketing_project_members_h($memberId) ?>"
                                aria-label="انتخاب <?= ticketing_project_members_h($displayName) ?>"
                            >
                        </td>


                        <td class="ticketing-member-name">
                            <strong>
                                <?= ticketing_project_members_h($displayName) ?>
                            </strong>
                        </td>


                        <td>
                            <span class="admin-pill">
                                <?= ticketing_project_members_h(
                                    $member[
                                        'role_title'
                                    ]
                                    ?? $roleCode
                                ) ?>
                            </span>
                        </td>


                        <td>
                            <span class="admin-pill">
                                <?= $active
                                    ? 'فعال'
                                    : 'غیرفعال' ?>
                            </span>
                        </td>


                        <td class="ticketing-member-number">
                            <?= ticketing_project_members_h(
                                $digits(
                                    (int) (
                                        $member[
                                            'requester_ticket_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            ) ?>
                        </td>


                        <td class="ticketing-member-number">
                            <?= ticketing_project_members_h(
                                $digits(
                                    (int) (
                                        $member[
                                            'requester_open_ticket_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            ) ?>
                        </td>


                        <td class="ticketing-member-number">
                            <?= ticketing_project_members_h(
                                $digits(
                                    (int) (
                                        $member[
                                            'owned_open_ticket_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            ) ?>
                        </td>


                        <td class="ticketing-member-number">
                            <?= ticketing_project_members_h(
                                $digits(
                                    (int) (
                                        $member[
                                            'active_team_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            ) ?>
                        </td>


                        <td class="ticketing-member-actions">

                            <?php if ($active): ?>

                                <button
                                    type="button"
                                    class="ticketing-member-icon-button"
                                    data-member-open="role"
                                    data-member-target="<?= ticketing_project_members_h($memberId) ?>"
                                    title="نقش و عضویت"
                                    aria-label="نقش و عضویت"
                                >
                                    <?= ticketing_project_member_icon('role') ?>
                                </button>


                                <?php if (
                                    in_array(
                                        $roleCode,
                                        [
                                            'member',
                                            'manager',
                                        ],
                                        true
                                    )
                                ): ?>
                                    <button
                                        type="button"
                                        class="ticketing-member-icon-button"
                                        data-member-open="team"
                                        data-member-target="<?= ticketing_project_members_h($memberId) ?>"
                                        title="دسترسی‌های تیمی"
                                        aria-label="دسترسی‌های تیمی"
                                    >
                                        <?= ticketing_project_member_icon('team') ?>
                                    </button>
                                <?php endif; ?>


                                <form
                                    method="post"
                                    action="<?= ticketing_project_members_h(
                                        $membersUrl
                                        . '/'
                                        . $memberId
                                        . '/revoke'
                                    ) ?>"
                                    onsubmit="return confirm('عضویت این شخص در پروژه غیرفعال شود؟ سوابق حذف نمی‌شوند.');"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        value="<?= ticketing_project_members_h($csrf) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="ticketing-member-icon-button ticketing-member-icon-button--danger"
                                        title="لغو عضویت"
                                        aria-label="لغو عضویت"
                                    >
                                        <?= ticketing_project_member_icon('revoke') ?>
                                    </button>
                                </form>

                            <?php else: ?>

                                <form
                                    method="post"
                                    action="<?= ticketing_project_members_h(
                                        $membersUrl
                                        . '/'
                                        . $memberId
                                        . '/restore'
                                    ) ?>"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        value="<?= ticketing_project_members_h($csrf) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="ticketing-member-icon-button"
                                        title="فعال‌سازی عضویت"
                                        aria-label="فعال‌سازی عضویت"
                                    >
                                        <?= ticketing_project_member_icon('restore') ?>
                                    </button>
                                </form>

                            <?php endif; ?>

                        </td>
                    </tr>


                    <tr
                        class="ticketing-member-detail-row"
                        data-member-detail="<?= ticketing_project_members_h($memberId) ?>"
                        hidden
                    >
                        <td colspan="9">

                            <div class="ticketing-member-detail-shell">

                                <div class="ticketing-member-detail-toolbar">
                                    <strong>
                                        مدیریت
                                        <?= ticketing_project_members_h($displayName) ?>
                                    </strong>

                                    <button
                                        type="button"
                                        class="ticketing-member-detail-close"
                                        data-member-close="<?= ticketing_project_members_h($memberId) ?>"
                                        title="بستن"
                                        aria-label="بستن"
                                    >
                                        <?= ticketing_project_member_icon('close') ?>
                                    </button>
                                </div>


                                <?php if ($active): ?>

                                    <section
                                        class="ticketing-member-detail-panel"
                                        data-detail-role
                                    >
                                        <div>
                                            <h4>
                                                نقش و عضویت
                                            </h4>

                                            <p class="admin-muted">
                                                تغییر نقش پروژه با کنترل تیکت‌های باز انجام می‌شود.
                                            </p>
                                        </div>


                                        <form
                                            method="post"
                                            action="<?= ticketing_project_members_h(
                                                $membersUrl
                                                . '/'
                                                . $memberId
                                                . '/role'
                                            ) ?>"
                                            class="ticketing-member-role-form"
                                        >
                                            <input
                                                type="hidden"
                                                name="_token"
                                                value="<?= ticketing_project_members_h($csrf) ?>"
                                            >

                                            <select
                                                name="role_code"
                                                required
                                            >
                                                <?php foreach (
                                                    $projectRoles
                                                    as $code => $title
                                                ): ?>
                                                    <option
                                                        value="<?= ticketing_project_members_h($code) ?>"
                                                        <?= $roleCode === $code ? 'selected' : '' ?>
                                                    >
                                                        <?= ticketing_project_members_h($title) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button
                                                type="submit"
                                                class="admin-button"
                                            >
                                                ذخیره نقش
                                            </button>
                                        </form>
                                    </section>


                                    <?php if (
                                        in_array(
                                            $roleCode,
                                            [
                                                'member',
                                                'manager',
                                            ],
                                            true
                                        )
                                    ): ?>

                                        <section
                                            class="ticketing-member-detail-panel"
                                            data-detail-team
                                        >
                                            <div>
                                                <h4>
                                                    دسترسی‌های تیمی
                                                </h4>

                                                <p class="admin-muted">
                                                    عضویت و سطح دسترسی فرد در تیم‌های همین پروژه
                                                </p>
                                            </div>


                                            <?php if ($activeTeams !== []): ?>

                                                <div class="ticketing-member-team-list">

                                                    <?php foreach (
                                                        $activeTeams
                                                        as $teamMembership
                                                    ): ?>
                                                        <?php
                                                        $teamId =
                                                            (int) (
                                                                $teamMembership[
                                                                    'team_id'
                                                                ]
                                                                ?? 0
                                                            );

                                                        $teamRole =
                                                            (string) (
                                                                $teamMembership[
                                                                    'staff_role_code'
                                                                ]
                                                                ?? ''
                                                            );
                                                        ?>

                                                        <div class="ticketing-member-team-item">

                                                            <strong>
                                                                <?= ticketing_project_members_h(
                                                                    $teamMembership[
                                                                        'team_title'
                                                                    ]
                                                                    ?? ''
                                                                ) ?>
                                                            </strong>


                                                            <form
                                                                method="post"
                                                                action="<?= ticketing_project_members_h(
                                                                    $membersUrl
                                                                    . '/'
                                                                    . $memberId
                                                                    . '/team'
                                                                ) ?>"
                                                                class="ticketing-member-team-form"
                                                            >
                                                                <input
                                                                    type="hidden"
                                                                    name="_token"
                                                                    value="<?= ticketing_project_members_h($csrf) ?>"
                                                                >

                                                                <input
                                                                    type="hidden"
                                                                    name="team_id"
                                                                    value="<?= ticketing_project_members_h($teamId) ?>"
                                                                >

                                                                <select
                                                                    name="staff_role_code"
                                                                    required
                                                                >
                                                                    <?php foreach (
                                                                        $staffRoles
                                                                        as $code => $title
                                                                    ): ?>
                                                                        <option
                                                                            value="<?= ticketing_project_members_h($code) ?>"
                                                                            <?= $teamRole === $code ? 'selected' : '' ?>
                                                                        >
                                                                            <?= ticketing_project_members_h($title) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>


                                                                <?php
                                                                $flags = [
                                                                    'can_assign' =>
                                                                        'تخصیص',

                                                                    'can_observe' =>
                                                                        'مشاهده',

                                                                    'can_assist' =>
                                                                        'همکاری',

                                                                    'can_takeover' =>
                                                                        'در اختیار گرفتن',

                                                                    'can_transfer' =>
                                                                        'انتقال',
                                                                ];
                                                                ?>

                                                                <div class="ticketing-member-team-flags">

                                                                    <?php foreach (
                                                                        $flags
                                                                        as $flag => $flagTitle
                                                                    ): ?>
                                                                        <label>
                                                                            <input
                                                                                type="checkbox"
                                                                                name="<?= ticketing_project_members_h($flag) ?>"
                                                                                value="1"
                                                                                <?= !empty($teamMembership[$flag]) ? 'checked' : '' ?>
                                                                            >

                                                                            <span>
                                                                                <?= ticketing_project_members_h($flagTitle) ?>
                                                                            </span>
                                                                        </label>
                                                                    <?php endforeach; ?>

                                                                </div>


                                                                <button
                                                                    type="submit"
                                                                    class="admin-button admin-button--soft"
                                                                >
                                                                    ذخیره
                                                                </button>
                                                            </form>


                                                            <form
                                                                method="post"
                                                                action="<?= ticketing_project_members_h(
                                                                    $membersUrl
                                                                    . '/'
                                                                    . $memberId
                                                                    . '/teams/'
                                                                    . $teamId
                                                                    . '/remove'
                                                                ) ?>"
                                                                onsubmit="return confirm('دسترسی این عضو به تیم غیرفعال شود؟');"
                                                            >
                                                                <input
                                                                    type="hidden"
                                                                    name="_token"
                                                                    value="<?= ticketing_project_members_h($csrf) ?>"
                                                                >

                                                                <button
                                                                    type="submit"
                                                                    class="ticketing-member-remove-team"
                                                                >
                                                                    حذف دسترسی
                                                                </button>
                                                            </form>

                                                        </div>

                                                    <?php endforeach; ?>

                                                </div>

                                            <?php endif; ?>


                                            <?php if ($teams !== []): ?>

                                                <form
                                                    method="post"
                                                    action="<?= ticketing_project_members_h(
                                                        $membersUrl
                                                        . '/'
                                                        . $memberId
                                                        . '/team'
                                                    ) ?>"
                                                    class="ticketing-member-add-team"
                                                >
                                                    <strong>
                                                        افزودن تیم
                                                    </strong>

                                                    <input
                                                        type="hidden"
                                                        name="_token"
                                                        value="<?= ticketing_project_members_h($csrf) ?>"
                                                    >

                                                    <select
                                                        name="team_id"
                                                        required
                                                    >
                                                        <option value="">
                                                            انتخاب تیم
                                                        </option>

                                                        <?php foreach (
                                                            $teams
                                                            as $team
                                                        ): ?>
                                                            <option
                                                                value="<?= ticketing_project_members_h($team['id'] ?? '') ?>"
                                                            >
                                                                <?= ticketing_project_members_h($team['title'] ?? '') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>


                                                    <select
                                                        name="staff_role_code"
                                                        required
                                                    >
                                                        <?php foreach (
                                                            $staffRoles
                                                            as $code => $title
                                                        ): ?>
                                                            <option
                                                                value="<?= ticketing_project_members_h($code) ?>"
                                                            >
                                                                <?= ticketing_project_members_h($title) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <input
                                                        type="hidden"
                                                        name="can_observe"
                                                        value="1"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="admin-button"
                                                    >
                                                        افزودن
                                                    </button>
                                                </form>

                                            <?php endif; ?>

                                        </section>

                                    <?php endif; ?>

                                <?php endif; ?>

                            </div>

                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <div
            class="ticketing-member-show-more"
            data-member-show-more-wrap
            hidden
        >
            <button
                type="button"
                class="admin-button admin-button--soft"
                data-member-show-more
            >
                نمایش بیشتر
            </button>
        </div>


        <div
            class="admin-empty-state"
            data-member-filter-empty
            hidden
        >
            عضوی مطابق فیلتر انتخاب‌شده پیدا نشد.
        </div>

    <?php endif; ?>

</section>


<style>
.ticketing-member-access-hero{
    min-height:0;
    padding:.75rem 1rem
}

.ticketing-member-access-hero .admin-module-hub__icon{
    width:2.8rem;
    height:2.8rem;
    min-width:2.8rem
}

.ticketing-member-status{
    padding:.65rem
}


/* summary */
.ticketing-member-summary{
    padding:.75rem
}

.ticketing-member-summary__grid{
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:.45rem
}

.ticketing-member-summary__grid article{
    min-height:4rem;
    padding:.5rem .65rem;
    border:1px solid var(--admin-border);
    border-radius:.75rem;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:.08rem
}

.ticketing-member-summary__grid span{
    color:var(--admin-muted);
    font-size:.7rem
}

.ticketing-member-summary__grid strong{
    font-size:1.08rem
}


/* browser header */
.ticketing-member-browser{
    padding:.8rem
}

.ticketing-member-browser__header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:.6rem
}

.ticketing-member-browser__header h3,
.ticketing-member-browser__header p{
    margin:0
}

.ticketing-member-result-count{
    display:flex;
    align-items:center;
    gap:.3rem;
    white-space:nowrap
}


/* filters */
.ticketing-member-filters{
    display:grid;
    grid-template-columns:minmax(15rem,2fr) minmax(9rem,1fr) minmax(9rem,1fr);
    gap:.5rem;
    margin-bottom:.7rem
}

.ticketing-member-filters>label{
    display:grid;
    gap:.2rem
}

.ticketing-member-filters>label>span{
    color:var(--admin-muted);
    font-size:.7rem
}


/* table */
.ticketing-member-table-wrap{
    width:100%;
    overflow:auto;
    border:1px solid var(--admin-border);
    border-radius:.8rem
}

.ticketing-member-table{
    width:100%;
    min-width:58rem;
    border-collapse:separate;
    border-spacing:0
}

.ticketing-member-table th,
.ticketing-member-table td{
    padding:.55rem .6rem;
    border-bottom:1px solid var(--admin-border);
    vertical-align:middle
}

.ticketing-member-table th{
    position:sticky;
    top:0;
    z-index:2;
    background:var(--admin-surface);
    color:var(--admin-muted);
    font-size:.72rem;
    font-weight:700;
    white-space:nowrap
}

.ticketing-member-main-row:last-child td{
    border-bottom:0
}

.ticketing-member-main-row:hover td{
    background:var(--admin-surface-soft)
}

.ticketing-member-main-row[hidden],
.ticketing-member-detail-row[hidden]{
    display:none !important
}

.ticketing-member-name{
    width:26%;
    min-width:12rem
}

.ticketing-member-name strong{
    display:block;
    white-space:normal;
    overflow-wrap:anywhere
}

.ticketing-member-number{
    text-align:center;
    white-space:nowrap
}

.ticketing-member-actions-col{
    width:8.5rem;
    text-align:center
}

.ticketing-member-actions{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:.25rem;
    white-space:nowrap
}

.ticketing-member-actions form{
    margin:0
}


/* icon actions */
.ticketing-member-icon-button{
    width:2rem;
    height:2rem;
    min-width:2rem;
    padding:0;
    border:1px solid var(--admin-border);
    border-radius:.58rem;
    background:var(--admin-surface);
    color:inherit;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer
}

.ticketing-member-icon-button:hover{
    background:var(--admin-surface-soft)
}

.ticketing-member-icon-button svg{
    width:.95rem;
    height:.95rem
}

.ticketing-member-icon-button--danger{
    color:#b42318
}


/* expandable management row */
.ticketing-member-detail-row td{
    padding:.65rem;
    background:var(--admin-surface-soft)
}

.ticketing-member-detail-shell{
    border:1px solid var(--admin-border);
    border-radius:.75rem;
    padding:.65rem;
    background:var(--admin-surface);
    display:grid;
    gap:.65rem
}

.ticketing-member-detail-toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:1rem
}

.ticketing-member-detail-close{
    width:1.9rem;
    height:1.9rem;
    padding:0;
    border:1px solid var(--admin-border);
    border-radius:.55rem;
    background:var(--admin-surface);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer
}

.ticketing-member-detail-close svg{
    width:.9rem;
    height:.9rem
}

.ticketing-member-detail-panel{
    display:grid;
    gap:.55rem
}

.ticketing-member-detail-panel[hidden]{
    display:none !important
}

.ticketing-member-detail-panel h4,
.ticketing-member-detail-panel p{
    margin:0
}


/* role */
.ticketing-member-role-form{
    display:flex;
    align-items:center;
    gap:.45rem;
    flex-wrap:wrap
}


/* teams */
.ticketing-member-team-list{
    display:grid;
    gap:.45rem
}

.ticketing-member-team-item{
    padding:.55rem;
    border:1px solid var(--admin-border);
    border-radius:.65rem;
    display:grid;
    gap:.45rem
}

.ticketing-member-team-form{
    display:flex;
    align-items:center;
    gap:.45rem;
    flex-wrap:wrap
}

.ticketing-member-team-flags{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:.35rem .55rem
}

.ticketing-member-team-flags label{
    display:flex;
    align-items:center;
    gap:.2rem;
    font-size:.7rem
}

.ticketing-member-remove-team{
    padding:0;
    border:0;
    background:none;
    color:#b42318;
    cursor:pointer;
    font:inherit
}

.ticketing-member-add-team{
    padding-top:.5rem;
    border-top:1px dashed var(--admin-border);
    display:flex;
    gap:.45rem;
    align-items:center;
    flex-wrap:wrap
}


/* pagination */
.ticketing-member-show-more{
    display:flex;
    justify-content:center;
    margin-top:.7rem
}


@media(max-width:1100px){
    .ticketing-member-summary__grid{
        grid-template-columns:repeat(3,minmax(0,1fr))
    }
}

@media(max-width:720px){
    .ticketing-member-summary__grid{
        grid-template-columns:repeat(2,minmax(0,1fr))
    }

    .ticketing-member-filters{
        grid-template-columns:1fr
    }

    .ticketing-member-browser__header{
        align-items:flex-start;
        flex-direction:column
    }
}

/* TICKETING_PROJECT_MEMBER_BULK_SELECTION_STYLE */

.ticketing-member-selection-toolbar{
    min-height:2.7rem;
    margin-bottom:.65rem;
    padding:.45rem .55rem;
    border:1px solid var(--admin-border);
    border-radius:.72rem;
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:.45rem;
    background:var(--admin-surface-soft)
}

.ticketing-member-selection-toolbar__count{
    min-width:6.5rem;
    display:flex;
    align-items:center;
    gap:.3rem
}

.ticketing-member-selection-toolbar__count strong{
    font-size:1rem
}

.ticketing-member-selection-toolbar__hint{
    margin-inline-start:auto;
    font-size:.7rem
}

.ticketing-member-select-col{
    width:2.7rem;
    min-width:2.7rem;
    text-align:center !important
}

.ticketing-member-select-col input[type="checkbox"]{
    width:1rem;
    height:1rem;
    margin:0;
    vertical-align:middle;
    cursor:pointer
}

.ticketing-member-main-row.ticketing-member-row--selected td{
    background:var(--admin-surface-soft)
}

@media(max-width:720px){
    .ticketing-member-selection-toolbar__hint{
        width:100%;
        margin-inline-start:0
    }
}




/* TICKETING_MEMBER_ACTION_VISUAL_CONTRACT */

.ticketing-standard-page-head{
    margin-bottom:1rem;
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:1rem
}

.ticketing-standard-page-head__main{
    min-width:0
}

.ticketing-standard-page-head h2{
    margin:0 0 .35rem;
    font-size:1.4rem;
    line-height:1.5
}

.ticketing-standard-page-head p{
    margin:0;
    color:var(--admin-muted);
    font-size:.84rem
}


/*
 * Row actions:
 * - routine actions are neutral
 * - destructive actions are red
 * - no unexplained primary-green square action
 */
.ticketing-member-actions > button,
.ticketing-member-actions > form > button,
.ticketing-member-actions .ticketing-member-icon-button{
    width:2rem !important;
    height:2rem !important;
    min-width:2rem !important;
    padding:0 !important;
    border:1px solid var(--admin-border) !important;
    border-radius:.58rem !important;
    background:var(--admin-surface) !important;
    color:var(--admin-text) !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    box-shadow:none !important
}

.ticketing-member-actions > button:hover,
.ticketing-member-actions > form > button:hover,
.ticketing-member-actions .ticketing-member-icon-button:hover{
    background:var(--admin-surface-soft) !important
}

.ticketing-member-actions svg{
    width:.95rem !important;
    height:.95rem !important;
    min-width:.95rem !important;
    display:block !important
}

.ticketing-member-actions
.ticketing-member-icon-button--danger,
.ticketing-member-actions
.ticketing-member-icon-button--danger:hover{
    color:#b42318 !important
}


/*
 * Primary green is reserved for explicit primary actions with text.
 * Icon-only row actions may never inherit green primary styling.
 */
.ticketing-member-actions
.admin-button{
    background:var(--admin-surface) !important;
    color:var(--admin-text) !important
}


@media(max-width:720px){
    .ticketing-standard-page-head{
        align-items:flex-start;
        flex-direction:column
    }
}


</style>


<script>
(() => {
    const rows =
        Array.from(
            document.querySelectorAll(
                '[data-member-row]'
            )
        );

    if (rows.length === 0) {
        return;
    }

    const search =
        document.querySelector(
            '[data-member-search]'
        );

    const role =
        document.querySelector(
            '[data-member-role-filter]'
        );

    const status =
        document.querySelector(
            '[data-member-status-filter]'
        );

    const resultCount =
        document.querySelector(
            '[data-member-result-count]'
        );

    const moreWrap =
        document.querySelector(
            '[data-member-show-more-wrap]'
        );

    const more =
        document.querySelector(
            '[data-member-show-more]'
        );

    const empty =
        document.querySelector(
            '[data-member-filter-empty]'
        );

    const selectAll =
        document.querySelector(
            '[data-member-select-all]'
        );

    const selectFiltered =
        document.querySelector(
            '[data-member-select-filtered]'
        );

    const clearSelection =
        document.querySelector(
            '[data-member-clear-selection]'
        );

    const selectedCount =
        document.querySelector(
            '[data-member-selected-count]'
        );

    const PAGE_SIZE = 24;

    let limit =
        PAGE_SIZE;

    let currentMatched =
        [];

    const selectedIds =
        new Set();


    const normalize =
        value =>
            String(value ?? '')
                .trim()
                .toLocaleLowerCase(
                    'fa-IR'
                );


    const detailFor =
        row =>
            document.querySelector(
                '[data-member-detail="'
                + row.dataset.memberId
                + '"]'
            );


    const closeAll =
        exceptId => {

            document
                .querySelectorAll(
                    '[data-member-detail]'
                )
                .forEach(
                    detail => {

                        if (
                            exceptId
                            &&
                            detail.dataset.memberDetail
                                === exceptId
                        ) {
                            return;
                        }

                        detail.hidden =
                            true;
                    }
                );
        };


    const checkboxFor =
        row =>
            row.querySelector(
                '[data-member-select]'
            );


    const syncSelectionUi =
        () => {

            rows.forEach(
                row => {

                    const checkbox =
                        checkboxFor(row);

                    if (!checkbox) {
                        return;
                    }

                    const selected =
                        selectedIds.has(
                            row.dataset.memberId
                        );

                    checkbox.checked =
                        selected;

                    row.classList.toggle(
                        'ticketing-member-row--selected',
                        selected
                    );
                }
            );


            const filteredIds =
                currentMatched.map(
                    row =>
                        row.dataset.memberId
                );

            const filteredSelected =
                filteredIds.filter(
                    id =>
                        selectedIds.has(id)
                ).length;


            if (selectAll) {
                selectAll.checked =
                    (
                        filteredIds.length > 0
                        &&
                        filteredSelected
                            === filteredIds.length
                    );

                selectAll.indeterminate =
                    (
                        filteredSelected > 0
                        &&
                        filteredSelected
                            < filteredIds.length
                    );
            }


            if (selectedCount) {
                selectedCount.textContent =
                    selectedIds.size
                        .toLocaleString(
                            'fa-IR'
                        );
            }


            if (clearSelection) {
                clearSelection.disabled =
                    selectedIds.size === 0;
            }
        };


    const apply =
        reset => {

            if (reset) {
                limit =
                    PAGE_SIZE;

                closeAll();
            }

            const query =
                normalize(
                    search?.value
                );

            const roleValue =
                role?.value
                ?? '';

            const statusValue =
                status?.value
                ?? '';

            const matched =
                rows.filter(
                    row => {

                        const name =
                            normalize(
                                row.dataset.memberName
                            );

                        return (
                            (
                                query === ''
                                ||
                                name.includes(
                                    query
                                )
                            )
                            &&
                            (
                                roleValue === ''
                                ||
                                row.dataset.memberRole
                                    === roleValue
                            )
                            &&
                            (
                                statusValue === ''
                                ||
                                row.dataset.memberStatus
                                    === statusValue
                            )
                        );
                    }
                );


            currentMatched =
                matched;


            rows.forEach(
                row => {

                    row.hidden =
                        true;

                    const detail =
                        detailFor(row);

                    if (detail) {
                        detail.hidden =
                            true;
                    }
                }
            );


            matched
                .slice(
                    0,
                    limit
                )
                .forEach(
                    row => {
                        row.hidden =
                            false;
                    }
                );


            if (resultCount) {
                resultCount.textContent =
                    matched.length
                        .toLocaleString(
                            'fa-IR'
                        );
            }

            if (empty) {
                empty.hidden =
                    matched.length !== 0;
            }

            if (moreWrap) {
                moreWrap.hidden =
                    matched.length <= limit;
            }

            syncSelectionUi();
        };


    document.addEventListener(
        'change',
        event => {

            const checkbox =
                event.target.closest(
                    '[data-member-select]'
                );

            if (!checkbox) {
                return;
            }

            const id =
                String(
                    checkbox.dataset
                        .memberSelectId
                    ?? ''
                );

            if (id === '') {
                return;
            }

            if (checkbox.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }

            syncSelectionUi();
        }
    );


    selectAll?.addEventListener(
        'change',
        () => {

            currentMatched.forEach(
                row => {

                    const id =
                        row.dataset.memberId;

                    if (!id) {
                        return;
                    }

                    if (selectAll.checked) {
                        selectedIds.add(id);
                    } else {
                        selectedIds.delete(id);
                    }
                }
            );

            syncSelectionUi();
        }
    );


    selectFiltered?.addEventListener(
        'click',
        () => {

            currentMatched.forEach(
                row => {

                    const id =
                        row.dataset.memberId;

                    if (id) {
                        selectedIds.add(id);
                    }
                }
            );

            syncSelectionUi();
        }
    );


    clearSelection?.addEventListener(
        'click',
        () => {

            selectedIds.clear();

            syncSelectionUi();
        }
    );


    document.addEventListener(
        'click',
        event => {

            const open =
                event.target.closest(
                    '[data-member-open]'
                );

            if (open) {
                const id =
                    open.dataset.memberTarget;

                const mode =
                    open.dataset.memberOpen;

                const detail =
                    document.querySelector(
                        '[data-member-detail="'
                        + id
                        + '"]'
                    );

                if (!detail) {
                    return;
                }

                const wasHidden =
                    detail.hidden;

                closeAll(id);

                detail.hidden =
                    !wasHidden;

                if (!detail.hidden) {
                    detail
                        .querySelectorAll(
                            '[data-detail-role], [data-detail-team]'
                        )
                        .forEach(
                            panel => {
                                panel.hidden =
                                    (
                                        mode === 'role'
                                        &&
                                        panel.hasAttribute(
                                            'data-detail-team'
                                        )
                                    )
                                    ||
                                    (
                                        mode === 'team'
                                        &&
                                        panel.hasAttribute(
                                            'data-detail-role'
                                        )
                                    );
                            }
                        );
                }

                return;
            }


            const close =
                event.target.closest(
                    '[data-member-close]'
                );

            if (close) {
                const detail =
                    document.querySelector(
                        '[data-member-detail="'
                        + close.dataset.memberClose
                        + '"]'
                    );

                if (detail) {
                    detail.hidden =
                        true;
                }
            }
        }
    );


    search?.addEventListener(
        'input',
        () => apply(true)
    );

    role?.addEventListener(
        'change',
        () => apply(true)
    );

    status?.addEventListener(
        'change',
        () => apply(true)
    );

    more?.addEventListener(
        'click',
        () => {
            limit +=
                PAGE_SIZE;

            apply(false);
        }
    );


    apply(true);
})();
</script>


<?php
$content =
    ob_get_clean();

require __DIR__
    . '/layout.php';
