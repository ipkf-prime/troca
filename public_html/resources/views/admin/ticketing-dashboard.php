<?php

declare(strict_types=1);

/** @var array $context */
/** @var array $foundation */

ob_start();
?>

<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1>پشتیبانی و تیکتینگ</h1>

            <p>
                مرکز ثبت، پیگیری و مدیریت درخواست‌ها و
                تیکت‌های پشتیبانی
            </p>
        </div>
    </div>

    <div class="admin-grid admin-grid-3">

        <section class="admin-card">
            <div class="admin-card-body">
                <h2>تیکت‌های من</h2>

                <p>
                    مشاهده و پیگیری درخواست‌های ثبت‌شده
                    توسط کاربر.
                </p>

                <div class="admin-muted">
                    مخزن تیکت‌ها در مرحله عملیاتی بعدی
                    فعال می‌شود.
                </div>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-card-body">
                <h2>صف پشتیبانی</h2>

                <p>
                    دریافت، ارجاع و پاسخ‌گویی به
                    درخواست‌های پشتیبانی.
                </p>

                <div class="admin-muted">
                    موتور صف، تخصیص و SLA در مراحل بعدی
                    فعال خواهد شد.
                </div>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-card-body">
                <h2>وضعیت سرویس</h2>

                <p>
                    Shell مستقل، SSO و کنترل دسترسی
                    تیکتینگ آماده است.
                </p>

                <div class="admin-muted">
                    Runtime:
                    <?= htmlspecialchars(
                        (string) (
                            $foundation['runtime']
                            ?? 'unknown'
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            </div>
        </section>

    </div>
</div>

<?php
$content = ob_get_clean() ?: '';

require __DIR__ . '/layout.php';
