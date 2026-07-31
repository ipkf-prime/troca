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

ob_start();
?>
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
                    نقش فعال، محدوده و نقش‌های قابل انتخاب حساب شما
                </p>
            </div>
            <span class="account-badge account-badge--success">
                <?= admin_h($active['role_title'] ?? 'بدون نقش') ?>
            </span>
        </div>

        <?php if ($assignments === []): ?>
            <div class="account-notice account-notice--info">
                نقش فعالی برای این حساب ثبت نشده است.
            </div>
        <?php else: ?>
            <div class="role-cards">
                <?php foreach ($assignments as $assignment): ?>
                    <?php
                    $isActive = (int) ($active['id'] ?? 0)
                        === (int) ($assignment['id'] ?? 0);
                    ?>
                    <article class="role-card<?= $isActive
                        ? ' is-active'
                        : '' ?>">
                        <div class="role-card__head">
                            <div>
                                <strong>
                                    <?= admin_h(
                                        $assignment['role_title'] ?? ''
                                    ) ?>
                                </strong>
                                <small>
                                    <?= admin_h(
                                        $assignment['role_code'] ?? ''
                                    ) ?>
                                </small>
                            </div>
                            <span class="account-badge <?= $isActive
                                ? 'account-badge--success'
                                : '' ?>">
                                <?= $isActive ? 'فعال' : 'قابل انتخاب' ?>
                            </span>
                        </div>

                        <div class="role-card__meta">
                            <span>
                                محدوده:
                                <?= admin_h(
                                    $assignment['scope_type']
                                    ?? 'global'
                                ) ?>
                            </span>
                            <span>
                                اولویت:
                                <?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $assignment['priority'] ?? '—'
                                    )
                                ) ?>
                            </span>
                        </div>

                        <?php if (!$isActive): ?>
                            <form
                                method="post"
                                action="/admin/profile/access"
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    value="<?= admin_h(
                                        (new \IPKF\Security\Csrf())
                                            ->token()
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
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
