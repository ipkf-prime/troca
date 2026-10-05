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

<style id="admin-account-password-reveal-style">
.admin-auth-password-control {
    position: relative;
    width: 100%;
}

.admin-auth-password-control > input {
    width: 100%;
    padding-left: 2.65rem;
}

.admin-auth-password-control
> button.admin-auth-password-toggle {
    position: absolute !important;
    left: .85rem !important;
    right: auto !important;
    top: 50% !important;
    transform: translateY(-50%) !important;

    width: 1.15rem !important;
    min-width: 1.15rem !important;
    max-width: 1.15rem !important;

    height: 1.15rem !important;
    min-height: 1.15rem !important;
    max-height: 1.15rem !important;

    padding: 0 !important;
    margin: 0 !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border: 0 !important;
    border-radius: 0 !important;

    background: transparent !important;
    background-color: transparent !important;
    background-image: none !important;

    box-shadow: none !important;

    color: #74827b !important;

    opacity: 1 !important;
    line-height: 1 !important;

    cursor: pointer !important;

    appearance: none !important;
    -webkit-appearance: none !important;

    z-index: 2;
}

.admin-auth-password-control
> button.admin-auth-password-toggle:hover,
.admin-auth-password-control
> button.admin-auth-password-toggle:focus,
.admin-auth-password-control
> button.admin-auth-password-toggle:active {
    border: 0 !important;

    background: transparent !important;
    background-color: transparent !important;
    background-image: none !important;

    box-shadow: none !important;

    color: #53635b !important;
}

.admin-auth-password-control
> button.admin-auth-password-toggle:focus-visible {
    outline: 1px solid currentColor !important;
    outline-offset: 3px;
    border-radius: 2px !important;
}

.admin-auth-password-control
> button.admin-auth-password-toggle
> svg {
    display: block !important;

    width: 1rem !important;
    min-width: 1rem !important;

    height: 1rem !important;
    min-height: 1rem !important;

    overflow: visible;
    color: inherit !important;

    pointer-events: none;
}

.admin-auth-password-toggle[
    aria-pressed="false"
] .admin-auth-password-eye-slash {
    display: none;
}

.admin-auth-password-toggle[
    aria-pressed="true"
] .admin-auth-password-eye-slash {
    display: block;
}
</style>

<script id="admin-account-password-reveal-script">
(() => {
    const SVG_NS =
        'http://www.w3.org/2000/svg';

    const createSvgElement =
        (name, attributes = {}) => {
            const element =
                document.createElementNS(
                    SVG_NS,
                    name
                );

            Object.entries(
                attributes
            ).forEach(
                ([key, value]) => {
                    element.setAttribute(
                        key,
                        value
                    );
                }
            );

            return element;
        };


    const createEye =
        () => {
            const svg =
                createSvgElement(
                    'svg',
                    {
                        viewBox:
                            '0 0 24 24',

                        'aria-hidden':
                            'true',

                        focusable:
                            'false',
                    }
                );


            const outer =
                createSvgElement(
                    'path',
                    {
                        d:
                            'M2.5 12s3.5-6 9.5-6 '
                            + '9.5 6 9.5 6-3.5 6-9.5 6 '
                            + '-9.5-6-9.5-6Z',

                        fill:
                            'none',

                        stroke:
                            'currentColor',

                        'stroke-width':
                            '1.8',

                        'stroke-linecap':
                            'round',

                        'stroke-linejoin':
                            'round',
                    }
                );


            const pupil =
                createSvgElement(
                    'circle',
                    {
                        cx:
                            '12',

                        cy:
                            '12',

                        r:
                            '2.8',

                        fill:
                            'none',

                        stroke:
                            'currentColor',

                        'stroke-width':
                            '1.8',
                    }
                );


            const slash =
                createSvgElement(
                    'path',
                    {
                        d:
                            'M4 4 20 20',

                        fill:
                            'none',

                        stroke:
                            'currentColor',

                        'stroke-width':
                            '1.8',

                        'stroke-linecap':
                            'round',

                        class:
                            'admin-auth-password-eye-slash',
                    }
                );


            svg.append(
                outer,
                pupil,
                slash
            );


            return svg;
        };


    const initializePasswordToggles =
        () => {
            const scope =
                document.querySelector(
                    '.account-shell'
                );


            if (!scope) {
                return;
            }


            scope
                .querySelectorAll(
                    'input[type="password"]'
                )
                .forEach(
                    (input) => {
                        if (
                            input.closest(
                                '[data-password-control]'
                            )
                        ) {
                            return;
                        }


                        const parent =
                            input.parentNode;


                        if (!parent) {
                            return;
                        }


                        const wrapper =
                            document.createElement(
                                'div'
                            );


                        wrapper.className =
                            'admin-auth-password-control';


                        wrapper.setAttribute(
                            'data-password-control',
                            ''
                        );


                        parent.insertBefore(
                            wrapper,
                            input
                        );


                        wrapper.appendChild(
                            input
                        );


                        const toggle =
                            document.createElement(
                                'button'
                            );


                        toggle.type =
                            'button';


                        toggle.className =
                            'admin-auth-password-toggle';


                        toggle.setAttribute(
                            'data-password-toggle',
                            ''
                        );


                        toggle.setAttribute(
                            'aria-pressed',
                            'false'
                        );


                        const label =
                            wrapper.closest(
                                'label'
                            );


                        if (label) {
                            const titleNode =
                                label.querySelector(
                                    'span,strong'
                                );


                            if (
                                titleNode
                                && titleNode
                                    .textContent
                                    .trim() !== ''
                            ) {
                                toggle.setAttribute(
                                    'aria-label',
                                    titleNode
                                        .textContent
                                        .trim()
                                );
                            }
                        }


                        toggle.appendChild(
                            createEye()
                        );


                        toggle.addEventListener(
                            'click',
                            () => {
                                const reveal =
                                    input.type
                                    === 'password';


                                input.type =
                                    reveal
                                        ? 'text'
                                        : 'password';


                                toggle.setAttribute(
                                    'aria-pressed',
                                    reveal
                                        ? 'true'
                                        : 'false'
                                );


                                input.focus({
                                    preventScroll:
                                        true,
                                });


                                const end =
                                    input.value.length;


                                try {
                                    input.setSelectionRange(
                                        end,
                                        end
                                    );
                                } catch (_) {
                                }
                            }
                        );


                        wrapper.appendChild(
                            toggle
                        );
                    }
                );
        };


    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            initializePasswordToggles,
            {
                once:
                    true,
            }
        );
    } else {
        initializePasswordToggles();
    }
})();
</script>

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
