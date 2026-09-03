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
$status = $status ?? '';
$errors = $errors ?? [];

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
    <div class="admin-notice">پوسته پیش‌فرض سامانه با موفقیت ذخیره شد.</div>
<?php elseif ($status === 'reset'): ?>
    <div class="admin-notice">پوسته سامانه به تنظیمات پیش‌فرض بازنشانی شد.</div>
<?php elseif ($status === 'forbidden'): ?>
    <div class="admin-alert">برای بازنشانی پوسته سامانه دسترسی کافی ندارید.</div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="admin-alert">بخشی از تنظیمات معتبر نیست یا دسترسی کافی ندارید. مقادیر رنگ، فونت، لوگو و آواتار را بررسی کنید.</div>
<?php endif; ?>

<?php if (!$canManageTheme): ?>
    <div class="admin-alert">این بخش تنظیمات عمومی ظاهر پنل برای سامانه است و فقط توسط مدیر سامانه قابل تغییر است.</div>
<?php endif; ?>

<form method="post" action="/admin/theme" class="admin-theme-form">
    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">

    <nav class="admin-tabs" data-admin-tabs role="tablist" aria-label="بخش‌های پوسته">
        <button class="admin-tab is-active" type="button" data-admin-tab="presets" role="tab">پوسته‌ها</button>
        <button class="admin-tab" type="button" data-admin-tab="branding" role="tab">آواتار پیش‌فرض</button>
        <button class="admin-tab" type="button" data-admin-tab="advanced" role="tab">پیشرفته</button>
    </nav>

    <section class="admin-section admin-tab-panel is-active" data-admin-tab-panel="presets">
        <div class="admin-section__header">
            <div>
                <h2>پوسته پیش‌فرض سامانه</h2>
                <p class="admin-muted">تنظیمات عمومی ظاهر پنل برای سامانه. این بخش فقط توسط مدیر سامانه قابل تغییر است.</p>
                <p class="admin-muted">در نسخه ۰.۴.۴ انتخاب پوسته فقط از میان پوسته‌های آماده انجام می‌شود.</p>
            </div>
        </div>
        <div class="admin-theme-presets">
            <?php foreach ($presets as $key => $preset): ?>
                <?php $tokens = $preset['tokens']; ?>
                <label class="admin-preset-card theme-preset-card <?= $theme['active_preset'] === $key ? 'is-active' : '' ?>">
                    <input type="radio" name="active_preset" value="<?= admin_h($key) ?>" <?= $theme['active_preset'] === $key ? 'checked' : '' ?> <?= $canManageTheme ? '' : 'disabled' ?>>
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

    <section
        class="admin-section admin-tab-panel"
        data-admin-tab-panel="branding"
        hidden
    >
        <div class="admin-section__header">
            <div>
                <h2>
                    آواتار پیش‌فرض کاربران
                </h2>

                <p class="admin-muted">
                    نام سامانه، عنوان، لوگو و فوتر
                    از بخش «مدیریت صفحات» مدیریت
                    می‌شوند.
                </p>
            </div>
        </div>

        <div class="admin-form-grid">
            <label>
                <span>
                    آواتار پیش‌فرض کاربران
                </span>

                <select
                    name="default_avatar_url"
                    <?= $canManageTheme
                        ? ''
                        : 'disabled' ?>
                >
                    <?php foreach (
                        $avatarOptions
                        as $label => $path
                    ): ?>
                        <option
                            value="<?= admin_h($path) ?>"
                            <?= $theme[
                                'default_avatar_url'
                            ] === $path
                                ? 'selected'
                                : '' ?>
                        >
                            <?= admin_h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="admin-branding-preview">
            <img
                class="admin-avatar"
                src="<?= admin_h(
                    $theme['default_avatar_url']
                ) ?>"
                alt=""
            >

            <div>
                <strong>
                    آواتار پیش‌فرض
                </strong>

                <span class="admin-muted">
                    تصویر شخصی هر کاربر از پروفایل
                    خودش قابل تغییر است.
                </span>
            </div>
        </div>
    </section>

    <?php if (false): ?>
    <section class="admin-section admin-tab-panel" data-admin-tab-panel="header" hidden>
        <div class="admin-section__header"><div><h2>هدر</h2><p class="admin-muted">نمایش کاربر، نقش فعال و رنگ هدر پنل.</p></div></div>
        <div class="admin-form-grid">
            <label><span>رنگ هدر</span><input type="color" name="token_header_bg" value="<?= admin_h($theme['tokens']['header_bg']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label class="admin-check-field"><input type="checkbox" name="show_user_name" value="1" <?= $theme['show_user_name'] ? 'checked' : '' ?> <?= $canManageTheme ? '' : 'disabled' ?>><span>نمایش نام کاربر</span></label>
            <label class="admin-check-field"><input type="checkbox" name="show_active_role" value="1" <?= $theme['show_active_role'] ? 'checked' : '' ?> <?= $canManageTheme ? '' : 'disabled' ?>><span>نمایش نقش فعال</span></label>
        </div>
    </section>

    <section class="admin-section admin-tab-panel" data-admin-tab-panel="sidebar" hidden>
        <div class="admin-section__header"><div><h2>سایدبار</h2><p class="admin-muted">رنگ منوی اصلی، آیتم فعال و حالت‌های نمایشی سایدبار.</p></div></div>
        <div class="admin-form-grid">
            <label><span>پس‌زمینه سایدبار</span><input type="color" name="token_sidebar_bg" value="<?= admin_h($theme['tokens']['sidebar_bg']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label><span>رنگ دوم سایدبار</span><input type="color" name="token_sidebar_bg_2" value="<?= admin_h($theme['tokens']['sidebar_bg_2']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
            <label><span>آیتم فعال</span><input type="color" name="token_sidebar_active_bg" value="<?= admin_h($theme['tokens']['sidebar_active_bg']) ?>" <?= $canManageTheme ? '' : 'disabled' ?>></label>
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
                        <option value="<?= admin_h($radius) ?>" <?= $theme['tokens']['radius'] === $radius ? 'selected' : '' ?>><?= admin_h(\App\Support\AdminFormat::digits($radius)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>
    <?php endif; ?>



    <?php if (false): ?>
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
                        <option value="<?= admin_h($size) ?>" <?= $theme['tokens']['font_size_base'] === $size ? 'selected' : '' ?>><?= admin_h(\App\Support\AdminFormat::digits($size)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>فاصله خطوط</span>
                <select name="token_line_height_base" <?= $canManageTheme ? '' : 'disabled' ?>>
                    <?php foreach ($lineHeightOptions as $lineHeight): ?>
                        <option value="<?= admin_h($lineHeight) ?>" <?= $theme['tokens']['line_height_base'] === $lineHeight ? 'selected' : '' ?>><?= admin_h(\App\Support\AdminFormat::digits($lineHeight)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>
    <?php endif; ?>

    <section class="admin-section admin-tab-panel" data-admin-tab-panel="advanced" hidden>
        <div class="admin-section__header">
            <div>
                <h2>پیشرفته</h2>
                <p class="admin-muted">ویرایش پیشرفته پوسته در نسخه بعدی فعال می‌شود.</p>
            </div>
        </div>
    </section>

    <?php if ($canManageTheme): ?>
        <div class="admin-form-actions">
            <button type="submit">ذخیره پوسته سامانه</button>
        </div>
    <?php endif; ?>
</form>

<?php if ($canManageTheme): ?>
    <form method="post" action="/admin/theme/reset" class="admin-reset-form">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
        <input type="hidden" name="scope" value="system">
        <button type="submit" class="admin-button admin-button--soft">بازنشانی پوسته سامانه</button>
    </form>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
