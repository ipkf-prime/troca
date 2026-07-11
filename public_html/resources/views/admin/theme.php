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
$logoOptions = $logoOptions ?? [];
$avatarOptions = $avatarOptions ?? [];

$colorLabels = [
    'primary' => 'رنگ اصلی',
    'primary_hover' => 'رنگ اصلی در حالت hover',
    'primary_dark' => 'سبز تیره',
    'primary_soft' => 'سبز ملایم',
    'accent' => 'رنگ تاکید',
    'accent_hover' => 'تاکید در حالت hover',
    'surface_muted' => 'سطح ملایم',
    'text' => 'متن اصلی',
    'text_muted' => 'متن کم‌رنگ',
    'border' => 'خط جداکننده',
    'sidebar_text' => 'متن سایدبار',
    'sidebar_text_muted' => 'متن کم‌رنگ سایدبار',
    'sidebar_active_text' => 'متن آیتم فعال',
    'footer_text' => 'متن فوتر',
];

$advancedTextTokens = [
    'shadow' => 'سایه کارت‌ها',
    'sidebar_width' => 'عرض سایدبار',
    'topbar_height' => 'ارتفاع هدر',
    'font_size_sm' => 'اندازه متن کوچک',
    'font_size_lg' => 'اندازه تیترها',
    'font_weight_normal' => 'وزن معمولی',
    'font_weight_medium' => 'وزن متوسط',
    'font_weight_bold' => 'وزن ضخیم',
];

ob_start();
?>
<?php if ($status === 'saved'): ?>
    <div class="admin-notice">پوسته پنل با موفقیت ذخیره شد.</div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="admin-alert">بخشی از تنظیمات معتبر نیست یا دسترسی کافی ندارید. مقادیر رنگ، فونت، لوگو و آواتار را بررسی کنید.</div>
<?php endif; ?>

<?php if (!$canManageTheme): ?>
    <div class="admin-alert">برای ذخیره تغییرات باید نقش فعال شما دارای دسترسی مدیریت پوسته باشد.</div>
<?php endif; ?>

<form method="post" action="/admin/theme" class="admin-theme-form">
    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">

    <nav class="admin-tabs" data-admin-tabs role="tablist" aria-label="بخش‌های پوسته">
        <button class="admin-tab is-active" type="button" data-admin-tab="presets" role="tab">پوسته‌ها</button>
        <button class="admin-tab" type="button" data-admin-tab="branding" role="tab">برندینگ</button>
        <button class="admin-tab" type="button" data-admin-tab="header" role="tab">هدر</button>
        <button class="admin-tab" type="button" data-admin-tab="sidebar" role="tab">سایدبار</button>
        <button class="admin-tab" type="button" data-admin-tab="dashboard" role="tab">داشبورد</button>
        <button class="admin-tab" type="button" data-admin-tab="footer" role="tab">فوتر</button>
        <button class="admin-tab" type="button" data-admin-tab="font" role="tab">فونت</button>
        <button class="admin-tab" type="button" data-admin-tab="advanced" role="tab">پیشرفته</button>
    </nav>

    <section class="admin-section admin-tab-panel is-active" data-admin-tab-panel="presets">
        <div class="admin-section__header">
            <div>
                <h2>انتخاب پوسته</h2>
                <p class="admin-muted">یک ظاهر پایه انتخاب کنید. هر پوسته رنگ، سایدبار و حس بصری متفاوتی دارد.</p>
            </div>
        </div>
        <div class="admin-theme-presets">
            <?php foreach ($presets as $key => $preset): ?>
                <?php $tokens = $preset['tokens']; ?>
                <label class="admin-preset-card <?= $theme['active_preset'] === $key ? 'is-active' : '' ?>">
                    <input type="radio" name="active_preset" value="<?= admin_h($key) ?>" <?= $theme['active_preset'] === $key ? 'checked' : '' ?> <?= $canManageTheme ? '' : 'disabled' ?>>
                    <span class="admin-preset-card__visual" style="background: <?= admin_h($tokens['bg']) ?>;">
                        <i style="background: linear-gradient(160deg, <?= admin_h($tokens['sidebar_bg']) ?>, <?= admin_h($tokens['sidebar_bg_2']) ?>);"></i>
                        <b style="background: <?= admin_h($tokens['sidebar_active_bg']) ?>;"></b>
                        <em style="background: <?= admin_h($tokens['surface']) ?>;"></em>
                    </span>
                    <strong><?= admin_h($preset['title']) ?></strong>
                    <small><?= admin_h($preset['description']) ?></small>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-section admin-tab-panel" data-admin-tab-panel="branding" hidden>
        <div class="admin-section__header">
            <div>
                <h2>برندینگ</h2>
                <p class="admin-muted">نام سامانه، لوگو و آواتار پیش‌فرض کاربران را تنظیم کنید.</p>
            </div>
        </div>
        <div class="admin-form-grid">
            <label><span>نام سامانه</span><input name="brand_name" value="<?= admin_h($theme['brand_name']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label>
                <span>لوگوی پنل</span>
                <select name="logo_url" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php foreach ($logoOptions as $label => $path): ?>
                        <option value="<?= admin_h($path) ?>" <?= $theme['logo_url'] === $path ? 'selected' : '' ?>><?= admin_h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>آواتار پیش‌فرض کاربران</span>
                <select name="default_avatar_url" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php foreach ($avatarOptions as $label => $path): ?>
                        <option value="<?= admin_h($path) ?>" <?= $theme['default_avatar_url'] === $path ? 'selected' : '' ?>><?= admin_h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="admin-branding-preview">
            <img src="<?= admin_h($theme['logo_url']) ?>" alt="">
            <div>
                <strong><?= admin_h($theme['brand_name']) ?></strong>
                <span class="admin-muted">پیش‌نمایش برند در هدر و سایدبار</span>
            </div>
            <img class="admin-avatar" src="<?= admin_h($theme['default_avatar_url']) ?>" alt="">
        </div>
    </section>

    <section class="admin-section admin-tab-panel" data-admin-tab-panel="header" hidden>
        <div class="admin-section__header"><div><h2>هدر</h2><p class="admin-muted">نمایش کاربر، نقش فعال و رنگ هدر پنل.</p></div></div>
        <div class="admin-form-grid">
            <label><span>رنگ هدر</span><input type="color" name="token_header_bg" value="<?= admin_h($theme['tokens']['header_bg']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label class="admin-check-field"><input type="checkbox" name="show_user_name" value="1" <?= $theme['show_user_name'] ? 'checked' : '' ?> <?= $canManageTheme ? '' : 'disabled' ?>><span>نمایش نام کاربر</span></label>
            <label class="admin-check-field"><input type="checkbox" name="show_active_role" value="1" <?= $theme['show_active_role'] ? 'checked' : '' ?> <?= $canManageTheme ? '' : 'disabled' ?>><span>نمایش نقش فعال</span></label>
        </div>
        <div class="admin-header-preview" style="background: <?= admin_h($theme['tokens']['header_bg']) ?>;"><span><?= admin_h($theme['brand_name']) ?></span><b>مدیر کل سامانه</b></div>
    </section>

    <section class="admin-section admin-tab-panel" data-admin-tab-panel="sidebar" hidden>
        <div class="admin-section__header"><div><h2>سایدبار</h2><p class="admin-muted">رنگ منوی اصلی، آیتم فعال و حالت hover را تنظیم کنید.</p></div></div>
        <div class="admin-form-grid">
            <label><span>پس‌زمینه سایدبار</span><input type="color" name="token_sidebar_bg" value="<?= admin_h($theme['tokens']['sidebar_bg']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label><span>رنگ دوم سایدبار</span><input type="color" name="token_sidebar_bg_2" value="<?= admin_h($theme['tokens']['sidebar_bg_2']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label><span>آیتم فعال</span><input type="color" name="token_sidebar_active_bg" value="<?= admin_h($theme['tokens']['sidebar_active_bg']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
        </div>
        <div class="admin-sidebar-preview" style="background: linear-gradient(180deg, <?= admin_h($theme['tokens']['sidebar_bg']) ?>, <?= admin_h($theme['tokens']['sidebar_bg_2']) ?>); color: <?= admin_h($theme['tokens']['sidebar_text']) ?>;">
            <span>داشبورد</span>
            <strong style="background: <?= admin_h($theme['tokens']['sidebar_active_bg']) ?>; color: <?= admin_h($theme['tokens']['sidebar_active_text']) ?>;">پوسته پنل</strong>
            <span>گزارش‌ها</span>
        </div>
    </section>

    <section class="admin-section admin-tab-panel" data-admin-tab-panel="dashboard" hidden>
        <div class="admin-section__header"><div><h2>داشبورد</h2><p class="admin-muted">پس‌زمینه، کارت‌ها، گردی گوشه‌ها و سایه‌ها.</p></div></div>
        <div class="admin-form-grid">
            <label><span>پس‌زمینه</span><input type="color" name="token_bg" value="<?= admin_h($theme['tokens']['bg']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label><span>رنگ کارت‌ها</span><input type="color" name="token_surface" value="<?= admin_h($theme['tokens']['surface']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label>
                <span>گردی گوشه‌ها</span>
                <select name="token_radius" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php foreach ($radiusOptions as $radius): ?>
                        <option value="<?= admin_h($radius) ?>" <?= $theme['tokens']['radius'] === $radius ? 'selected' : '' ?>><?= admin_h($radius) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>

    <section class="admin-section admin-tab-panel" data-admin-tab-panel="footer" hidden>
        <div class="admin-section__header"><div><h2>فوتر</h2><p class="admin-muted">متن و رنگ فوتر پنل مدیریت.</p></div></div>
        <div class="admin-form-grid">
            <label><span>متن فوتر</span><input name="footer_text" value="<?= admin_h($theme['footer_text']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label><span>رنگ فوتر</span><input type="color" name="token_footer_bg" value="<?= admin_h($theme['tokens']['footer_bg']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label class="admin-check-field"><input type="checkbox" name="footer_enabled" value="1" <?= $theme['footer_enabled'] ? 'checked' : '' ?> <?= $canManageTheme ? '' : 'disabled' ?>><span>نمایش فوتر</span></label>
        </div>
    </section>

    <section class="admin-section admin-tab-panel" data-admin-tab-panel="font" hidden>
        <div class="admin-section__header"><div><h2>فونت و خوانایی</h2><p class="admin-muted">فونت، اندازه متن و فاصله خطوط برای خوانایی فارسی.</p></div></div>
        <div class="admin-form-grid">
            <label>
                <span>فونت پنل</span>
                <select name="token_font_family" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php foreach (($fontOptions ?? []) as $key => $fontValue): ?>
                        <option value="<?= admin_h($fontValue) ?>" <?= $theme['tokens']['font_family'] === $fontValue ? 'selected' : '' ?>><?= admin_h($fontLabels[$key] ?? $key) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>اندازه پایه فونت</span>
                <select name="token_font_size_base" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php foreach ($fontSizeOptions as $size): ?>
                        <option value="<?= admin_h($size) ?>" <?= $theme['tokens']['font_size_base'] === $size ? 'selected' : '' ?>><?= admin_h($size) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>فاصله خطوط</span>
                <select name="token_line_height_base" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php foreach ($lineHeightOptions as $lineHeight): ?>
                        <option value="<?= admin_h($lineHeight) ?>" <?= $theme['tokens']['line_height_base'] === $lineHeight ? 'selected' : '' ?>><?= admin_h($lineHeight) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="admin-typography-preview">«نمونه متن فارسی پنل مدیریت تروکا»</div>
    </section>

    <section class="admin-section admin-tab-panel" data-admin-tab-panel="advanced" hidden>
        <div class="admin-section__header"><div><h2>پیشرفته</h2><p class="admin-muted">تنظیم دقیق رنگ‌ها، مسیرهای داخلی و توکن‌های نمایشی. مقدار خارجی یا ناامن ذخیره نمی‌شود.</p></div></div>
        <div class="admin-form-grid">
            <label><span>مسیر دستی لوگو</span><input name="logo_url_manual" placeholder="/assets/admin/images/logos/default-logo.svg" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label><span>مسیر دستی آواتار</span><input name="default_avatar_url_manual" placeholder="/assets/admin/images/avatars/default-avatar.svg" <?= $canManageTheme ? '' : 'disabled' ?>></label>
        </div>
        <div class="admin-token-grid">
            <?php foreach ($colorLabels as $key => $label): ?>
                <?php if (!isset($theme['tokens'][$key])): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <label class="admin-token-field">
                    <span><?= admin_h($label) ?></span>
                    <input type="color" name="token_<?= admin_h($key) ?>" value="<?= admin_h($theme['tokens'][$key]) ?>" <?= $canManageTheme ? '' : 'disabled' ?>>
                </label>
            <?php endforeach; ?>
            <?php foreach ($advancedTextTokens as $key => $label): ?>
                <?php if (!isset($theme['tokens'][$key])): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <label class="admin-token-field">
                    <span><?= admin_h($label) ?></span>
                    <input name="token_<?= admin_h($key) ?>" value="<?= admin_h($theme['tokens'][$key]) ?>" <?= $canManageTheme ? '' : 'disabled' ?>>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($canManageTheme): ?>
        <div class="admin-form-actions">
            <button type="submit">ذخیره پوسته</button>
        </div>
    <?php endif; ?>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
