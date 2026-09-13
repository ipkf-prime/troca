<?php

if (
    !function_exists(
        'admin_h'
    )
) {
    function admin_h(
        $value
    ): string {

        return
            htmlspecialchars(
                (string) (
                    $value
                    ?? ''
                ),
                ENT_QUOTES
                |
                ENT_SUBSTITUTE,
                'UTF-8',
                false
            );
    }
}


$status =
    $status
    ?? '';


$page =
    is_array(
        $page
        ?? null
    )
        ? $page
        : [];


$items =
    is_array(
        $page[
            'items'
        ]
        ?? null
    )
        ?
        $page[
            'items'
        ]
        : [];


$roles =
    is_array(
        $page[
            'roles'
        ]
        ?? null
    )
        ?
        $page[
            'roles'
        ]
        : [];


$filter =
    (string) (
        $page[
            'filter'
        ]
        ?? 'pending'
    );


$canGrantAccess =
    !empty(
        $page[
            'can_grant_access'
        ]
    );


$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();


$filters = [
    'pending' =>
        'در انتظار',

    'verified' =>
        'تأییدشده',

    'rejected' =>
        'ردشده',

    'all' =>
        'همه',
];


ob_start();
?>

<style>
.account-notice {
    border:1px solid var(--admin-border);
    border-radius:.75rem;
    font-size:.72rem;
    margin-bottom:.75rem;
    padding:.65rem .75rem;
}

.account-notice--success {
    background:var(--admin-primary-soft);
    color:var(--admin-primary);
}

.account-notice--info {
    background:var(--admin-surface-muted);
}

.account-notice--danger {
    background:#fff1f2;
    color:#be123c;
}

.account-badge {
    align-items:center;
    background:var(--admin-surface-muted);
    border:1px solid var(--admin-border);
    border-radius:999px;
    display:inline-flex;
    font-size:.65rem;
    font-weight:800;
    min-height:1.75rem;
    padding:.2rem .5rem;
}

.account-badge--success {
    background:var(--admin-primary-soft);
    color:var(--admin-primary);
}

.account-badge--danger {
    background:#fff1f2;
    color:#be123c;
}

.affiliation-admin-toolbar {
    align-items:center;
    display:flex;
    flex-wrap:wrap;
    gap:.45rem;
    justify-content:space-between;
    margin-bottom:.8rem;
}

.affiliation-admin-filters {
    display:flex;
    flex-wrap:wrap;
    gap:.35rem;
}

.affiliation-admin-filters a {
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:999px;
    color:var(--admin-text-muted);
    font-size:.7rem;
    font-weight:800;
    padding:.35rem .65rem;
    text-decoration:none;
}

.affiliation-admin-filters a.is-active {
    background:var(--admin-primary-soft);
    color:var(--admin-primary);
}

.affiliation-admin-list {
    display:grid;
    gap:.7rem;
}

.affiliation-admin-card {
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:.85rem;
    display:grid;
    gap:.7rem;
    padding:.8rem;
}

.affiliation-admin-card__head {
    align-items:flex-start;
    display:flex;
    gap:.7rem;
    justify-content:space-between;
}

.affiliation-admin-card__head h3 {
    font-size:.88rem;
    margin:0;
}

.affiliation-admin-card__head p {
    color:var(--admin-text-muted);
    font-size:.68rem;
    margin:.15rem 0 0;
}

.affiliation-admin-facts {
    display:grid;
    gap:.5rem;
    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );
}

.affiliation-admin-fact {
    background:var(--admin-surface-muted);
    border:1px solid var(--admin-border);
    border-radius:.65rem;
    padding:.5rem;
}

.affiliation-admin-fact span,
.affiliation-admin-fact strong {
    display:block;
}

.affiliation-admin-fact span {
    color:var(--admin-text-muted);
    font-size:.62rem;
}

.affiliation-admin-fact strong {
    font-size:.72rem;
    margin-top:.15rem;
}

.affiliation-admin-actions {
    align-items:end;
    display:grid;
    gap:.55rem;
    grid-template-columns:
        minmax(0,1fr)
        auto;
}

.affiliation-admin-actions select {
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:.65rem;
    color:var(--admin-text);
    font:inherit;
    min-height:2.55rem;
    width:100%;
}

.affiliation-admin-inline {
    align-items:center;
    display:flex;
    font-size:.66rem;
    gap:.35rem;
    margin-top:.35rem;
}

@media(max-width:850px) {
    .affiliation-admin-facts {
        grid-template-columns:1fr;
    }

    .affiliation-admin-actions {
        grid-template-columns:1fr;
    }
}
</style>


<?php if (
    $status === 'approved'
): ?>

    <div class="account-notice account-notice--success">
        وابستگی سازمانی تأیید شد.
    </div>

<?php elseif (
    $status === 'approved_access_assigned'
): ?>

    <div class="account-notice account-notice--success">
        وابستگی تأیید و نقش دسترسی با حوزه سازمانی اعمال شد.
    </div>

<?php elseif (
    $status === 'approved_access_pending'
): ?>

    <div class="account-notice account-notice--info">
        وابستگی تأیید شد؛ تخصیص دسترسی کامل نشد و باید
        از مرکز دسترسی بررسی شود. دسترسی اضافی فعال نشده است.
    </div>

<?php elseif (
    $status === 'rejected'
): ?>

    <div class="account-notice account-notice--success">
        درخواست وابستگی رد شد.
    </div>

<?php elseif (
    $status === 'invalid_csrf'
): ?>

    <div class="account-notice account-notice--danger">
        نشست فرم معتبر نیست. صفحه را دوباره بارگذاری کنید.
    </div>

<?php elseif (
    $status === 'decision_failed'
): ?>

    <div class="account-notice account-notice--danger">
        عملیات انجام نشد. محدوده دسترسی، نقش انتخابی
        و وضعیت درخواست را بررسی کنید.
    </div>

<?php endif; ?>


<div class="affiliation-admin-toolbar">

    <div>

        <h2
            style="margin:0;font-size:1rem"
        >
            مدیریت وابستگی‌های سازمانی
        </h2>

        <p
            style="
                margin:.15rem 0 0;
                color:var(--admin-text-muted);
                font-size:.7rem
            "
        >
            تأیید محل سازمانی و سمت واقعی مستقل از نقش دسترسی سامانه انجام می‌شود.
        </p>

    </div>


    <div class="affiliation-admin-filters">

        <?php foreach (
            $filters
            as $code => $label
        ): ?>

            <a
                href="/admin/organization-affiliations?filter=<?= admin_h(
                    $code
                ) ?>"
                class="<?= $filter === $code
                    ? 'is-active'
                    : '' ?>"
            >
                <?= admin_h(
                    $label
                ) ?>
            </a>

        <?php endforeach; ?>

    </div>

</div>


<?php if (
    $items === []
): ?>

    <div class="account-notice account-notice--info">
        در این محدوده موردی برای نمایش وجود ندارد.
    </div>

<?php else: ?>

    <div class="affiliation-admin-list">

        <?php foreach (
            $items
            as $item
        ): ?>

            <?php

            $state =
                (string) (
                    $item[
                        'workflow_state'
                    ]
                    ?? 'pending'
                );

            ?>

            <article class="affiliation-admin-card">

                <div class="affiliation-admin-card__head">

                    <div>

                        <h3>
                            <?= admin_h(
                                $item[
                                    'person_title'
                                ]
                                ??
                                $item[
                                    'username'
                                ]
                                ?? ''
                            ) ?>
                        </h3>

                        <p>
                            <?= admin_h(
                                $item[
                                    'organization_title'
                                ]
                                ?? ''
                            ) ?>
                        </p>

                    </div>


                    <span
                        class="account-badge<?= $state === 'verified'
                            ? ' account-badge--success'
                            : (
                                $state === 'rejected'
                                    ? ' account-badge--danger'
                                    : ''
                              ) ?>"
                    >
                        <?= $state === 'verified'
                            ? 'تأییدشده'
                            : (
                                $state === 'rejected'
                                    ? 'ردشده'
                                    : 'در انتظار'
                              ) ?>
                    </span>

                </div>


                <div class="affiliation-admin-facts">

                    <div class="affiliation-admin-fact">

                        <span>
                            سازمان / شرکت
                        </span>

                        <strong>
                            <?= admin_h(
                                $item[
                                    'organization_title'
                                ]
                                ?? ''
                            ) ?>
                        </strong>

                    </div>


                    <div class="affiliation-admin-fact">

                        <span>
                            سمت درخواستی
                        </span>

                        <strong>
                            <?= admin_h(
                                $item[
                                    'requested_position_title'
                                ]
                                ??
                                'بدون پست مشخص'
                            ) ?>
                        </strong>

                    </div>


                    <div class="affiliation-admin-fact">

                        <span>
                            نوع وابستگی
                        </span>

                        <strong>
                            <?= !empty(
                                $item[
                                    'requested_primary'
                                ]
                            )
                                ? 'اصلی'
                                : 'عادی' ?>
                        </strong>

                    </div>

                </div>


                <?php if (
                    $state === 'pending'
                ): ?>

                    <div class="affiliation-admin-actions">

                        <form
                            method="post"
                            action="/admin/organization-affiliations/decision"
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
                                name="membership_reference"
                                value="<?= admin_h(
                                    $item[
                                        'public_reference'
                                    ]
                                    ?? ''
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="decision"
                                value="approve"
                            >


                            <?php if (
                                $canGrantAccess
                                &&
                                $roles !== []
                            ): ?>

                                <select name="role_id">

                                    <option value="0">
                                        فقط تأیید عضویت؛ بدون نقش دسترسی جدید
                                    </option>

                                    <?php foreach (
                                        $roles
                                        as $role
                                    ): ?>

                                        <option
                                            value="<?= (int) (
                                                $role[
                                                    'id'
                                                ]
                                                ?? 0
                                            ) ?>"
                                        >
                                            <?= admin_h(
                                                $role[
                                                    'title'
                                                ]
                                                ??
                                                $role[
                                                    'code'
                                                ]
                                                ?? ''
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>


                                <label class="affiliation-admin-inline">

                                    <input
                                        type="checkbox"
                                        name="include_descendants"
                                        value="1"
                                        checked
                                    >

                                    شامل زیرمجموعه‌های ساختار

                                </label>

                            <?php else: ?>

                                <input
                                    type="hidden"
                                    name="role_id"
                                    value="0"
                                >

                                <small
                                    style="
                                        color:
                                        var(--admin-text-muted)
                                    "
                                >
                                    این نقش فعال اجازه تخصیص دسترسی ندارد؛
                                    عضویت و سمت قابل تأیید است.
                                </small>

                            <?php endif; ?>


                            <button
                                type="submit"
                                style="margin-top:.45rem"
                            >
                                تأیید
                            </button>

                        </form>


                        <form
                            method="post"
                            action="/admin/organization-affiliations/decision"
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
                                name="membership_reference"
                                value="<?= admin_h(
                                    $item[
                                        'public_reference'
                                    ]
                                    ?? ''
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="decision"
                                value="reject"
                            >

                            <input
                                type="hidden"
                                name="role_id"
                                value="0"
                            >

                            <button
                                class="admin-button admin-button--soft"
                                type="submit"
                            >
                                رد درخواست
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </article>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<?php

$content =
    ob_get_clean();

require
    __DIR__
    . '/layout.php';
