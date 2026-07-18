<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}
$detail = $detail ?? [];
$workspace = $detail['workspace'] ?? [];
$tabs = $detail['tabs'] ?? [];
$activeTab = (string) ($detail['active_tab'] ?? 'summary');
$c = $detail['correspondence'] ?? [];
ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb"><a href="/admin/dashboard">داشبورد</a><span>/</span><a href="/admin/automation">اتوماسیون</a><span>/</span><a href="/admin/automation/correspondences">مکاتبات</a><span>/</span><span>جزئیات</span></nav>
<?php ob_start(); ?>
<?php if ($activeTab === 'summary'): ?>
    <section class="entity-section"><div class="admin-section__header"><div><h2>خلاصه مکاتبه</h2><p class="admin-muted">اطلاعات پایه بدون نمایش شناسه‌های فنی</p></div><?php if ($detail['editable'] ?? false): ?><a class="admin-button" href="<?= admin_h($detail['edit_url']) ?>">ویرایش پیش نویس</a><?php endif; ?></div><div class="entity-field-grid">
        <?php foreach (['شناسه عمومی' => $c['public_reference'] ?? '', 'موضوع' => $c['subject'] ?? '', 'قالب نامه' => $c['document_template'] ?? '', 'نوع/جهت' => $c['type'] ?? '', 'اولویت' => $c['priority'] ?? '', 'محرمانگی' => $c['confidentiality'] ?? '', 'کانال' => $c['channel'] ?? '', 'شماره بیرونی' => $c['external_number'] ?? '', 'تاریخ بیرونی' => $c['external_date'] ?? '', 'ایجاد' => $c['created_at'] ?? '', 'آخرین تغییر' => $c['updated_at'] ?? ''] as $label => $value): ?><div class="entity-field"><span><?= admin_h($label) ?></span><strong><?= admin_h($value) ?></strong></div><?php endforeach; ?>
    </div><p><?= admin_h($c['summary'] ?? '') ?></p></section>
<?php elseif ($activeTab === 'content'): ?>
    <section class="entity-section"><h2>نسخه جاری</h2><article class="automation-content-box"><?= nl2br(admin_h($c['content'] ?? '')) ?></article></section>
<?php elseif ($activeTab === 'parties'): ?>
    <section class="entity-section"><h2>طرف‌های مکاتبه</h2><?php if (($detail['parties'] ?? []) === []): ?><div class="admin-empty-state">طرفی ثبت نشده است.</div><?php else: ?><div class="entity-card-list"><?php foreach ($detail['parties'] as $party): ?><article class="entity-info-card"><header><strong><?= admin_h($party['display']) ?></strong><span class="admin-pill"><?= admin_h($party['role']) ?></span></header><p><?= admin_h($party['kind']) ?></p><small><?= admin_h($party['contact']) ?></small></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($activeTab === 'versions'): ?>
    <section class="entity-section"><h2>نسخه‌ها</h2><div class="admin-users-table-wrap"><table class="admin-table"><thead><tr><th>نسخه</th><th>موضوع</th><th>یادداشت</th><th>زمان ایجاد</th></tr></thead><tbody><?php foreach ($detail['versions'] as $version): ?><tr><td><?= admin_h($version['number']) ?></td><td><?= admin_h($version['subject']) ?></td><td><?= admin_h($version['change_note']) ?></td><td><?= admin_h($version['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php else: ?>
    <section class="entity-section"><h2>تاریخچه رویداد</h2><div class="entity-card-list"><?php foreach ($detail['events'] as $event): ?><article class="entity-info-card"><header><strong><?= admin_h($event['type']) ?></strong><span><?= admin_h($event['occurred_at']) ?></span></header><p>از <?= admin_h($event['from']) ?> به <?= admin_h($event['to']) ?></p></article><?php endforeach; ?></div></section>
<?php endif; ?>
<?php $workspaceContent = ob_get_clean(); require __DIR__ . '/partials/entity-workspace.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
