<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}
$dashboard = $dashboard ?? ['ok' => false, 'counts' => []];
$counts = $dashboard['counts'] ?? [];
ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span aria-hidden="true">/</span><span>اتوماسیون اداری</span>
</nav>
<section class="admin-module-hub admin-module-hub--teal admin-users-heading">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></div>
    <div><h2>اتوماسیون اداری</h2><p>برش نمایشی عملیات مکاتبات؛ ایجاد پیش نویس، نسخه‌ها، طرف‌ها و تاریخچه</p></div>
    <a class="admin-module-hub__back" href="/admin/automation/correspondences/create">ایجاد پیش نویس</a>
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
            <span class="admin-action-tile__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></span><strong>فهرست مکاتبات</strong><small>جستجو، فیلتر و مشاهده مکاتبات</small>
        </a>
        <a class="admin-action-tile admin-action-tile--blue" href="/admin/automation/correspondences/create">
            <span class="admin-action-tile__icon"><?= \App\Support\AdminIcon::html('circle-check') ?></span><strong>ایجاد پیش نویس</strong><small>ثبت مکاتبه، طرف‌ها و نسخه اول</small>
        </a>
    </div>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
