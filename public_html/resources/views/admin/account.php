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

$user = $context['user'] ?? [];

ob_start();
?>
<div class="account-shell">
    <?php require __DIR__ . '/partials/account-nav.php'; ?>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h2>اطلاعات حساب</h2>
                <p>
                    شناسه‌های اصلی ورود و اطلاعات تماس ثبت‌شده
                </p>
            </div>
        </div>

        <div class="account-list">
            <div class="account-list__row">
                <span>شناسه فنی</span>
                <strong>
                    <?= admin_h(
                        \App\Support\AdminFormat::digits(
                            $user['id'] ?? '—'
                        )
                    ) ?>
                </strong>
                <span class="account-badge">سیستمی</span>
            </div>

            <div class="account-list__row">
                <span>نام کاربری</span>
                <strong dir="ltr">
                    <?= admin_h($user['username'] ?? '—') ?>
                </strong>
                <span class="account-badge account-badge--success">
                    فعال
                </span>
            </div>

            <div class="account-list__row">
                <span>ایمیل</span>
                <strong dir="ltr">
                    <?= admin_h($user['email'] ?? '—') ?>
                </strong>
                <span class="account-badge">
                    قابل ورود
                </span>
            </div>

            <div class="account-list__row">
                <span>شماره موبایل</span>
                <strong dir="ltr">
                    <?= admin_h($user['mobile'] ?? '—') ?>
                </strong>
                <span class="account-badge">
                    قابل ورود
                </span>
            </div>
        </div>

        <div
            class="account-notice account-notice--info"
            style="margin-top:.7rem"
        >
            تغییر ایمیل و موبایل باید با کد تأیید انجام شود.
            این جریان پس از اتصال Notification Core به ایمیل و پیامک
            در همین صفحه فعال خواهد شد.
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
