<?php

$items = $page['items'] ?? [];
$unread = (int) ($page['unread_count'] ?? 0);
$messageDate = static function ($value): string {
    $value = trim((string) $value);
    $timestamp = $value === '' ? false : strtotime($value);
    if ($timestamp === false) return $value;
    return \IPKF\Support\PersianDate::fromGregorianDate(date('Y-m-d', $timestamp))
        . ' - ' . date('H:i', $timestamp);
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
            href="/admin/messages/sent"
        >
            پیام‌های ارسالی
        </a>
    </div>
    <h2 style="margin-top:1rem">کارتابل داخلی</h2>
    <p class="communication-muted">
        <?= admin_h(
            \App\Support\AdminFormat::digits($unread)
        ) ?>
        پیام خوانده‌نشده
    </p>
</section>

<section class="communication-panel" style="margin-top:1rem">
    <?php if ($items === []): ?>
        <p class="communication-muted">
            هنوز گفتگویی برای شما ثبت نشده است.
        </p>
    <?php else: ?>
        <div class="communication-table-wrap">
            <table class="communication-table">
                <thead>
                    <tr>
                        <th>طرف گفتگو</th>
                        <th>موضوع</th>
                        <th>آخرین پیام</th>
                        <th>زمان</th>
                        <th>وضعیت</th>
                        <th>خوانده‌نشده</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr class="communication-clickable-row <?= (int) $item['unread_count'] > 0
                        ? 'communication-row-unread'
                        : '' ?>">
                        <td><?= admin_h(
                            $item['counterpart_title'] ?? '—'
                        ) ?></td>
                        <td>
                            <a href="<?= admin_h(
                                '/admin/messages/thread/'
                                . rawurlencode(
                                    $item['public_reference']
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
                                (string) (
                                    $item['last_message_body']
                                    ?? ''
                                ),
                                0,
                                120,
                                'UTF-8'
                            )
                        ) ?></td>
                        <td dir="ltr"><?= admin_h(
                            $messageDate($item['last_message_at'] ?? '')
                        ) ?></td>
                        <td><?= ($item['status_code'] ?? 'active') === 'closed'
                            ? 'بسته' : 'باز' ?></td>
                        <td><?= admin_h(
                            \App\Support\AdminFormat::digits(
                                (int) $item['unread_count']
                            )
                        ) ?></td>
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
