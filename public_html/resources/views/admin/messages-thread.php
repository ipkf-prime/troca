<?php

$conversation = $page['conversation'] ?? [];
$messages = $page['messages'] ?? [];
$currentUserId = (int) ($context['user_id'] ?? 0);
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
    <h2 style="margin-top:1rem">
        <?= admin_h(
            $conversation['subject']
            ?: 'گفتگوی داخلی'
        ) ?>
    </h2>
</section>

<section class="communication-panel" style="margin-top:1rem">
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
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
