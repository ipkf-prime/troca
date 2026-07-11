<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$status = $status ?? '';

ob_start();
?>
<?php if ($status === 'switched'): ?>
    <div class="admin-notice">نقش فعال تغییر کرد.</div>
<?php elseif ($status === 'forbidden'): ?>
    <div class="admin-alert">امکان تغییر به این نقش وجود ندارد.</div>
<?php endif; ?>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>انتخاب نقش فعال</h2>
            <p class="admin-muted">پنل بر اساس نقش فعال شما رفتار می‌کند. برای دسترسی مدیریتی، نقش مناسب را انتخاب کنید.</p>
        </div>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>نقش</th>
                    <th>کد</th>
                    <th>محدوده</th>
                    <th>اولویت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($context['assignments'] as $assignment): ?>
                    <?php $isActive = (int) ($context['active_assignment']['id'] ?? 0) === (int) $assignment['id']; ?>
                    <tr>
                        <td><?= admin_h($assignment['role_title'] ?? '') ?></td>
                        <td><?= admin_h($assignment['role_code'] ?? '') ?></td>
                        <td><?= admin_h($assignment['scope_type'] ?? 'global') ?></td>
                        <td><?= admin_h($assignment['priority'] ?? '') ?></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="admin-pill">فعال</span>
                            <?php else: ?>
                                <form method="post" action="/admin/access" class="admin-inline-form">
                                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                                    <input type="hidden" name="role_assignment_id" value="<?= (int) $assignment['id'] ?>">
                                    <button type="submit">فعال‌سازی</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
