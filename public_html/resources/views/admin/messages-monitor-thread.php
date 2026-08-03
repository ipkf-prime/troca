<?php $conversation = $page['conversation'] ?? []; $messages = $page['messages'] ?? []; ob_start(); require BASE_PATH . '/resources/views/admin/partials/communication-style.php'; ?>
<div class="message-page-shell"><section class="communication-panel"><a class="message-back-link" href="/admin/messages/monitor">بازگشت به نظارت</a>
<h2><?= admin_h($conversation['subject'] ?: 'گفتگوی داخلی') ?></h2><p class="communication-muted">نمایش نظارتی فقط‌خواندنی؛ وضعیت خواندن گیرندگان تغییر نمی‌کند.</p></section>
<section class="communication-panel" style="margin-top:1rem"><div class="communication-thread">
<?php foreach ($messages as $message): ?>
    <article class="communication-message">
        <header><strong><?= admin_h($message['sender_title']) ?></strong><time><?= admin_h(\App\Support\AdminFormat::jalaliDateTime($message['sent_at'])) ?></time></header>
        <p><?= nl2br(admin_h($message['body'])) ?></p>
        <?php foreach (($message['attachments'] ?? []) as $attachment): ?>
            <span class="communication-muted">📎 <?= admin_h($attachment['original_name']) ?> · <?= admin_h(\App\Support\AdminFormat::digits($attachment['size_bytes'])) ?> بایت</span>
        <?php endforeach; ?>
    </article>
<?php endforeach; ?>
</div></section></div><?php $content = ob_get_clean(); require BASE_PATH . '/resources/views/admin/layout.php'; ?>
