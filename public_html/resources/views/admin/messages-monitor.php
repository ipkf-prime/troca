<?php $items = $page['items'] ?? []; $audit = $page['audit'] ?? []; ob_start(); ?>
<section class="communication-panel">
    <header class="communication-panel__head"><div><h2>نظارت بر پیام‌ها</h2><p class="communication-muted">دسترسی فقط‌خواندنی است و مشاهده محتوا فقط پس از ثبت دلیل، ممیزی می‌شود.</p></div></header>
    <?php if ($items === []): ?><div class="admin-empty-state">پیامی ثبت نشده است.</div><?php endif; ?>
    <?php foreach ($items as $item): ?>
        <article class="communication-row">
            <div><strong><?= admin_h($item['subject'] ?: 'بدون موضوع') ?></strong>
                <p><?= admin_h($item['creator_title'] ?? '—') ?> · <?= admin_h(\App\Support\AdminFormat::jalaliDateTime($item['last_message_at'] ?? '')) ?> · <?= admin_h(\App\Support\AdminFormat::digits($item['message_count'])) ?> پیام · <?= admin_h(\App\Support\AdminFormat::digits($item['attachment_count'])) ?> پیوست</p></div>
            <form method="post" action="/admin/messages/monitor/<?= admin_h(rawurlencode((string) $item['public_reference'])) ?>">
                <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                <input name="reason" minlength="5" maxlength="1000" required placeholder="دلیل یا شماره پیگیری">
                <button class="admin-button admin-button--soft" type="submit">مشاهده نظارتی</button>
            </form>
        </article>
    <?php endforeach; ?>
</section>
<section class="communication-panel" style="margin-top:1rem"><h2>لاگ دسترسی نظارتی</h2>
<?php if ($audit === []): ?><div class="admin-empty-state">هنوز رویداد نظارتی ثبت نشده است.</div><?php else: ?>
<div class="communication-table-wrap"><table class="communication-table"><thead><tr><th>مدیر</th><th>رویداد</th><th>دلیل</th><th>IP</th><th>زمان</th></tr></thead><tbody>
<?php foreach ($audit as $event): ?><tr><td><?= admin_h($event['actor_title']) ?></td><td><?= admin_h($event['event_code']) ?></td><td><?= admin_h($event['reason'] ?: '—') ?></td><td dir="ltr"><?= admin_h($event['ip_address'] ?: '—') ?></td><td><?= admin_h(\App\Support\AdminFormat::jalaliDateTime($event['occurred_at'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php $content = ob_get_clean(); require BASE_PATH . '/resources/views/admin/layout.php'; ?>
