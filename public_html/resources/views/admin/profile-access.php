<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$status = $status ?? '';
$assignments = $context['assignments'] ?? [];
$active = $context['active_assignment'] ?? [];

ob_start();
?>
<?php require __DIR__ . '/partials/account-nav.php'; ?>

<?php if ($status === 'switched'): ?>
    <div class="admin-notice">نقش فعال تغییر کرد.</div>
<?php elseif ($status === 'forbidden'): ?>
    <div class="admin-alert">امکان تغییر به این نقش وجود ندارد.</div>
<?php endif; ?>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>نقش‌ها و دسترسی‌های من</h2>
            <p class="admin-muted">مشاهده نقش‌های معتبر حساب شما و انتخاب نقش فعال نشست جاری.</p>
        </div>
    </div>
    <div class="admin-mini-grid">
        <article class="admin-card"><span>نقش فعال</span><strong><?= admin_h($active['role_title'] ?? '—') ?></strong></article>
        <article class="admin-card"><span>کد نقش</span><strong dir="ltr"><?= admin_h($active['role_code'] ?? '—') ?></strong></article>
        <article class="admin-card"><span>وضعیت</span><strong>فعال</strong></article>
    </div>
</section>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>نقش‌های قابل انتخاب</h2>
            <p class="admin-muted">فقط نقش‌های معتبر متعلق به حساب شما نمایش داده می‌شود.</p>
        </div>
    </div>
    <div class="admin-table-wrap admin-profile-access-table-wrap">
        <table class="admin-table admin-profile-access-table">
            <thead>
                <tr>
                    <th>عنوان نقش</th>
                    <th>کد نقش</th>
                    <th>اولویت</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $assignment): ?>
                    <?php $isActive = (int) ($active['id'] ?? 0) === (int) $assignment['id']; ?>
                    <tr>
                        <td><?= admin_h($assignment['role_title'] ?? '') ?></td>
                        <td dir="ltr"><?= admin_h($assignment['role_code'] ?? '') ?></td>
                        <td><?= admin_h(\App\Support\AdminFormat::digits($assignment['priority'] ?? '')) ?></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="admin-pill">فعال</span>
                            <?php else: ?>
                                <span class="admin-status-badge admin-status-badge--active">قابل انتخاب</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="admin-muted">نقش فعال</span>
                            <?php else: ?>
                                <form method="post" action="/admin/profile/access" class="admin-inline-form">
                                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                                    <input type="hidden" name="role_assignment_id" value="<?= (int) $assignment['id'] ?>">
                                    <button type="submit">انتخاب نقش</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-users-cards admin-profile-access-cards" aria-label="نقش‌های قابل انتخاب">
        <?php foreach ($assignments as $assignment): ?>
            <?php $isActive = (int) ($active['id'] ?? 0) === (int) $assignment['id']; ?>
            <article class="admin-user-card">
                <header>
                    <span class="admin-user-card__icon"><?= \App\Support\AdminIcon::html('user-shield') ?></span>
                    <div>
                        <strong><?= admin_h($assignment['role_title'] ?? '') ?></strong>
                        <small dir="ltr"><?= admin_h($assignment['role_code'] ?? '') ?></small>
                    </div>
                    <?php if ($isActive): ?>
                        <span class="admin-pill">فعال</span>
                    <?php endif; ?>
                </header>
                <dl>
                    <div>
                        <dt><?= \App\Support\AdminIcon::html('sliders') ?> اولویت</dt>
                        <dd><?= admin_h(\App\Support\AdminFormat::digits($assignment['priority'] ?? '')) ?></dd>
                    </div>
                    <div>
                        <dt><?= \App\Support\AdminIcon::html('status') ?> وضعیت</dt>
                        <dd><?= $isActive ? 'نقش فعال نشست' : 'قابل انتخاب' ?></dd>
                    </div>
                </dl>
                <?php if (!$isActive): ?>
                    <form method="post" action="/admin/profile/access" class="admin-inline-form admin-profile-access-card__form">
                        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                        <input type="hidden" name="role_assignment_id" value="<?= (int) $assignment['id'] ?>">
                        <button type="submit">انتخاب نقش</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
