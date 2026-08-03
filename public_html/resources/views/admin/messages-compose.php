<?php

$recipients = $page['recipients'] ?? [];
$messageStatus = trim(
    (string) ($_GET['status'] ?? '')
);

ob_start();

require BASE_PATH
    . '/resources/views/admin/partials/communication-style.php';
?>
<div class="message-page-shell message-compose-shell">
<section class="communication-panel">
    <header class="communication-panel__head">
        <div>
            <h2>ارسال پیام داخلی</h2>
            <p class="communication-muted">
                یک گفتگوی داخلی جدید ایجاد کنید.
            </p>
        </div>
    </header>

    <?php if ($messageStatus !== ''): ?>
        <div class="admin-alert">
            <?= admin_h($messageStatus) ?>
        </div>
    <?php endif; ?>

    <form
        class="communication-form"
        method="post"
        action="/admin/messages/compose"
        enctype="multipart/form-data"
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
            <select
                name="recipient_user_id"
                required
            >
                <option value="">انتخاب کنید</option>

                <?php foreach ($recipients as $recipient): ?>
                    <option
                        value="<?= admin_h(
                            $recipient['id']
                        ) ?>"
                    >
                        <?= admin_h(
                            $recipient['title']
                        ) ?>

                        <?php if (
                            ($recipient['username'] ?? '')
                                !== ''
                        ): ?>
                            — <?= admin_h(
                                $recipient['username']
                            ) ?>
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

        <label class="communication-form__wide">
            <span>متن پیام</span>
            <textarea
                name="body"
                maxlength="20000"
            ></textarea>
        </label>

        <label class="communication-form__wide">
            <span>پیوست‌ها (حداکثر ۳ فایل؛ هر فایل ۱۰ و مجموع ۲۰ مگابایت)</span>
            <input type="file" name="attachments[]" multiple
                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt">
            <small class="communication-muted">PDF، Word، Excel، JPG، PNG یا TXT. ارسال پیام فقط با پیوست نیز مجاز است.</small>
        </label>

        <div
            class="communication-actions
                communication-form__wide"
        >
            <button
                class="admin-button"
                type="submit"
            >
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
</div>
<?php

$content = ob_get_clean();

require BASE_PATH
    . '/resources/views/admin/layout.php';
