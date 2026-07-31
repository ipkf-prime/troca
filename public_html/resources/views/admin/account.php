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

$page = $page ?? [];
$pending = is_array($pending ?? null)
    ? $pending
    : [];
$status = (string) ($status ?? '');
$devOtp = (string) ($devOtp ?? '');

$statusMessage = match ($status) {
    'change_otp_sent' =>
        'کد تأیید به شناسه جدید ارسال شد.',
    'identity_changed' =>
        'شناسه جدید تأیید و اعمال شد.',
    'verification_otp_sent' =>
        'کد تأیید ارسال شد.',
    'identity_verified' =>
        'شناسه حساب با موفقیت تأیید شد.',
    'invalid_credentials' =>
        'رمز عبور فعلی صحیح نیست.',
    'invalid_identity_value' =>
        'مقدار واردشده معتبر نیست.',
    'value_not_available' =>
        'این ایمیل یا موبایل قبلاً استفاده شده است.',
    'value_unchanged' =>
        'مقدار جدید با مقدار فعلی یکسان است.',
    'change_request_already_pending' =>
        'برای این مقدار یک درخواست فعال وجود دارد.',
    'not_configured' =>
        'سرویس ارسال OTP هنوز پیکربندی نشده است.',
    'delivery_failed' =>
        'ارسال کد تأیید ناموفق بود.',
    'rate_limited' =>
        'تعداد درخواست‌ها زیاد است؛ چند دقیقه بعد تلاش کنید.',
    'invalid_or_expired_code',
    'invalid_code' =>
        'کد تأیید نامعتبر یا منقضی شده است.',
    default => '',
};

ob_start();
?>
<style>
.identity-account-grid {
    display: grid;
    gap: .7rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.identity-card {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .78rem;
    display: grid;
    gap: .65rem;
    padding: .75rem;
}

.identity-card__head {
    align-items: center;
    display: flex;
    gap: .55rem;
    justify-content: space-between;
}

.identity-card__head strong {
    font-size: .82rem;
}

.identity-form {
    display: grid;
    gap: .55rem;
}

.identity-form label {
    display: grid;
    gap: .25rem;
    font-size: .7rem;
    font-weight: 800;
}

.identity-form input {
    min-height: 2.55rem;
}

.identity-form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
}

.identity-pending {
    background: var(--admin-primary-soft);
    border: 1px solid color-mix(
        in srgb,
        var(--admin-primary) 25%,
        var(--admin-border)
    );
    border-radius: .78rem;
    padding: .75rem;
}

@media (max-width: 760px) {
    .identity-account-grid {
        grid-template-columns: 1fr;
    }

    .identity-form-actions,
    .identity-form-actions .admin-button {
        width: 100%;
    }
}
</style>

<div class="account-shell">
    <?php require __DIR__ . '/partials/account-nav.php'; ?>

    <?php if ($statusMessage !== ''): ?>
        <div class="admin-alert">
            <?= admin_h($statusMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($devOtp !== ''): ?>
        <div class="admin-alert admin-alert--success">
            کد توسعه:
            <strong dir="ltr"><?= admin_h($devOtp) ?></strong>
        </div>
    <?php endif; ?>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h2>ایمیل و موبایل حساب</h2>
                <p>
                    تغییر شناسه‌ها فقط بعد از تأیید OTP نهایی می‌شود.
                </p>
            </div>
        </div>

        <div class="identity-account-grid">
            <?php foreach ([
                'email' => [
                    'title' => 'ایمیل',
                    'value' => $page['email'] ?? '',
                    'verified' => $page['email_verified'] ?? false,
                    'type' => 'email',
                    'placeholder' => 'name@example.com',
                ],
                'mobile' => [
                    'title' => 'شماره موبایل',
                    'value' => $page['mobile'] ?? '',
                    'verified' => $page['mobile_verified'] ?? false,
                    'type' => 'tel',
                    'placeholder' => '09123456789',
                ],
            ] as $field => $definition): ?>
                <article class="identity-card">
                    <div class="identity-card__head">
                        <strong><?= admin_h($definition['title']) ?></strong>
                        <span class="account-badge <?= $definition['verified']
                            ? 'account-badge--success'
                            : 'account-badge--danger' ?>">
                            <?= $definition['verified']
                                ? 'تأیید شده'
                                : 'تأیید نشده' ?>
                        </span>
                    </div>

                    <div dir="ltr">
                        <?= admin_h(
                            $definition['value'] !== ''
                                ? $definition['value']
                                : '—'
                        ) ?>
                    </div>

                    <form
                        class="identity-form"
                        method="post"
                        action="/admin/account/identity/request"
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= admin_h(
                                (new \IPKF\Security\Csrf())->token()
                            ) ?>"
                        >
                        <input
                            type="hidden"
                            name="field"
                            value="<?= admin_h($field) ?>"
                        >
                        <label>
                            <?= admin_h($definition['title']) ?> جدید
                            <input
                                type="<?= admin_h($definition['type']) ?>"
                                name="value"
                                placeholder="<?= admin_h(
                                    $definition['placeholder']
                                ) ?>"
                                dir="ltr"
                                required
                            >
                        </label>
                        <label>
                            رمز عبور فعلی
                            <input
                                type="password"
                                name="password"
                                autocomplete="current-password"
                                required
                            >
                        </label>
                        <button class="admin-button" type="submit">
                            ارسال کد تغییر
                        </button>
                    </form>

                    <?php if (
                        !$definition['verified']
                        && $definition['value'] !== ''
                    ): ?>
                        <form
                            class="identity-form"
                            method="post"
                            action="/admin/account/verification/request"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value="<?= admin_h(
                                    (new \IPKF\Security\Csrf())->token()
                                ) ?>"
                            >
                            <input
                                type="hidden"
                                name="field"
                                value="<?= admin_h($field) ?>"
                            >
                            <button
                                class="admin-button admin-button--soft"
                                type="submit"
                            >
                                ارسال مجدد کد تأیید
                            </button>
                        </form>

                        <form
                            class="identity-form"
                            method="post"
                            action="/admin/account/verification/confirm"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value="<?= admin_h(
                                    (new \IPKF\Security\Csrf())->token()
                                ) ?>"
                            >
                            <input
                                type="hidden"
                                name="field"
                                value="<?= admin_h($field) ?>"
                            >
                            <label>
                                کد OTP
                                <input
                                    name="code"
                                    inputmode="numeric"
                                    maxlength="6"
                                    dir="ltr"
                                    required
                                >
                            </label>
                            <button class="admin-button" type="submit">
                                تأیید شناسه فعلی
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($pending !== []): ?>
        <section class="account-card">
            <div class="identity-pending">
                <strong>تأیید تغییر در انتظار است</strong>
                <p>
                    کد ارسال‌شده به
                    <?= admin_h($pending['masked_destination'] ?? '') ?>
                    را وارد کنید.
                </p>

                <form
                    class="identity-form"
                    method="post"
                    action="/admin/account/identity/confirm"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h(
                            (new \IPKF\Security\Csrf())->token()
                        ) ?>"
                    >
                    <input
                        type="hidden"
                        name="request_id"
                        value="<?= (int) (
                            $pending['request_id'] ?? 0
                        ) ?>"
                    >
                    <label>
                        کد OTP
                        <input
                            name="code"
                            inputmode="numeric"
                            maxlength="6"
                            dir="ltr"
                            required
                        >
                    </label>
                    <button class="admin-button" type="submit">
                        تأیید و اعمال تغییر
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
