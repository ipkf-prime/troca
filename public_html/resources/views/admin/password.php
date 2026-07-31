<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$status = $status ?? '';
$errors = $errors ?? [];

ob_start();
?>
<div class="account-shell">
    <?php require __DIR__ . '/partials/account-nav.php'; ?>

    <?php if ($status === 'updated'): ?>
        <div class="account-notice account-notice--success">
            رمز عبور با موفقیت تغییر کرد.
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="account-notice account-notice--danger">
            <strong>تغییر رمز عبور انجام نشد.</strong>
            <ul style="margin:.35rem 0 0">
                <?php foreach ($errors as $error): ?>
                    <li><?= admin_h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h2>تغییر رمز عبور</h2>
                <p>
                    پس از ذخیره، شناسه نشست جاری برای امنیت بیشتر
                    نوسازی می‌شود.
                </p>
            </div>
            <a
                class="admin-button admin-button--soft"
                href="/admin/security"
            >
                بازگشت به امنیت
            </a>
        </div>

        <div class="password-layout">
            <form
                method="post"
                action="/admin/password"
                class="password-panel"
                data-password-form
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h(
                        (new \IPKF\Security\Csrf())->token()
                    ) ?>"
                >

                <label class="password-field">
                    <span>رمز عبور فعلی</span>
                    <input
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                        autofocus
                    >
                    <button
                        class="password-toggle"
                        type="button"
                        data-toggle-password
                    >
                        نمایش
                    </button>
                </label>

                <label class="password-field">
                    <span>رمز عبور جدید</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        minlength="12"
                        required
                        data-new-password
                    >
                    <button
                        class="password-toggle"
                        type="button"
                        data-toggle-password
                    >
                        نمایش
                    </button>
                </label>

                <div class="password-meter" aria-hidden="true">
                    <span data-password-meter></span>
                </div>

                <label class="password-field">
                    <span>تکرار رمز عبور جدید</span>
                    <input
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        minlength="12"
                        required
                    >
                    <button
                        class="password-toggle"
                        type="button"
                        data-toggle-password
                    >
                        نمایش
                    </button>
                </label>

                <div class="account-actions">
                    <button type="submit">
                        ذخیره رمز عبور
                    </button>
                    <a
                        class="admin-button admin-button--soft"
                        href="/admin/security"
                    >
                        انصراف
                    </a>
                </div>
            </form>

            <aside class="security-method">
                <strong>الزامات رمز امن</strong>
                <ul class="password-rules" style="margin-top:.6rem">
                    <li data-rule="length">
                        حداقل ۱۲ کاراکتر
                    </li>
                    <li data-rule="classes">
                        دست‌کم سه گروه از حروف بزرگ، حروف کوچک،
                        عدد و نماد
                    </li>
                    <li data-rule="identity">
                        متفاوت از نام کاربری و ایمیل
                    </li>
                    <li>
                        متفاوت از رمز عبور فعلی
                    </li>
                </ul>
            </aside>
        </div>
    </section>
</div>

<script>
(() => {
    document.querySelectorAll('[data-toggle-password]')
        .forEach(button => {
            button.addEventListener('click', () => {
                const input = button
                    .closest('.password-field')
                    ?.querySelector('input');

                if (!input) return;

                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                button.textContent = visible ? 'نمایش' : 'پنهان';
            });
        });

    const password = document.querySelector('[data-new-password]');
    const meter = document.querySelector('[data-password-meter]');
    const rules = {
        length: document.querySelector('[data-rule="length"]'),
        classes: document.querySelector('[data-rule="classes"]'),
    };

    const refresh = () => {
        const value = password?.value || '';
        const classes = [
            /[a-z]/.test(value),
            /[A-Z]/.test(value),
            /[0-9]/.test(value),
            /[^a-zA-Z0-9]/.test(value),
        ].filter(Boolean).length;

        const lengthOk = value.length >= 12;
        const classesOk = classes >= 3;
        const score = Math.min(
            100,
            (lengthOk ? 45 : value.length * 3)
                + classes * 13
        );

        if (meter) {
            meter.style.width = `${score}%`;
        }

        rules.length?.classList.toggle('is-valid', lengthOk);
        rules.classes?.classList.toggle('is-valid', classesOk);
    };

    password?.addEventListener('input', refresh);
    refresh();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
