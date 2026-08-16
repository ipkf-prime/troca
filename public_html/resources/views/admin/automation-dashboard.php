<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}
$dashboard = $dashboard ?? ['ok' => false, 'counts' => []];
$counts = $dashboard['counts'] ?? [];

$canManageExternalDirectory =
    (new \App\Services\AuthorizationService())
        ->hasPermission(
            (int) (
                $context['user_id']
                ?? 0
            ),
            'automation.external_directory.manage'
        );
ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span aria-hidden="true">/</span><span>اتوماسیون اداری</span>
</nav>
<section class="admin-module-hub admin-module-hub--teal admin-users-heading">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></div>
    <div><h2>اتوماسیون اداری</h2><p>برش نمایشی عملیات مکاتبات؛ ایجاد پیش نویس، نسخه‌ها، طرف‌ها و تاریخچه</p></div>
    <a class="admin-module-hub__back" href="/admin/automation/correspondences/create?direction=outgoing">ایجاد پیش نویس</a>
</section>
<?php if (($dashboard['ok'] ?? false) !== true): ?>
    <section class="admin-section"><div class="admin-alert">زیرساخت عملیاتی اتوماسیون در دسترس نیست. لطفاً وضعیت اتصال اختصاصی Automation را بررسی کنید.</div></section>
<?php else: ?>
<section class="admin-grid">
    <?php foreach ([
        'all' => 'همه مکاتبات', 'drafts' => 'پیش نویس‌ها', 'incoming' => 'وارده', 'outgoing' => 'صادره', 'internal' => 'داخلی', 'recent' => 'به‌روزرسانی اخیر'
    ] as $key => $label): ?>
        <a class="admin-card admin-card--link" href="/admin/automation/correspondences<?= in_array($key, ['incoming','outgoing','internal'], true) ? '?direction=' . $key : ($key === 'drafts' ? '?status=draft' : '') ?>">
            <span><?= admin_h($label) ?></span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($counts[$key] ?? 0))) ?></strong>
        </a>
    <?php endforeach; ?>
</section>
<section class="admin-section">
    <div class="admin-section__header"><div><h2>شروع کار</h2><p class="admin-muted">برای نمایش عملیاتی، یک پیش نویس ایجاد کنید و سپس نسخه‌ها، طرف‌ها و تاریخچه را در فضای کاری ببینید.</p></div></div>
    <div class="admin-action-grid">
        <a class="admin-action-tile admin-action-tile--teal" href="/admin/automation/correspondences">
            <span class="admin-action-tile__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></span>
            <span class="admin-action-tile__body">
                <strong>فهرست مکاتبات</strong>
                <small>جستجو، فیلتر و مشاهده مکاتبات</small>
            </span>
        </a>
        <a class="admin-action-tile admin-action-tile--blue" href="/admin/automation/correspondences/create?direction=incoming">
            <span class="admin-action-tile__icon"><?= \App\Support\AdminIcon::html('circle-check') ?></span>
            <span class="admin-action-tile__body">
                <strong>نامه وارده</strong>
                <small>ثبت مشخصات و بارگذاری تصویر اصل نامه</small>
            </span>
        </a>
        <a class="admin-action-tile admin-action-tile--teal" href="/admin/automation/correspondences/create?direction=outgoing">
            <span class="admin-action-tile__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></span>
            <span class="admin-action-tile__body">
                <strong>نامه صادره</strong>
                <small>انتخاب قالب، نگارش متن و تعیین گیرنده بیرونی</small>
            </span>
        </a>
        <a class="admin-action-tile admin-action-tile--blue" href="/admin/automation/correspondences/create?direction=internal">
            <span class="admin-action-tile__icon"><?= \App\Support\AdminIcon::html('users') ?></span>
            <span class="admin-action-tile__body">
                <strong>نامه داخلی</strong>
                <small>مکاتبه میان اشخاص و واحدهای داخل سازمان</small>
            </span>
        </a>
        <?php if ($canManageExternalDirectory): ?>
        <a class="admin-action-tile admin-action-tile--blue" href="/admin/automation/external-organizations">
            <span class="admin-action-tile__icon"><?= \App\Support\AdminIcon::html('organization') ?></span>
            <span class="admin-action-tile__body">
                <strong>سازمان‌های بیرونی</strong>
                <small>دبیرخانه‌های مقصد، راه‌های ارتباطی و نشانی پستی</small>
            </span>
        </a>
        <?php endif; ?>

        <a class="admin-action-tile admin-action-tile--teal" href="/admin/automation/templates">
            <span class="admin-action-tile__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></span>
            <span class="admin-action-tile__body">
                <strong>قالب‌های نامه</strong>
                <small>قالب‌های A4 و A5، فارسی و انگلیسی و امضاها</small>
            </span>
        </a>
    </div>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
