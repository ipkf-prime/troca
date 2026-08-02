<?php

$conversation = $page['conversation'] ?? [];
$messages = $page['messages'] ?? [];
$currentUserId = (int) ($context['user_id'] ?? 0);
$conversationStatus = (string) ($conversation['status_code'] ?? 'active');
$conversationReference = (string) ($conversation['public_reference'] ?? '');
$messageDate = static function ($value): string {
    $value = trim((string) $value);
    $timestamp = $value === '' ? false : strtotime($value);
    if ($timestamp === false) return $value;
    return \IPKF\Support\PersianDate::fromGregorianDate(date('Y-m-d', $timestamp))
        . ' - ' . \App\Support\AdminFormat::digits(
            date('H:i', $timestamp)
        );
};
$status = trim((string) ($_GET['status'] ?? ''));
$statusMessage = match ($status) {
    'sent' => 'پیام با موفقیت ارسال شد.',
    'replied' => 'پاسخ با موفقیت ارسال شد.',
    'closed' => 'گفتگو بسته شد.',
    'reopened' => 'گفتگو دوباره باز شد.',
    'message_conversation_closed' =>
        'این گفتگو بسته است؛ برای پاسخ ابتدا آن را باز کنید.',
    default => '',
};
ob_start();
require BASE_PATH
    . '/resources/views/admin/partials/communication-style.php';
?>
<div class="message-page-shell">
    <section class="communication-panel message-thread-head">
        <div class="message-thread-head__main">
            <a class="message-back-link" href="/admin/messages/inbox">
                بازگشت به کارتابل
            </a>
            <div class="message-thread-title-row">
                <div>
                    <h2><?= admin_h(
                        $conversation['subject'] ?: 'گفتگوی داخلی'
                    ) ?></h2>
                    <p class="communication-muted">
                        <?= count($messages) ?> پیام در این گفتگو
                    </p>
                </div>
                <span class="communication-status communication-status--<?=
                    $conversationStatus === 'closed' ? 'closed' : 'active'
                ?>">
                    <?= $conversationStatus === 'closed' ? 'بسته' : 'باز' ?>
                </span>
            </div>
            <?php if ($statusMessage !== ''): ?>
                <p class="communication-muted message-thread-notice">
                    <?= admin_h($statusMessage) ?>
                </p>
            <?php endif; ?>
        </div>
        <form
            method="post"
            action="<?= admin_h(
                '/admin/messages/thread/'
                . rawurlencode($conversationReference)
                . ($conversationStatus === 'closed' ? '/reopen' : '/close')
            ) ?>"
        >
            <input type="hidden" name="_token" value="<?= admin_h(
                (new \IPKF\Security\Csrf())->token()
            ) ?>">
            <button class="admin-button admin-button--soft" type="submit">
                <?= $conversationStatus === 'closed'
                    ? 'بازگشایی گفتگو' : 'بستن گفتگو' ?>
            </button>
        </form>
    </section>

    <section class="communication-panel message-thread-panel">
        <div class="communication-thread">
            <?php foreach ($messages as $message): ?>
                <?php $isMine = (int) (
                    $message['sender_user_id'] ?? 0
                ) === $currentUserId; ?>
                <article class="communication-message<?= $isMine
                    ? ' is-mine' : ' is-other' ?>">
                    <header>
                        <strong><?= admin_h($message['sender_title']) ?></strong>
                        <time dir="ltr"><?= admin_h(
                            $messageDate($message['sent_at'] ?? '')
                        ) ?></time>
                    </header>
                    <p><?= nl2br(admin_h($message['body'])) ?></p>
                    <?php foreach (($message['attachments'] ?? []) as $attachment): ?>
                        <a
                            class="admin-button admin-button--soft admin-button--compact"
                            href="/admin/messages/attachments/<?= admin_h(
                                rawurlencode((string) $attachment['public_reference'])
                            ) ?>"
                        >
                            📎 <?= admin_h($attachment['original_name']) ?>
                            (<?= admin_h(\App\Support\AdminFormat::digits(
                                number_format(
                                    ((int) $attachment['size_bytes']) / 1048576,
                                    2
                                )
                            )) ?> MB)
                        </a>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($conversationStatus === 'active'): ?>
        <section class="communication-panel message-reply-panel" id="reply">
            <form
                class="message-reply-form"
                method="post"
                enctype="multipart/form-data"
                action="<?= admin_h(
                    '/admin/messages/thread/'
                    . rawurlencode($conversationReference)
                    . '/reply'
                ) ?>"
            >
                <input type="hidden" name="_token" value="<?= admin_h(
                    (new \IPKF\Security\Csrf())->token()
                ) ?>">
                <label for="message-reply-body">پاسخ شما</label>
                <textarea
                    id="message-reply-body"
                    name="body"
                    maxlength="20000"
                    placeholder="پاسخ خود را بنویسید…"
                ></textarea>
                <label>
                    <span>افزودن پیوست</span>
                    <input
                        type="file"
                        name="attachments[]"
                        multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt"
                    >
                </label>
                <div class="message-reply-actions">
                    <button class="admin-button" type="submit">
                        ارسال پاسخ
                    </button>
                </div>
            </form>
        </section>
    <?php else: ?>
        <section class="communication-panel message-closed-note">
            این گفتگو بسته است. برای ادامه، آن را بازگشایی کنید.
        </section>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
