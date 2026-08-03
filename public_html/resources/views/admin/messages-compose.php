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

        <fieldset class="communication-recipient-picker communication-form__wide">
            <legend>گیرندگان</legend>
            <input type="search" data-recipient-search placeholder="جست‌وجو بر اساس نام، نام کاربری، گروه یا شهر">
            <div class="communication-recipient-results" data-recipient-results>
                <?php foreach ($recipients as $recipient): ?>
                    <?php $searchText = implode(' ', [$recipient['title'] ?? '', $recipient['username'] ?? '', $recipient['group_title'] ?? '', $recipient['city_title'] ?? '']); ?>
                    <label data-recipient-item data-search="<?= admin_h(mb_strtolower($searchText, 'UTF-8')) ?>">
                        <input type="checkbox" name="recipient_user_ids[]" value="<?= admin_h($recipient['id']) ?>">
                        <span><strong><?= admin_h($recipient['title']) ?></strong><small><?= admin_h(trim(($recipient['group_title'] ?? '') . ' ' . ($recipient['city_title'] ?? ''))) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small class="communication-muted"><span data-recipient-count>۰</span> گیرنده انتخاب شده است.</small>
        </fieldset>

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
<script>
(function () {
    var search = document.querySelector('[data-recipient-search]');
    var items = Array.from(document.querySelectorAll('[data-recipient-item]'));
    var count = document.querySelector('[data-recipient-count]');
    function refresh() {
        var query = (search.value || '').trim().toLocaleLowerCase('fa');
        items.forEach(function (item) { item.hidden = query !== '' && !item.dataset.search.includes(query); });
        count.textContent = String(items.filter(function (item) { return item.querySelector('input').checked; }).length).replace(/\d/g, function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];});
    }
    search.addEventListener('input', refresh);
    items.forEach(function (item) { item.querySelector('input').addEventListener('change', refresh); });
    refresh();
})();
</script>
<?php

$content = ob_get_clean();

require BASE_PATH
    . '/resources/views/admin/layout.php';
