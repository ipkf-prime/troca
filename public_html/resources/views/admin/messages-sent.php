<?php

$items = $page['items'] ?? [];
$messageDate = static function ($value): string {
    $value = trim((string) $value);
    $timestamp = $value === '' ? false : strtotime($value);
    if ($timestamp === false) return $value;
    return \IPKF\Support\PersianDate::fromGregorianDate(date('Y-m-d', $timestamp))
        . ' - ' . \App\Support\AdminFormat::digits(
            date('H:i', $timestamp)
        );
};
ob_start();
require BASE_PATH
    . '/resources/views/admin/partials/communication-style.php';
?>
<section class="communication-panel">
    <div class="communication-actions">
        <a class="admin-button" href="/admin/messages/compose">
            ارسال پیام
        </a>
        <a
            class="admin-button admin-button--soft"
            href="/admin/messages/inbox"
        >
            کارتابل داخلی
        </a>
    </div>
    <h2 style="margin-top:1rem">پیام‌های ارسالی</h2>
</section>

<section class="communication-panel" style="margin-top:1rem">
    <?php
    $listBasePath = '/admin/messages/sent';
    $showUnreadFilter = false;
    $listSortOptions = ['date' => 'تاریخ', 'recipient' => 'گیرنده', 'subject' => 'موضوع', 'status' => 'وضعیت'];
    require BASE_PATH . '/resources/views/admin/partials/message-list-tools.php';
    ?>
    <?php if ($items === []): ?>
        <p class="communication-muted">
            هنوز پیامی ارسال نکرده‌اید.
        </p>
    <?php else: ?>
        <div class="communication-table-wrap">
            <table class="communication-table">
                <thead>
                    <tr class="communication-clickable-row">
                        <th>گیرنده</th>
                        <th>موضوع</th>
                        <th>متن</th>
                        <th>زمان</th>
                        <th>وضعیت گفتگو</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= admin_h(
                            $item['recipients_title'] ?? '—'
                        ) ?></td>
                        <td>
                            <a href="<?= admin_h(
                                '/admin/messages/thread/'
                                . rawurlencode(
                                    $item['conversation_reference']
                                )
                            ) ?>" class="communication-row-link">
                                <?= admin_h(
                                    $item['subject']
                                    ?: 'بدون موضوع'
                                ) ?>
                            </a>
                        </td>
                        <td><?= admin_h(
                            mb_substr(
                                (string) $item['body'],
                                0,
                                120,
                                'UTF-8'
                            )
                        ) ?></td>
                        <td dir="ltr"><?= admin_h(
                            $messageDate($item['sent_at'] ?? '')
                        ) ?></td>
                        <td><?= ($item['status_code'] ?? 'active') === 'closed'
                            ? 'بسته' : 'باز' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
