<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$fontLabels = [
    'vazirmatn' => 'Vazirmatn',
    'tahoma' => 'Tahoma',
    'segoe_ui' => 'Segoe UI',
    'system_ui' => 'System UI',
];
$fontSizeOptions = ['13px', '14px', '15px', '16px', '1rem'];
$lineHeightOptions = ['1.5', '1.6', '1.7', '1.8'];
$radiusOptions = ['8px', '12px', '16px', '18px', '20px', '24px'];
$status = $status ?? '';
$errors = $errors ?? [];

ob_start();
?>
<?php require __DIR__ . '/partials/account-nav.php'; ?>

<?php if ($status === 'saved'): ?>
    <div class="admin-notice">تنظیمات نمایشی شما ذخیره شد.</div>
<?php elseif ($status === 'reset'): ?>
    <div class="admin-notice">تنظیمات نمایشی شما بازنشانی شد و از پوسته سامانه استفاده می‌شود.</div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="admin-alert">تنظیمات نمایشی معتبر نیست. مقدارهای انتخابی را بررسی کنید.</div>
<?php endif; ?>

<form method="post" action="/admin/my-theme" class="admin-theme-form">
    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">

    <section class="admin-section">
        <div class="admin-section__header">
            <div>
                <h2>پوسته‌های اصلی</h2>
                <p class="admin-muted">یکی از پوسته‌های اصلی سامانه را برای نمایش حساب خود انتخاب کنید. این انتخاب فقط برای شما ذخیره می‌شود.</p>
            </div>
            <span class="admin-pill">پوسته فعال من: <?= admin_h($theme['preset_title'] ?? '-') ?></span>
        </div>
        <div class="admin-theme-presets">
            <?php foreach ($presets as $key => $preset): ?>
                <?php $tokens = $preset['tokens']; ?>
                <label class="admin-preset-card <?= $theme['active_preset'] === $key ? 'is-active' : '' ?>">
                    <input type="radio" name="active_preset" value="<?= admin_h($key) ?>" <?= $theme['active_preset'] === $key ? 'checked' : '' ?>>
                    <span class="admin-preset-card__visual" style="background: <?= admin_h($tokens['bg']) ?>;">
                        <i style="background: linear-gradient(160deg, <?= admin_h($tokens['sidebar_bg']) ?>, <?= admin_h($tokens['sidebar_bg_2']) ?>);"></i>
                        <b style="background: <?= admin_h($tokens['sidebar_active_bg']) ?>;"></b>
                        <em style="background: <?= admin_h($tokens['surface']) ?>;"></em>
                    </span>
                    <strong><?= admin_h($preset['title']) ?></strong>
                    <span class="admin-preset-card__selected">انتخاب شده</span>
                    <small><?= admin_h($preset['description']) ?></small>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <div>
                <h2>تنظیمات خوانایی من</h2>
                <p class="admin-muted">این گزینه‌ها فقط ظاهر حساب شما را تغییر می‌دهند و نام برند، لوگو، آواتار پیش‌فرض یا فوتر سامانه را تغییر نمی‌دهند.</p>
            </div>
        </div>
        <div class="admin-form-grid">
            <label>
                <span>فونت پنل</span>
                <select name="token_font_family">
                    <?php foreach (($fontOptions ?? []) as $key => $fontValue): ?>
                        <option value="<?= admin_h($fontValue) ?>" <?= $theme['tokens']['font_family'] === $fontValue ? 'selected' : '' ?>><?= admin_h($fontLabels[$key] ?? $key) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>اندازه پایه فونت</span>
                <select name="token_font_size_base">
                    <?php foreach ($fontSizeOptions as $size): ?>
                        <option value="<?= admin_h($size) ?>" <?= $theme['tokens']['font_size_base'] === $size ? 'selected' : '' ?>><?= admin_h($size) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>فاصله خطوط</span>
                <select name="token_line_height_base">
                    <?php foreach ($lineHeightOptions as $lineHeight): ?>
                        <option value="<?= admin_h($lineHeight) ?>" <?= $theme['tokens']['line_height_base'] === $lineHeight ? 'selected' : '' ?>><?= admin_h($lineHeight) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>گردی گوشه‌ها</span>
                <select name="token_radius">
                    <?php foreach ($radiusOptions as $radius): ?>
                        <option value="<?= admin_h($radius) ?>" <?= $theme['tokens']['radius'] === $radius ? 'selected' : '' ?>><?= admin_h($radius) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>

    <section class="admin-section">
        <h2>پوسته فعال من</h2>
        <div class="admin-empty-state">
            <?= $theme['has_personal_override'] ? 'شما تنظیمات نمایشی شخصی فعال دارید.' : 'فعلا تنظیمات شخصی ذخیره نشده است و پوسته سامانه برای شما استفاده می‌شود.' ?>
        </div>
    </section>

    <div class="admin-form-actions">
        <button type="submit">ذخیره تنظیمات من</button>
    </div>
</form>

<form method="post" action="/admin/theme/reset" class="admin-reset-form">
    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
    <input type="hidden" name="scope" value="user">
    <button type="submit" class="admin-button admin-button--soft">بازنشانی تنظیمات من</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
