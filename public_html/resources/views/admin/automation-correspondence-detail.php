<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}
$detail = $detail ?? [];
$workspace = $detail['workspace'] ?? [];
$tabs = $detail['tabs'] ?? [];
$activeTab = (string) ($detail['active_tab'] ?? 'summary');
$c = $detail['correspondence'] ?? [];
$attachmentStatus = (string) ($attachmentStatus ?? '');
$registrationStatus = (string) ($registrationStatus ?? '');
$canRegister = (bool) ($canRegister ?? false);
ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb"><a href="/admin/dashboard">داشبورد</a><span>/</span><a href="/admin/automation">اتوماسیون</a><span>/</span><a href="/admin/automation/correspondences">مکاتبات</a><span>/</span><span>جزئیات</span></nav>
<?php ob_start(); ?>
<?php if($attachmentStatus==='uploaded'):?><div class="admin-alert admin-alert--success">پیوست با موفقیت ثبت شد.</div><?php elseif($attachmentStatus!==''):?><div class="admin-alert admin-alert--danger">ثبت پیوست انجام نشد؛ نوع یا حجم فایل را بررسی کنید.</div><?php endif;?>

<?php if ($registrationStatus === 'registered'): ?>
<div class="admin-alert admin-alert--success">
    مکاتبه با موفقیت در دبیرخانه ثبت رسمی شد.
    <?php if (($c['official_number'] ?? '—') !== '—'): ?>
        شماره ثبت:
        <strong><?= admin_h($c['official_number']) ?></strong>
    <?php endif; ?>
</div>
<?php elseif ($registrationStatus === 'already_registered'): ?>
<div class="admin-alert admin-alert--success">
    این مکاتبه قبلاً ثبت رسمی شده است.
    <?php if (($c['official_number'] ?? '—') !== '—'): ?>
        شماره ثبت:
        <strong><?= admin_h($c['official_number']) ?></strong>
    <?php endif; ?>
</div>
<?php elseif ($registrationStatus === 'invalid_csrf'): ?>
<div class="admin-alert admin-alert--danger">
    نشست فرم معتبر نیست؛ صفحه را تازه‌سازی و دوباره تلاش کنید.
</div>
<?php elseif ($registrationStatus === 'registry_book_unavailable'): ?>
<div class="admin-alert admin-alert--danger">
    دفتر ثبت فعال و مجاز برای این نوع مکاتبه پیدا نشد.
</div>
<?php elseif ($registrationStatus === 'registry_book_ambiguous'): ?>
<div class="admin-alert admin-alert--danger">
    بیش از یک دفتر ثبت معتبر برای این مکاتبه پیدا شد؛ تنظیم دبیرخانه باید بررسی شود.
</div>
<?php elseif ($registrationStatus === 'correspondence_not_registerable'): ?>
<div class="admin-alert admin-alert--danger">
    وضعیت فعلی مکاتبه اجازه ثبت رسمی نمی‌دهد.
</div>
<?php elseif ($registrationStatus !== ''): ?>
<div class="admin-alert admin-alert--danger">
    ثبت رسمی مکاتبه انجام نشد.
    <span dir="ltr"><?= admin_h($registrationStatus) ?></span>
</div>
<?php endif; ?>

<?php if ($activeTab === 'summary'): ?>
    <section class="entity-section"><div class="admin-section__header"><div><h2>خلاصه مکاتبه</h2><p class="admin-muted">اطلاعات پایه بدون نمایش شناسه‌های فنی</p></div><div class="admin-form-actions"><?php if ($detail['editable'] ?? false): ?><a class="admin-button admin-button--soft" href="<?= admin_h($detail['edit_url']) ?>">ویرایش پیش‌نویس</a><?php endif; ?><?php if (($detail['editable'] ?? false) && $canRegister): ?><form method="post" action="/admin/automation/correspondences/<?= admin_h($c['public_reference'] ?? '') ?>/register"><input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>"><button class="admin-button" type="submit">ثبت رسمی در دبیرخانه</button></form><?php endif; ?></div></div><div class="entity-field-grid">
        <?php foreach (['شناسه عمومی' => $c['public_reference'] ?? '', 'موضوع' => $c['subject'] ?? '', 'قالب نامه' => $c['document_template'] ?? '', 'نوع/جهت' => $c['type'] ?? '', 'اولویت' => $c['priority'] ?? '', 'محرمانگی' => $c['confidentiality'] ?? '', 'کانال' => $c['channel'] ?? '', ...((($c['direction_code'] ?? '') === 'incoming') ? ['شماره بیرونی' => $c['external_number'] ?? '', 'تاریخ بیرونی' => $c['external_date'] ?? ''] : []), 'شماره ثبت رسمی' => $c['official_number'] ?? '—', 'تاریخ ثبت رسمی' => $c['official_registered_at'] ?? '—', 'ایجاد' => $c['created_at'] ?? '', 'آخرین تغییر' => $c['updated_at'] ?? ''] as $label => $value): ?><div class="entity-field"><span><?= admin_h($label) ?></span><strong><?= admin_h($value) ?></strong></div><?php endforeach; ?>
    </div><?php if (($c['summary'] ?? '—') !== '—'): ?><p><?= admin_h($c['summary']) ?></p><?php endif; ?></section>
<?php elseif ($activeTab === 'content'): ?>
    <section class="entity-section"><h2>نسخه جاری</h2><?php foreach($detail['relations']??[] as $relation):?><p class="automation-reference-line"><?=admin_h($relation['line'])?></p><?php endforeach;?><article class="automation-content-box"><?= nl2br(admin_h($c['content'] ?? '')) ?></article><?php $copies=array_filter($detail['parties']??[],static fn($party)=>in_array($party['role_code']??'', ['cc','bcc'],true));if($copies):?><div class="automation-copy-block"><strong>رونوشت:</strong><?php foreach($copies as $copy):?><span><?=admin_h($copy['display'])?></span><?php endforeach;?></div><?php endif;?></section>
<?php elseif ($activeTab === 'parties'): ?>
    <section class="entity-section"><h2>گیرندگان و رونوشت‌ها</h2><?php if (($detail['parties'] ?? []) === []): ?><div class="admin-empty-state">طرفی ثبت نشده است.</div><?php else: ?><div class="entity-card-list"><?php foreach ($detail['parties'] as $party): ?><article class="entity-info-card"><header><strong><?= admin_h($party['display']) ?></strong><span class="admin-pill"><?= admin_h($party['role']) ?></span></header><p><?= admin_h($party['kind']) ?></p><small><?= admin_h($party['contact']) ?></small></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($activeTab === 'relations'): ?>
    <section class="entity-section"><h2>عطف، پیرو و نامه‌های مرتبط</h2><?php if(($detail['relations']??[])===[]):?><div class="admin-empty-state">ارتباطی با نامه‌های دیگر ثبت نشده است.</div><?php else:?><div class="entity-card-list"><?php foreach($detail['relations'] as $relation):?><article class="entity-info-card"><header><strong><?=admin_h($relation['subject'])?></strong><span class="admin-pill"><?=admin_h($relation['type'])?></span></header><p>شماره: <?=admin_h($relation['number'])?> · تاریخ: <?=admin_h($relation['date'])?></p><small><?=admin_h($relation['note'])?></small></article><?php endforeach;?></div><?php endif;?></section>
<?php elseif ($activeTab === 'attachments'): ?>
    <section class="entity-section"><div class="admin-section__header"><div><h2>پیوست‌های نامه</h2><p class="admin-muted">PDF، Word، JPG یا PNG؛ حداکثر ۱۰ مگابایت</p></div></div><?php if($detail['editable']??false):?><form class="admin-form-grid" method="post" enctype="multipart/form-data" action="<?=admin_h($detail['edit_url'].'/attachments')?>"><input type="hidden" name="_token" value="<?=admin_h((new \IPKF\Security\Csrf())->token())?>"><label><span>عنوان پیوست</span><input name="title" maxlength="255"></label><label><span>نوع فایل</span><select name="attachment_role_code"><option value="enclosure">پیوست</option><option value="supporting">مدرک پشتیبان</option><option value="scan">تصویر اسکن‌شده</option><option value="main">فایل اصلی</option></select></label><label class="admin-form-grid__wide"><span>انتخاب فایل</span><input type="file" name="attachment" accept=".pdf,.docx,.jpg,.jpeg,.png" required></label><div class="admin-form-actions"><button class="admin-button" type="submit">افزودن پیوست</button></div></form><?php endif;?><?php if(($detail['attachments']??[])===[]):?><div class="admin-empty-state">پیوستی ثبت نشده است.</div><?php else:?><div class="entity-card-list"><?php foreach($detail['attachments'] as $attachment):?><article class="entity-info-card"><header><strong><?=admin_h($attachment['title']==='—'?$attachment['filename']:$attachment['title'])?></strong><span class="admin-pill"><?=admin_h($attachment['role'])?></span></header><p><?=admin_h($attachment['filename'])?> · <?=admin_h($attachment['size'])?></p><a class="admin-button admin-button--soft admin-button--compact" href="<?=admin_h($attachment['url'])?>">دریافت فایل</a></article><?php endforeach;?></div><?php endif;?></section>
<?php elseif ($activeTab === 'versions'): ?>
    <section class="entity-section"><h2>نسخه‌ها</h2><div class="admin-users-table-wrap"><table class="admin-table"><thead><tr><th>نسخه</th><th>موضوع</th><th>یادداشت</th><th>زمان ایجاد</th></tr></thead><tbody><?php foreach ($detail['versions'] as $version): ?><tr><td><?= admin_h($version['number']) ?></td><td><?= admin_h($version['subject']) ?></td><td><?= admin_h($version['change_note']) ?></td><td><?= admin_h($version['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php else: ?>
    <section class="entity-section"><h2>تاریخچه رویداد</h2><div class="entity-card-list"><?php foreach ($detail['events'] as $event): ?><article class="entity-info-card"><header><strong><?= admin_h($event['type']) ?></strong><span><?= admin_h($event['occurred_at']) ?></span></header><p>از <?= admin_h($event['from']) ?> به <?= admin_h($event['to']) ?></p></article><?php endforeach; ?></div></section>
<?php endif; ?>
<?php $workspaceContent = ob_get_clean(); require __DIR__ . '/partials/entity-workspace.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
