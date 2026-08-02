<?php

$conversation = $page['conversation'] ?? [];
$messages = $page['messages'] ?? [];
$currentUserId = (int) ($context['user_id'] ?? 0);
$conversationStatus = (string) (
    $conversation['status_code'] ?? 'active'
);
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
<section class="communication-panel">
    <a
        class="admin-button admin-button--soft"
        href="/admin/messages/inbox"
    >
        بازگشت به کارتابل
    </a>
    <div class="communication-panel__head" style="margin-top:1rem">
        <div>
            <h2><?= admin_h(
                $conversation['subject'] ?: 'گفتگوی داخلی'
            ) ?></h2>
            <p class="communication-muted">
                وضعیت: <?= $conversationStatus === 'closed'
                    ? 'بسته' : 'باز' ?>
            </p>
        </div>
        <form
            method="post"
            action="<?= admin_h(
                '/admin/messages/thread/'
                . rawurlencode($conversation['public_reference'])
                . ($conversationStatus === 'closed'
                    ? '/reopen' : '/close')
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
    </div>
    <?php if ($statusMessage !== ''): ?>
        <p class="communication-muted" style="margin-top:.75rem">
            <?= admin_h($statusMessage) ?>
        </p>
    <?php endif; ?>
</section>

<section class="communication-panel" style="margin-top:1rem" id="reply">
    <div class="communication-thread">
        <?php foreach ($messages as $message): ?>
            <article class="communication-message<?= (int) (
                $message['sender_user_id'] ?? 0
            ) === $currentUserId ? ' is-mine' : '' ?>">
                <header>
                    <strong><?= admin_h(
                        $message['sender_title']
                    ) ?></strong>
                    <time dir="ltr"><?= admin_h(
                        $message['sent_at']
                    ) ?></time>
                </header>
                <p><?= nl2br(
                    admin_h($message['body'])
                ) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($conversationStatus === 'active'): ?>
<section class="communication-panel" style="margin-top:1rem">
    <h3>ارسال پاسخ</h3>
    <form
        class="communication-form"
        method="post"
        action="<?= admin_h(
            '/admin/messages/thread/'
            . rawurlencode(
                $conversation['public_reference']
            )
            . '/reply'
        ) ?>"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= admin_h(
                (new \IPKF\Security\Csrf())->token()
            ) ?>"
        >
        <label>
            <span>متن پاسخ</span>
            <textarea
                name="body"
                maxlength="20000"
                required
            ></textarea>
        </label>
        <button class="admin-button" type="submit">
            ارسال پاسخ
        </button>
    </form>
</section>
<?php else: ?>
<section class="communication-panel" style="margin-top:1rem">
    <p class="communication-muted">
        این گفتگو بسته شده است. برای ارسال پاسخ، ابتدا آن را بازگشایی کنید.
    </p>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
