<?php

$recipients = $page['recipients'] ?? [];
$status = (string) ($status ?? '');
ob_start();
require BASE_PATH
    . '/resources/views/admin/partials/communication-style.php';
?>
<section class="communication-panel">
    <h2>ارسال پیام داخلی</h2>
    <p class="communication-muted">
        گیرندگان از سیاست فعال دیتابیس خوانده می‌شوند.
        فعلاً سیاست پایه کاربران فعال است؛ قواعد نقش، سازمان
        و حوزه دسترسی در مرحله بعد تکمیل می‌شوند.
    </p>

    <?php if ($status !== ''): ?>
        <div class="admin-alert">
            <?= admin_h($status) ?>
        </div>
    <?php endif; ?>

    <form
        class="communication-form"
        method="post"
        action="/admin/messages/compose"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= admin_h(
                (new \IPKF\Security\Csrf())->token()
            ) ?>"
        >
        <label>
            <span>گیرنده</span>
            <select name="recipient_user_id" required>
                <option value="">انتخاب کنید</option>
                <?php foreach ($recipients as $recipient): ?>
                    <option value="<?= admin_h($recipient['id']) ?>">
                        <?= admin_h($recipient['title']) ?>
                        <?php if (
                            ($recipient['username'] ?? '') !== ''
                        ): ?>
                            — <?= admin_h($recipient['username']) ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>موضوع</span>
            <input
                type="text"
                name="subject"
                maxlength="300"
                required
            >
        </label>
        <label>
            <span>متن پیام</span>
            <textarea
                name="body"
                maxlength="20000"
                required
            ></textarea>
        </label>
        <div class="communication-actions">
            <button class="admin-button" type="submit">
                ارسال پیام
            </button>
            <a
                class="admin-button admin-button--soft"
                href="/admin/messages/inbox"
            >
                بازگشت
            </a>
        </div>
    </form>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
