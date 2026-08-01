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
$message = $message ?? null;
$totpEnabled = (bool) ($page['totp_enabled'] ?? false);
$pendingTotp = $page['pending_totp'] ?? null;
$recoveryCodes = $page['recovery_codes'] ?? [];
$session = $page['session'] ?? [];
$loginHistory = is_array(
    $page['login_history'] ?? null
) ? $page['login_history'] : [];

ob_start();
?>
<style>
.login-history-list {
    border: 1px solid var(--admin-border);
    border-radius: .78rem;
    overflow: hidden;
}

.login-history-row {
    align-items: center;
    border-top: 1px solid var(--admin-border);
    display: grid;
    gap: .55rem;
    grid-template-columns:
        2rem
        minmax(8rem, .85fr)
        minmax(11rem, 1.35fr)
        minmax(7rem, .8fr)
        minmax(7rem, .8fr)
        auto;
    min-height: 3.4rem;
    padding: .55rem .65rem;
}

.login-history-row:first-child {
    border-top: 0;
}

.login-history-row--head {
    background: var(--admin-surface-muted);
    color: var(--admin-text-muted);
    font-size: .66rem;
    font-weight: 800;
    min-height: 2.7rem;
}

.login-history-index {
    color: var(--admin-text-muted);
    font-size: .7rem;
    text-align: center;
}

.login-history-main strong,
.login-history-main small {
    display: block;
}

.login-history-main strong {
    font-size: .74rem;
}

.login-history-main small {
    color: var(--admin-text-muted);
    direction: ltr;
    font-size: .63rem;
    margin-top: .1rem;
}

.login-history-cell {
    font-size: .7rem;
    overflow-wrap: anywhere;
}

.login-history-badges {
    display: flex;
    flex-wrap: wrap;
    gap: .3rem;
    justify-content: flex-end;
}

@media (max-width: 880px) {
    .login-history-row--head {
        display: none;
    }

    .login-history-row {
        grid-template-columns: 2rem minmax(0, 1fr) auto;
    }

    .login-history-cell {
        grid-column: 2;
    }

    .login-history-badges {
        grid-column: 2 / -1;
        justify-content: flex-start;
    }
}

@media (max-width: 560px) {
    .login-history-row {
        align-items: flex-start;
        grid-template-columns: 1.6rem minmax(0, 1fr);
    }

    .login-history-cell,
    .login-history-badges {
        grid-column: 2;
    }
}
</style>

<div class="account-shell">
    <?php require __DIR__ . '/partials/account-nav.php'; ?>

    <?php if (is_array($message)): ?>
        <div class="account-notice account-notice--<?= admin_h(
            $message['type'] ?? 'info'
        ) ?>">
            <?= admin_h($message['text'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if ($recoveryCodes !== []): ?>
        <section class="account-card">
            <div class="account-card__head">
                <div>
                    <h2>کدهای بازیابی جدید</h2>
                    <p>
                        هر کد فقط یک بار قابل استفاده است.
                        این صفحه تنها یک‌بار آن‌ها را نمایش می‌دهد.
                    </p>
                </div>
                <button
                    type="button"
                    class="admin-button admin-button--soft"
                    data-copy-recovery
                >
                    کپی همه
                </button>
            </div>

            <div class="recovery-codes" data-recovery-list>
                <?php foreach ($recoveryCodes as $code): ?>
                    <code class="recovery-code"><?= admin_h($code) ?></code>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h2>امنیت و ورود</h2>
                <p>
                    تأیید دومرحله‌ای، کدهای بازیابی و نشست جاری
                </p>
            </div>
            <span class="account-badge <?= $totpEnabled
                ? 'account-badge--success'
                : 'account-badge--danger' ?>">
                <?= $totpEnabled ? 'MFA فعال' : 'MFA غیرفعال' ?>
            </span>
        </div>

        <div class="security-grid">
            <article class="security-method">
                <div class="security-method__head">
                    <strong>برنامه Authenticator</strong>
                    <span class="account-badge <?= $totpEnabled
                        ? 'account-badge--success'
                        : '' ?>">
                        <?= $totpEnabled ? 'فعال' : 'غیرفعال' ?>
                    </span>
                </div>
                <p>
                    کد شش‌رقمی زمان‌دار از Google Authenticator،
                    Microsoft Authenticator یا برنامه مشابه.
                </p>
            </article>

            <article class="security-method">
                <div class="security-method__head">
                    <strong>کدهای بازیابی</strong>
                    <span class="account-badge">
                        <?= admin_h(
                            \App\Support\AdminFormat::digits(
                                $page['recovery_code_count'] ?? 0
                            )
                        ) ?>
                        کد
                    </span>
                </div>
                <p>
                    برای ورود اضطراری زمانی که به برنامه Authenticator
                    دسترسی ندارید.
                </p>
            </article>
        </div>
    </section>

    <?php if (is_array($pendingTotp)): ?>
        <section class="account-card">
            <div class="account-card__head">
                <div>
                    <h3>تأیید اتصال جدید</h3>
                    <p>
                        حساب را در برنامه Authenticator به‌صورت دستی
                        اضافه و سپس کد شش‌رقمی را وارد کنید.
                    </p>
                </div>
                <span class="account-badge">
                    اعتبار
                    <?= admin_h(
                        \App\Support\AdminFormat::digits(
                            (int) ceil(
                                ($pendingTotp['expires_in'] ?? 0) / 60
                            )
                        )
                    ) ?>
                    دقیقه
                </span>
            </div>

            <div class="setup-box">
                <div class="setup-secret">
                    <div>
                        <small class="admin-muted">کلید محرمانه</small>
                        <code data-copy-value="secret">
                            <?= admin_h($pendingTotp['secret'] ?? '') ?>
                        </code>
                    </div>
                    <button
                        type="button"
                        class="admin-button admin-button--soft"
                        data-copy-target="secret"
                    >
                        کپی کلید
                    </button>
                </div>

                <div class="setup-secret">
                    <div>
                        <small class="admin-muted">نشانی کامل اتصال</small>
                        <code data-copy-value="uri">
                            <?= admin_h(
                                $pendingTotp['otpauth_uri'] ?? ''
                            ) ?>
                        </code>
                    </div>
                    <button
                        type="button"
                        class="admin-button admin-button--soft"
                        data-copy-target="uri"
                    >
                        کپی نشانی
                    </button>
                </div>
            </div>

            <form
                method="post"
                action="/admin/security/mfa/totp/confirm"
                class="security-form security-form--2"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h(
                        (new \IPKF\Security\Csrf())->token()
                    ) ?>"
                >
                <label>
                    <span>کد شش‌رقمی برنامه</span>
                    <input
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        dir="ltr"
                        required
                        autofocus
                    >
                </label>
                <div class="account-actions" style="align-self:end">
                    <button type="submit">
                        تأیید و فعال‌سازی
                    </button>
                </div>
            </form>

            <form
                method="post"
                action="/admin/security/mfa/totp/cancel"
                style="margin-top:.5rem"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h(
                        (new \IPKF\Security\Csrf())->token()
                    ) ?>"
                >
                <button
                    type="submit"
                    class="admin-button admin-button--soft"
                >
                    لغو اتصال
                </button>
            </form>
        </section>
    <?php else: ?>
        <section class="account-card">
            <div class="account-card__head">
                <div>
                    <h3>
                        <?= $totpEnabled
                            ? 'تغییر برنامه Authenticator'
                            : 'فعال‌سازی تأیید دومرحله‌ای' ?>
                    </h3>
                    <p>
                        برای جلوگیری از تغییر غیرمجاز، رمز عبور فعلی
                        <?= $totpEnabled
                            ? 'و کد فعلی Authenticator'
                            : '' ?>
                        لازم است.
                    </p>
                </div>
            </div>

            <form
                method="post"
                action="/admin/security/mfa/totp/start"
                class="security-form security-form--2"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h(
                        (new \IPKF\Security\Csrf())->token()
                    ) ?>"
                >

                <label>
                    <span>رمز عبور فعلی</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <?php if ($totpEnabled): ?>
                    <label>
                        <span>کد فعلی Authenticator</span>
                        <input
                            name="current_totp"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            dir="ltr"
                            required
                        >
                    </label>
                <?php endif; ?>

                <div class="account-actions" style="grid-column:1/-1">
                    <button type="submit">
                        <?= $totpEnabled
                            ? 'ایجاد اتصال جدید'
                            : 'شروع فعال‌سازی' ?>
                    </button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($totpEnabled): ?>
        <section class="account-card">
            <div class="account-card__head">
                <div>
                    <h3>کدهای بازیابی و غیرفعال‌سازی</h3>
                    <p>
                        عملیات حساس فقط پس از تأیید رمز عبور و کد جاری
                        انجام می‌شود.
                    </p>
                </div>
            </div>

            <div class="security-grid">
                <form
                    method="post"
                    action="/admin/security/recovery/regenerate"
                    class="security-method security-form"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h(
                            (new \IPKF\Security\Csrf())->token()
                        ) ?>"
                    >
                    <strong>ساخت کدهای بازیابی جدید</strong>
                    <label>
                        <span>رمز عبور فعلی</span>
                        <input
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                    </label>
                    <label>
                        <span>کد Authenticator</span>
                        <input
                            name="totp_code"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            dir="ltr"
                            required
                        >
                    </label>
                    <button type="submit">
                        بازتولید کدها
                    </button>
                </form>

                <form
                    method="post"
                    action="/admin/security/mfa/totp/disable"
                    class="security-method security-form"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h(
                            (new \IPKF\Security\Csrf())->token()
                        ) ?>"
                    >
                    <strong>غیرفعال‌کردن MFA</strong>
                    <label>
                        <span>رمز عبور فعلی</span>
                        <input
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                    </label>
                    <label>
                        <span>کد Authenticator</span>
                        <input
                            name="totp_code"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            dir="ltr"
                            required
                        >
                    </label>
                    <button
                        type="submit"
                        class="admin-button admin-button--soft"
                    >
                        غیرفعال‌سازی
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h3>۱۰ ورود اخیر</h3>
                <p>
                    زمان ورود، دستگاه، IP، نقش زمان ورود و وضعیت MFA
                </p>
            </div>
            <span class="account-badge">
                <?= admin_h(
                    \App\Support\AdminFormat::digits(
                        count($loginHistory)
                    )
                ) ?>
                رکورد
            </span>
        </div>

        <?php if ($loginHistory === []): ?>
            <div class="account-notice account-notice--info">
                هنوز سابقه ورود تفصیلی ثبت نشده است.
                ثبت تاریخچه از این نسخه آغاز می‌شود.
            </div>
        <?php else: ?>
            <div class="login-history-list">
                <div class="login-history-row login-history-row--head">
                    <span>ردیف</span>
                    <span>زمان ورود</span>
                    <span>مرورگر و سیستم‌عامل</span>
                    <span>IP</span>
                    <span>نقش ورود</span>
                    <span>روش ورود</span>
                </div>

                <?php foreach (
                    $loginHistory as $index => $login
                ): ?>
                    <div class="login-history-row">
                        <span class="login-history-index">
                            <?= admin_h(
                                \App\Support\AdminFormat::digits(
                                    $index + 1
                                )
                            ) ?>
                        </span>

                        <div class="login-history-main">
                            <strong dir="ltr">
                                <?= admin_h(
                                    $login['logged_in_at']
                                    ?? '—'
                                ) ?>
                            </strong>
                            <?php if (!empty(
                                $login['is_legacy']
                            )): ?>
                                <small>
                                    سابقه قدیمی بدون جزئیات دستگاه
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="login-history-cell">
                            <?= admin_h(
                                ($login['browser_label'] ?? '')
                                    !== ''
                                    ? $login['browser_label']
                                    : '—'
                            ) ?>
                        </div>

                        <div
                            class="login-history-cell"
                            dir="ltr"
                        >
                            <?= admin_h(
                                ($login['ip_address'] ?? '')
                                    !== ''
                                    ? $login['ip_address']
                                    : '—'
                            ) ?>
                        </div>

                        <div class="login-history-cell">
                            <?= admin_h(
                                ($login['role_title'] ?? '')
                                    !== ''
                                    ? $login['role_title']
                                    : '—'
                            ) ?>
                            <?php if (
                                ($login['role_code'] ?? '') !== ''
                            ): ?>
                                <small
                                    class="admin-muted"
                                    dir="ltr"
                                >
                                    <?= admin_h(
                                        $login['role_code']
                                    ) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="login-history-badges">
                            <span class="account-badge">
                                <?= admin_h(
                                    $login[
                                        'auth_method_label'
                                    ] ?? 'ورود سامانه‌ای'
                                ) ?>
                            </span>
                            <span class="account-badge <?= !empty(
                                $login['mfa_verified']
                            ) ? 'account-badge--success' : '' ?>">
                                <?= !empty(
                                    $login['mfa_verified']
                                )
                                    ? 'MFA'
                                    : 'بدون MFA' ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h3>نشست جاری</h3>
                <p>
                    مشخصات مرورگری که اکنون با آن وارد سامانه هستید.
                </p>
            </div>
            <span class="account-badge account-badge--success">
                نشست فعال
            </span>
        </div>

        <div class="account-list">
            <div class="account-list__row">
                <span>مرورگر و سیستم‌عامل</span>
                <strong><?= admin_h($session['browser'] ?? '—') ?></strong>
                <span class="account-badge">همین دستگاه</span>
            </div>
            <div class="account-list__row">
                <span>نشانی IP</span>
                <strong dir="ltr">
                    <?= admin_h($session['ip'] ?? '—') ?>
                </strong>
                <span class="account-badge">
                    <?= admin_h($session['id_short'] ?? '') ?>
                </span>
            </div>
            <div class="account-list__row">
                <span>زمان ورود</span>
                <strong dir="ltr">
                    <?= admin_h($session['login_at'] ?? '—') ?>
                </strong>
                <span class="account-badge <?= !empty(
                    $session['mfa_verified']
                ) ? 'account-badge--success' : '' ?>">
                    <?= !empty($session['mfa_verified'])
                        ? 'MFA تأیید شده'
                        : 'ورود عادی' ?>
                </span>
            </div>
        </div>

        <div class="account-actions" style="margin-top:.7rem">
            <a class="admin-button" href="/admin/password">
                تغییر رمز عبور
            </a>
            <a
                class="admin-button admin-button--soft"
                href="/admin/logout"
            >
                خروج از حساب
            </a>
        </div>
    </section>
</div>

<script>
(() => {
    const copyText = async value => {
        if (!value) return;

        try {
            await navigator.clipboard.writeText(value.trim());
        } catch (_) {
            const area = document.createElement('textarea');
            area.value = value.trim();
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }
    };

    document.querySelectorAll('[data-copy-target]').forEach(button => {
        button.addEventListener('click', () => {
            const key = button.dataset.copyTarget;
            const target = document.querySelector(
                `[data-copy-value="${key}"]`
            );

            copyText(target?.textContent || '');
            button.textContent = 'کپی شد';
        });
    });

    document.querySelector('[data-copy-recovery]')
        ?.addEventListener('click', event => {
            const values = Array.from(
                document.querySelectorAll('.recovery-code')
            ).map(item => item.textContent.trim());

            copyText(values.join('\n'));
            event.currentTarget.textContent = 'کپی شد';
        });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
