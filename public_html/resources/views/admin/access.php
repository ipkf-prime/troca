<?php

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$status =
    trim(
        (string) (
            $status
            ?? ''
        )
    );

$canManageAccessControl =
    (bool) (
        $canManageAccessControl
        ?? false
    );

$assignments =
    is_array(
        $context['assignments']
        ?? null
    )
        ? $context['assignments']
        : [];

$activeAssignmentId =
    (int) (
        $context['active_assignment']['id']
        ?? 0
    );

$scopeLabels = [
    'global' => 'سراسری',
    'national' => 'ملی',
    'province' => 'استان',
    'county' => 'شهرستان',
    'city' => 'شهر',
    'organization' => 'سازمان',
    'company' => 'شرکت',
    'warehouse' => 'انبار',
    'center' => 'مرکز',
    'org_unit' => 'واحد سازمانی',
    'project' => 'پروژه',
    'own' => 'فقط خود کاربر',
    'assigned' => 'موارد واگذارشده',
];

$scopeLabel =
    static function (
        mixed $value
    ) use ($scopeLabels): string {
        $code =
            strtolower(
                trim(
                    (string) $value
                )
            );

        return
            $scopeLabels[$code]
            ?? (
                $code !== ''
                    ? $code
                    : 'سراسری'
            );
    };

$notices = [
    'switched' => [
        'ok',
        'نقش فعال تغییر کرد.',
    ],
    'forbidden' => [
        'error',
        'امکان فعال‌سازی این نقش وجود ندارد.',
    ],
    'access_management_moved' => [
        'ok',
        'مدیریت نقش‌ها و مجوزها به مرکز کنترل دسترسی منتقل شده است.',
    ],
];

ob_start();
?>

<style>
.active-access-page {
    display:grid;
    gap:.85rem;
}

.active-access-hero,
.active-access-card,
.active-access-governance {
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:.95rem;
}

.active-access-hero {
    align-items:center;
    display:flex;
    gap:.8rem;
    justify-content:space-between;
    padding:.85rem 1rem;
}

.active-access-hero h2 {
    font-size:1rem;
    margin:0;
}

.active-access-hero p {
    color:var(--admin-text-muted);
    font-size:.72rem;
    line-height:1.8;
    margin:.15rem 0 0;
}

.active-access-card {
    padding:.9rem;
}

.active-access-card__head {
    margin-bottom:.7rem;
}

.active-access-card__head h3 {
    font-size:.88rem;
    margin:0;
}

.active-access-card__head p {
    color:var(--admin-text-muted);
    font-size:.69rem;
    line-height:1.75;
    margin:.15rem 0 0;
}

.active-access-table {
    table-layout:auto;
    width:100%;
}

.active-access-table th,
.active-access-table td {
    vertical-align:middle;
}

.active-access-governance {
    align-items:center;
    display:flex;
    gap:.75rem;
    justify-content:space-between;
    padding:.8rem .9rem;
}

.active-access-governance strong {
    display:block;
    font-size:.8rem;
}

.active-access-governance small {
    color:var(--admin-text-muted);
    display:block;
    font-size:.66rem;
    line-height:1.7;
    margin-top:.12rem;
}

@media (max-width:760px) {
    .active-access-hero,
    .active-access-governance {
        align-items:stretch;
        display:grid;
    }
}
</style>

<div class="active-access-page">

    <nav class="admin-breadcrumb" aria-label="breadcrumb">
        <a href="/admin/dashboard">داشبورد</a>
        <span>/</span>
        <span>نقش فعال</span>
    </nav>

    <?php if (isset($notices[$status])): ?>
        <?php [$noticeType, $noticeText] = $notices[$status]; ?>

        <div class="<?= $noticeType === 'ok'
            ? 'admin-notice'
            : 'admin-alert' ?>">
            <?= admin_h($noticeText) ?>
        </div>
    <?php endif; ?>

    <header class="active-access-hero">
        <div>
            <h2>نقش فعال من</h2>
            <p>
                این صفحه فقط برای انتخاب Context فعال کاربر است.
                تعریف نقش، مجوز، حوزه و انتساب از مرکز کنترل دسترسی انجام می‌شود.
            </p>
        </div>

        <a
            class="admin-button admin-button--soft"
            href="/admin/profile/access"
        >
            مشاهده دسترسی‌های من
        </a>
    </header>

    <section class="active-access-card">
        <div class="active-access-card__head">
            <h3>انتخاب نقش فعال</h3>
            <p>
                در صورت داشتن چند نقش، نقشی را که می‌خواهید
                پنل با آن اجرا شود انتخاب کنید.
            </p>
        </div>

        <?php if ($assignments === []): ?>
            <div class="admin-empty-state">
                نقش فعالی برای این حساب ثبت نشده است.
            </div>
        <?php else: ?>

            <div class="admin-table-wrap">
                <table class="admin-table active-access-table">
                    <thead>
                        <tr>
                            <th>نقش</th>
                            <th>کد</th>
                            <th>حوزه</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <?php
                            $assignmentId =
                                (int) (
                                    $assignment['id']
                                    ?? 0
                                );

                            $isActive =
                                $assignmentId
                                === $activeAssignmentId;
                            ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= admin_h(
                                            $assignment['role_title']
                                            ?? ''
                                        ) ?>
                                    </strong>
                                </td>

                                <td dir="ltr">
                                    <code>
                                        <?= admin_h(
                                            $assignment['role_code']
                                            ?? ''
                                        ) ?>
                                    </code>
                                </td>

                                <td>
                                    <?= admin_h(
                                        $scopeLabel(
                                            $assignment['scope_type']
                                            ?? 'global'
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= admin_h(
                                        \App\Support\AdminFormat::digits(
                                            $assignment['priority']
                                            ?? ''
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="admin-pill">
                                            فعال
                                        </span>
                                    <?php else: ?>
                                        <span class="admin-muted">
                                            غیرفعال
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="admin-pill">
                                            نقش جاری
                                        </span>
                                    <?php else: ?>
                                        <form
                                            method="post"
                                            action="/admin/access"
                                            class="admin-inline-form"
                                        >
                                            <input
                                                type="hidden"
                                                name="_token"
                                                value="<?= admin_h(
                                                    (
                                                        new \IPKF\Security\Csrf()
                                                    )->token()
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="role_assignment_id"
                                                value="<?= $assignmentId ?>"
                                            >

                                            <button
                                                class="admin-button admin-button--compact"
                                                type="submit"
                                            >
                                                فعال‌سازی
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

    <?php if ($canManageAccessControl): ?>
        <section class="active-access-governance">
            <div>
                <strong>مدیریت نقش و دسترسی</strong>
                <small>
                    تعریف نقش، مجوزها، انتساب کاربر،
                    حوزه و محدودیت فقط در مرکز کنترل دسترسی انجام می‌شود.
                </small>
            </div>

            <a
                class="admin-button"
                href="/admin/access-control"
            >
                ورود به مرکز کنترل دسترسی
            </a>
        </section>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
