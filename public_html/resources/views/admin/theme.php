<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$tokenLabels = [
    'font_family' => 'خانواده فونت',
    'font_size_base' => 'اندازه پایه فونت',
    'line_height_base' => 'فاصله خطوط',
    'font_weight_normal' => 'وزن معمولی',
    'font_weight_medium' => 'وزن متوسط',
    'font_weight_bold' => 'وزن ضخیم',
    'primary' => 'رنگ اصلی',
    'primary_hover' => 'رنگ اصلی در حالت فعال',
    'primary_soft' => 'رنگ زمینه ملایم',
    'accent' => 'رنگ تاکید',
    'bg' => 'پس زمینه',
    'bg_gradient_start' => 'شروع گرادیان',
    'bg_gradient_end' => 'پایان گرادیان',
    'surface' => 'سطح کارت',
    'surface_muted' => 'سطح ملایم',
    'text' => 'متن اصلی',
    'text_muted' => 'متن کم رنگ',
    'border' => 'خط جداکننده',
    'danger' => 'خطا',
    'warning' => 'هشدار',
    'success' => 'موفقیت',
    'radius' => 'گردی گوشه',
    'shadow' => 'سایه',
    'sidebar_width' => 'عرض منو',
    'topbar_height' => 'ارتفاع سربرگ',
];

$fontLabels = [
    'vazirmatn' => 'Vazirmatn',
    'tahoma' => 'Tahoma',
    'segoe_ui' => 'Segoe UI',
    'system_ui' => 'System UI',
];

$featuredTokens = ['font_family', 'font_size_base', 'line_height_base', 'radius'];

$colorTokens = [
    'primary', 'primary_hover', 'primary_soft', 'accent', 'bg',
    'bg_gradient_start', 'bg_gradient_end', 'surface', 'surface_muted',
    'text', 'text_muted', 'border', 'danger', 'warning', 'success',
];

ob_start();
?>
<?php if ($status === 'saved'): ?>
    <div class="admin-notice">پوسته پنل به روز شد.</div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="admin-alert">تنظیمات پوسته معتبر نیست یا دسترسی کافی ندارید.</div>
<?php endif; ?>

<section class="admin-section">
    <h2>پیش نمایش پوسته</h2>
    <div class="admin-theme-preview">
        <?php foreach ($presets as $key => $preset): ?>
            <article class="admin-theme-card <?= $theme['active_preset'] === $key ? 'is-active' : '' ?>">
                <span><?= admin_h($preset['title']) ?></span>
                <strong><?= admin_h($key) ?></strong>
                <div class="admin-color-row">
                    <i style="background: <?= admin_h($preset['tokens']['primary']) ?>"></i>
                    <i style="background: <?= admin_h($preset['tokens']['accent']) ?>"></i>
                    <i style="background: <?= admin_h($preset['tokens']['surface_muted']) ?>"></i>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-section">
    <h2>تنظیمات پوسته</h2>
    <?php if (!$canManageTheme): ?>
        <p class="admin-muted">برای تغییر پوسته باید نقش فعال دارای دسترسی مدیریت پوسته باشد.</p>
    <?php endif; ?>

    <form method="post" action="/admin/theme" class="admin-theme-form">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">

        <div class="admin-form-grid">
            <label>
                <span>پوسته فعال</span>
                <select name="active_preset" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php foreach ($presets as $key => $preset): ?>
                        <option value="<?= admin_h($key) ?>" <?= $theme['active_preset'] === $key ? 'selected' : '' ?>>
                            <?= admin_h($preset['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>نام برند</span>
                <input name="brand_name" value="<?= admin_h($theme['brand_name']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>>
            </label>
            <label>
                <span>آدرس لوگو</span>
                <input name="logo_url" value="<?= admin_h($theme['logo_url']) ?>" placeholder="/assets/admin/images/logos/default-logo.svg" <?= $canManageTheme ? '' : 'disabled' ?>>
            </label>
            <label>
                <span>آواتار پیش فرض</span>
                <input name="default_avatar_url" value="<?= admin_h($theme['default_avatar_url']) ?>" placeholder="/assets/admin/images/avatars/default-avatar.svg" <?= $canManageTheme ? '' : 'disabled' ?>>
            </label>
            <label>
                <span>فونت پنل</span>
                <select name="token_font_family" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php foreach (($fontOptions ?? []) as $key => $fontValue): ?>
                        <option value="<?= admin_h($fontValue) ?>" <?= $theme['tokens']['font_family'] === $fontValue ? 'selected' : '' ?>>
                            <?= admin_h($fontLabels[$key] ?? $key) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>اندازه پایه فونت</span>
                <input name="token_font_size_base" value="<?= admin_h($theme['tokens']['font_size_base']) ?>" placeholder="15px" <?= $canManageTheme ? '' : 'disabled' ?>>
            </label>
            <label>
                <span>فاصله خطوط</span>
                <input name="token_line_height_base" value="<?= admin_h($theme['tokens']['line_height_base']) ?>" placeholder="1.8" <?= $canManageTheme ? '' : 'disabled' ?>>
            </label>
            <label>
                <span>گردی گوشه</span>
                <input name="token_radius" value="<?= admin_h($theme['tokens']['radius']) ?>" placeholder="16px" <?= $canManageTheme ? '' : 'disabled' ?>>
            </label>
        </div>

        <div class="admin-typography-preview">
            نمونه متن فارسی پنل مدیریت تروکا
        </div>

        <div class="admin-token-grid">
            <?php foreach ($theme['tokens'] as $key => $value): ?>
                <?php if (in_array($key, $featuredTokens, true)): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <label class="admin-token-field">
                    <span><?= admin_h($tokenLabels[$key] ?? $key) ?></span>
                    <?php if (in_array($key, $colorTokens, true)): ?>
                        <input type="color" name="token_<?= admin_h($key) ?>" value="<?= admin_h($value) ?>" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php else: ?>
                        <input name="token_<?= admin_h($key) ?>" value="<?= admin_h($value) ?>" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>

        <?php if ($canManageTheme): ?>
            <button type="submit">ذخیره پوسته</button>
        <?php endif; ?>
    </form>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
