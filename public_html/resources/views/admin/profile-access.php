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

$status = $status ?? '';
$assignments = $context['assignments'] ?? [];
$active = $context['active_assignment'] ?? [];
$activeId = (int) ($active['id'] ?? 0);

if ($activeId < 1) {
    foreach ($assignments as $assignment) {
        if ((string) ($assignment['role_code'] ?? '') === 'user') {
            $active = $assignment;
            $activeId = (int) ($assignment['id'] ?? 0);
            break;
        }
    }
}

if ($activeId < 1 && $assignments !== []) {
    $active = $assignments[0];
    $activeId = (int) ($active['id'] ?? 0);
}

$scopeLabel = static function (array $assignment): string {
    return match (strtolower((string) ($assignment['scope_type'] ?? 'global'))) {
        'global' => 'سراسری',
        'organization' => 'سازمان',
        'org_unit' => 'واحد سازمانی',
        'province' => 'استان',
        'county' => 'شهرستان',
        'city' => 'شهر',
        default => 'محدوده اختصاصی',
    };
};

$scopeReference = static function (array $assignment) use ($scopeLabel): string {
    foreach ([
        'scope_title',
        'organization_title',
        'org_unit_title',
        'province_title',
        'county_title',
        'city_title',
    ] as $field) {
        $value = trim((string) ($assignment[$field] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return $scopeLabel($assignment) === 'سراسری'
        ? 'کل سامانه'
        : 'در زمان انتصاب تعیین می‌شود';
};

ob_start();
?>
<style>
.profile-role-grid {
    display:grid;
    gap:.75rem;
    grid-template-columns:repeat(2,minmax(0,1fr));
}

.profile-role-card {
    background:var(--admin-surface-muted);
    border:1px solid var(--admin-border);
    border-radius:.85rem;
    display:grid;
    gap:.75rem;
    padding:.8rem;
}

.profile-role-card.is-active {
    background:color-mix(
        in srgb,
        var(--admin-primary-soft) 72%,
        var(--admin-surface)
    );
    border-color:color-mix(
        in srgb,
        var(--admin-primary) 45%,
        var(--admin-border)
    );
    box-shadow:inset -4px 0 0 var(--admin-primary);
}

.profile-role-card__head {
    align-items:flex-start;
    display:flex;
    gap:.65rem;
    justify-content:space-between;
}

.profile-role-card__title strong {
    display:block;
    font-size:.84rem;
}

.profile-role-card__title code {
    color:var(--admin-text-muted);
    direction:ltr;
    display:inline-flex;
    font-size:.65rem;
    margin-top:.15rem;
}

.profile-role-fields {
    display:grid;
    gap:.5rem;
    grid-template-columns:repeat(3,minmax(0,1fr));
}

.profile-role-field {
    background:var(--admin-surface);
    border:1px solid var(--admin-border);
    border-radius:.62rem;
    min-height:3.3rem;
    padding:.5rem;
}

.profile-role-field span,
.profile-role-field strong {
    display:block;
}

.profile-role-field span {
    color:var(--admin-text-muted);
    font-size:.62rem;
    margin-bottom:.16rem;
}

.profile-role-field strong {
    font-size:.7rem;
    line-height:1.7;
    overflow-wrap:anywhere;
}

.profile-role-card__action {
    align-items:center;
    display:flex;
    justify-content:flex-end;
}

.profile-role-card__action .admin-button {
    min-width:8.5rem;
}

.profile-role-card__active-button {
    cursor:default;
    opacity:1;
}

@media (max-width:900px) {
    .profile-role-grid {
        grid-template-columns:1fr;
    }
}

@media (max-width:560px) {
    .profile-role-fields {
        grid-template-columns:1fr;
    }

    .profile-role-card__action .admin-button {
        width:100%;
    }
}
</style>

<div class="account-shell">
    <?php require __DIR__ . '/partials/account-nav.php'; ?>

    <?php if ($status === 'switched'): ?>
        <div class="account-notice account-notice--success">
            نقش فعال با موفقیت تغییر کرد.
        </div>
    <?php elseif ($status === 'forbidden'): ?>
        <div class="account-notice account-notice--danger">
            امکان تغییر به این نقش وجود ندارد.
        </div>
    <?php endif; ?>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h2>نقش‌ها و دسترسی‌ها</h2>
                <p>
                    نقش فعال، حوزه دسترسی و مرجع محدوده هر انتصاب
                </p>
            </div>
            <span class="account-badge account-badge--success">
                نقش فعال:
                <?= admin_h($active['role_title'] ?? 'کاربر') ?>
            </span>
        </div>

        <?php if ($assignments === []): ?>
            <div class="account-notice account-notice--info">
                نقش پایه «کاربر» برای این حساب در نظر گرفته می‌شود.
            </div>
        <?php else: ?>
            <div class="profile-role-grid">
                <?php foreach ($assignments as $assignment): ?>
                    <?php
                    $isActive = $activeId === (int) ($assignment['id'] ?? 0);
                    $roleCode = (string) ($assignment['role_code'] ?? '');
                    ?>
                    <article class="profile-role-card<?= $isActive
                        ? ' is-active'
                        : '' ?>">
                        <div class="profile-role-card__head">
                            <div class="profile-role-card__title">
                                <strong>
                                    <?= admin_h(
                                        $assignment['role_title'] ?? 'کاربر'
                                    ) ?>
                                </strong>
                                <code><?= admin_h($roleCode) ?></code>
                            </div>

                            <span class="account-badge <?= $isActive
                                ? 'account-badge--success'
                                : '' ?>">
                                <?= $isActive
                                    ? 'انتخاب‌شده'
                                    : 'قابل انتخاب' ?>
                            </span>
                        </div>

                        <div class="profile-role-fields">
                            <div class="profile-role-field">
                                <span>نوع دسترسی</span>
                                <strong>
                                    <?= $roleCode === 'user'
                                        ? 'نقش پایه'
                                        : 'نقش مدیریتی' ?>
                                </strong>
                            </div>

                            <div class="profile-role-field">
                                <span>حوزه دسترسی</span>
                                <strong>
                                    <?= admin_h($scopeLabel($assignment)) ?>
                                </strong>
                            </div>

                            <div class="profile-role-field">
                                <span>مرجع حوزه</span>
                                <strong>
                                    <?= admin_h(
                                        $scopeReference($assignment)
                                    ) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="profile-role-card__action">
                            <?php if ($isActive): ?>
                                <span
                                    class="admin-button admin-button--soft profile-role-card__active-button"
                                    aria-current="true"
                                >
                                    نقش فعال
                                </span>
                            <?php else: ?>
                                <form
                                    method="post"
                                    action="/admin/profile/access"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        value="<?= admin_h(
                                            (new \IPKF\Security\Csrf())->token()
                                        ) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="role_assignment_id"
                                        value="<?= (int) (
                                            $assignment['id'] ?? 0
                                        ) ?>"
                                    >
                                    <button type="submit">
                                        انتخاب این نقش
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
