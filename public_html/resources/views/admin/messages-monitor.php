<?php
$items = $page['items'] ?? [];
$audit = $page['audit'] ?? [];
ob_start();
require BASE_PATH . '/resources/views/admin/partials/communication-style.php';
?>
<div class="message-page-shell message-page-shell--wide">
    <section class="communication-panel communication-compact-head">
        <div><h2>نظارت بر پیام‌ها</h2><p class="communication-muted">فهرست فقط‌خواندنی است؛ مشاهده متن هر گفتگو پس از ثبت دلیل در لاگ ممیزی انجام می‌شود.</p></div>
    </section>
    <section class="communication-panel">
        <?php
        $listBasePath = '/admin/messages/monitor';
        $showUnreadFilter = false;
        $listSortOptions = ['date' => 'آخرین فعالیت', 'creator' => 'ایجادکننده', 'subject' => 'موضوع', 'status' => 'وضعیت', 'messages' => 'تعداد پیام'];
        require BASE_PATH . '/resources/views/admin/partials/message-list-tools.php';
        ?>
        <?php if ($items === []): ?>
            <div class="admin-empty-state">پیامی مطابق فیلترها پیدا نشد.</div>
        <?php else: ?>
            <div class="communication-table-wrap"><table class="communication-table communication-monitor-table">
                <thead><tr><th>موضوع</th><th>ایجادکننده</th><th>آخرین فعالیت</th><th>پیام</th><th>پیوست</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody><?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?= admin_h($item['subject'] ?: 'بدون موضوع') ?></strong></td>
                        <td><?= admin_h($item['creator_title'] ?? '—') ?></td>
                        <td><?= admin_h(\App\Support\AdminFormat::jalaliDateTime($item['last_message_at'] ?? '')) ?></td>
                        <td><?= admin_h(\App\Support\AdminFormat::digits($item['message_count'])) ?></td>
                        <td><?= admin_h(\App\Support\AdminFormat::digits($item['attachment_count'])) ?></td>
                        <td><span class="communication-status communication-status--<?= ($item['status_code'] ?? '') === 'closed' ? 'closed' : 'active' ?>"><?= ($item['status_code'] ?? '') === 'closed' ? 'بسته' : 'باز' ?></span></td>
                        <td><details class="monitor-reason"><summary class="admin-button admin-button--soft">مشاهده</summary>
                            <form method="post" action="/admin/messages/monitor/<?= admin_h(rawurlencode((string) $item['public_reference'])) ?>">
                                <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                                <label>دلیل مشاهده یا شماره پیگیری<input name="reason" minlength="5" maxlength="1000" required placeholder="حداقل ۵ نویسه"></label>
                                <button class="admin-button" type="submit">ثبت و مشاهده گفتگو</button>
                            </form>
                        </details></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <details class="communication-panel communication-audit"><summary>لاگ دسترسی نظارتی (<?= admin_h(\App\Support\AdminFormat::digits(count($audit))) ?> رویداد اخیر)</summary>
        <?php if ($audit === []): ?><div class="admin-empty-state">هنوز رویدادی ثبت نشده است.</div><?php else: ?>
        <div class="communication-table-wrap"><table class="communication-table"><thead><tr><th>مدیر</th><th>رویداد</th><th>دلیل</th><th>IP</th><th>زمان</th></tr></thead><tbody>
        <?php foreach ($audit as $event): ?><tr><td><?= admin_h($event['actor_title']) ?></td><td><?= admin_h($event['event_code']) ?></td><td><?= admin_h($event['reason'] ?: '—') ?></td><td dir="ltr"><?= admin_h($event['ip_address'] ?: '—') ?></td><td><?= admin_h(\App\Support\AdminFormat::jalaliDateTime($event['occurred_at'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </details>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/resources/views/admin/layout.php'; ?>
