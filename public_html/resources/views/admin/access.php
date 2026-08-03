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
<?php elseif ($status === 'permissions_saved'): ?>
    <div class="admin-notice">دسترسی فرم‌ها و عملیات مرکز ارتباطات ذخیره شد.</div>
<?php elseif ($status === 'protected_role'): ?>
    <div class="admin-alert">نقش مدیر کل محافظت شده و دسترسی کامل آن قابل کاهش نیست.</div>
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
                        <td><?= admin_h(\App\Support\AdminFormat::digits($assignment['priority'] ?? '')) ?></td>
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

<?php if (($canManageCommunicationAccess ?? false) === true): ?>
    <?php
    $matrix = $communicationMatrix ?? [];
    $roles = $matrix['roles'] ?? [];
    $permissions = $matrix['permissions'] ?? [];
    $assigned = $matrix['assigned'] ?? [];
    ?>
    <section class="admin-section" style="margin-top:1rem">
        <div class="admin-section__header">
            <div>
                <h2>دسترسی فرم‌های پیام و اعلان</h2>
                <p class="admin-muted">برای هر نقش، مشاهده فرم‌ها و عملیات مجاز را تعیین کنید. نقش «مدیر کل» همیشه دسترسی کامل دارد.</p>
            </div>
        </div>
        <div class="admin-grid">
            <?php foreach ($roles as $role): ?>
                <?php $protected = ($role['code'] ?? '') === 'super_admin'; ?>
                <form method="post" action="/admin/access/communications" class="admin-card">
                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                    <input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">
                    <h3><?= admin_h($role['title']) ?></h3>
                    <p class="admin-muted"><code><?= admin_h($role['code']) ?></code></p>
                    <div style="display:grid;gap:.45rem;margin:.8rem 0">
                        <?php foreach ($permissions as $permission): ?>
                            <label style="display:flex;align-items:flex-start;gap:.5rem">
                                <input type="checkbox" name="permissions[]" value="<?= admin_h($permission['code']) ?>"
                                    <?= isset($assigned[(int) $role['id']][(string) $permission['code']]) ? 'checked' : '' ?>
                                    <?= $protected ? 'disabled' : '' ?>>
                                <span><?= admin_h($permission['title']) ?><small class="admin-muted" style="display:block"><?= admin_h($permission['code']) ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button class="admin-button" type="submit" <?= $protected ? 'disabled' : '' ?>>
                        <?= $protected ? 'دسترسی کامل ثابت' : 'ذخیره دسترسی این نقش' ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
