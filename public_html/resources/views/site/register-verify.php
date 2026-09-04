<?php

declare(strict_types=1);

$h =
    static fn (
        mixed $value
    ): string =>
        htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES
            | ENT_SUBSTITUTE,
            'UTF-8'
        );

$title =
    $title
    ?? 'تأیید شماره همراه';

$status =
    trim(
        (string) (
            $status
            ?? ''
        )
    );

$state =
    is_array($state ?? null)
        ? $state
        : [];

$devToken =
    trim(
        (string) (
            $devToken
            ?? ''
        )
    );

$themeService =
    new \App\Services\AdminThemeService();

$theme =
    $themeService->systemTheme();

$themeAssets =
    $themeService->assetUrls();

$brandName =
    (string) (
        $theme['brand_name']
        ?? 'سامانه هوشمند تروکا'
    );

$brandSubtitle =
    (string) (
        $theme['brand_subtitle']
        ?? 'سامانه یکپارچه خدمات سازمانی'
    );

$logoUrl =
    (string) (
        $theme['logo_url']
        ?? ''
    );

$digits =
    static fn (
        mixed $value
    ): string =>
        \App\Support\AdminFormat::digits(
            $value
        );

$maskedMobile =
    (string) (
        $state['masked_mobile']
        ?? ''
    );

$resendWait =
    (int) (
        $state[
            'resend_wait_seconds'
        ]
        ?? 0
    );

$attemptExpired =
    ($state['status'] ?? '')
    === 'attempt_expired';

/*
 * PUBLIC_SMS_WINDOW_STATUS_V1
 *
 * Hours are read from the same central policy
 * used by delivery gates. No public hardcoded
 * provider window is maintained here.
 */
$smsWindowMessage =
    'ارسال پیامک در حال حاضر خارج از بازه زمانی مجاز است. '
    . 'پس از شروع بازه مجاز دوباره تلاش کنید.';

if ($status === 'sms_window_closed') {
    try {
        $smsPolicy =
            (
                new \App\Services\SmsDeliveryPolicyService()
            )->settings();

        $start =
            substr(
                trim(
                    (string) (
                        $smsPolicy['start_time']
                        ?? ''
                    )
                ),
                0,
                5
            );

        $end =
            substr(
                trim(
                    (string) (
                        $smsPolicy['end_time']
                        ?? ''
                    )
                ),
                0,
                5
            );

        $faDigits =
            static function (
                string $value
            ): string {
                return strtr(
                    $value,
                    [
                        '0' => '۰',
                        '1' => '۱',
                        '2' => '۲',
                        '3' => '۳',
                        '4' => '۴',
                        '5' => '۵',
                        '6' => '۶',
                        '7' => '۷',
                        '8' => '۸',
                        '9' => '۹',
                    ]
                );
            };

        if (
            empty(
                $smsPolicy['all_day']
            )
            && preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/D',
                $start
            ) === 1
            && preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/D',
                $end
            ) === 1
        ) {
            $smsWindowMessage =
                'ارسال پیامک فقط از ساعت '
                . $faDigits($start)
                . ' تا '
                . $faDigits($end)
                . ' امکان‌پذیر است.';
        }
    } catch (\Throwable) {
        /*
         * Keep the safe generic message if the
         * policy cannot be read.
         */
    }
}

$messages = [
    'sms_window_closed' =>
        $smsWindowMessage,
    'sent' =>
        'کد تأیید برای شماره همراه شما ارسال شد.',

    'resend_sent' =>
        'کد تأیید جدید ارسال شد.',

    'dev_token_exposed' =>
        'کد تأیید در محیط توسعه ایجاد شد.',

    'invalid_form' =>
        'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.',

    'invalid_code' =>
        'کد تأیید باید ۶ رقم باشد.',

    'invalid_or_expired_code' =>
        'کد واردشده صحیح نیست یا اعتبار آن پایان یافته است.',

    'attempt_locked' =>
        'تعداد تلاش‌های ناموفق به حد مجاز رسیده است. ثبت‌نام را از ابتدا انجام دهید.',

    'attempt_expired' =>
        'مهلت تکمیل این ثبت‌نام پایان یافته است. ثبت‌نام را از ابتدا انجام دهید.',

    'rate_limited' =>
        'تعداد درخواست‌های ارسال کد بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',

    'resend_cooldown' =>
        'برای ارسال دوباره کد کمی صبر کنید.',

    'not_configured' =>
        'ارسال پیامک تأیید در حال حاضر در دسترس نیست.',

    'delivery_failed' =>
        'ارسال پیامک تأیید انجام نشد. کمی بعد دوباره تلاش کنید.',

    'bale_unavailable' =>
        'تأیید شماره همراه از طریق بله در حال حاضر در دسترس نیست.',

    'bale_pending' =>
        'هنوز تأیید شماره همراه از بله دریافت نشده است. پس از اشتراک شماره همراه خود در بله، دوباره وضعیت را بررسی کنید.',

    'template_unavailable' =>
        'متن پیام تأیید در حال حاضر در دسترس نیست.',

    'challenge_not_created' =>
        'ایجاد کد تأیید انجام نشد. دوباره تلاش کنید.',

    'activation_failed' =>
        'فعال‌سازی حساب تکمیل نشد. دوباره تلاش کنید.',
];

/*
 * PUBLIC_REGISTRATION_VERIFY_UX_A3_2A
 *
 * Delivery acknowledgements are positive notices.
 * Validation / transport / policy failures remain danger notices.
 */
$successStatuses = [
    'sent',
    'resend_sent',
];

$isSuccessStatus = in_array(
    $status,
    $successStatuses,
    true
);


$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <title>
        <?= $h($title) ?>
        |
        <?= $h($brandName) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= $h(
            $themeAssets['admin_css']
            ?? ''
        ) ?>"
    >

    <style id="admin-theme-vars">
        <?= "\n"
            . $themeService
                ->cssVariables()
            . "\n" ?>
    </style>

    <style>
        .registration-otp-page {
            min-height: 100vh;
            padding:
                clamp(24px, 5vh, 56px)
                18px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .registration-otp-card {
            width: min(100%, 620px);
            padding:
                clamp(24px, 4vw, 38px);
            background:
                var(--admin-surface, #fff);
            border: 1px solid
                var(--admin-border, #dfe8e3);
            border-radius: 20px;
            box-shadow:
                0 18px 50px
                rgba(15, 80, 43, .10);
        }

        .registration-otp-brand {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 26px;
        }

        .registration-otp-logo {
            width: 62px;
            height: 62px;
            flex: 0 0 62px;
            object-fit: contain;
            border-radius: 16px;
        }

        .registration-otp-kicker {
            margin: 0 0 5px;
            color:
                var(--admin-primary, #0f7a3f);
            font-weight: 700;
            font-size: .88rem;
        }

        .registration-otp-title {
            margin: 0;
            font-size:
                clamp(1.3rem, 3vw, 1.7rem);
        }

        .registration-otp-intro {
            margin: 0 0 22px;
            line-height: 2;
            color:
                var(--admin-text-muted, #64748b);
        }

        .registration-otp-mobile {
            direction: ltr;
            unicode-bidi: plaintext;
            font-weight: 800;
            color:
                var(--admin-text, #1f2933);
        }

        .registration-otp-form {
            display: grid;
            gap: 16px;
        }

        .registration-otp-input {
            width: 100%;
            height: 58px;
            box-sizing: border-box;
            border-radius: 12px;
            text-align: center;
            direction: ltr;
            font-size: 1.35rem;
            letter-spacing: .18em;
            font-weight: 800;
        }

        .registration-otp-submit {
            width: 100%;
            min-height: 52px;
            border-radius: 12px;
            font-weight: 800;
        }

        .registration-otp-secondary {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid
                var(--admin-border, #e6ece8);
            display: grid;
            gap: 12px;
        }

        .registration-otp-secondary form {
            margin: 0;
        }

        .registration-otp-secondary
        .admin-btn {
            width: 100%;
            min-height: 46px;
        }

        .registration-otp-restart {
            text-align: center;
        }

        .registration-otp-restart a {
            color:
                var(--admin-primary, #0f7a3f);
            font-weight: 700;
            text-decoration: none;
        }

        .registration-otp-dev {
            margin-top: 16px;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            background:
                var(--admin-primary-soft, #e8f5ee);
        }

        .registration-otp-dev code {
            direction: ltr;
            font-size: 1.15rem;
            font-weight: 800;
        }

        @media (max-width: 640px) {
            .registration-otp-page {
                padding: 14px 10px;
            }

            .registration-otp-card {
                padding: 22px 16px;
                border-radius: 16px;
            }

            .registration-otp-brand {
                align-items: flex-start;
            }
        }
    </style>
</head>

<body
    class="admin-auth-page"
    data-admin-theme="<?= $h(
        $theme['canonical_preset']
        ?? $theme['active_preset']
        ?? 'official_emerald'
    ) ?>"
    data-admin-theme-source="system"
>
<main class="registration-otp-page">
    <section class="registration-otp-card">

        <div class="registration-otp-brand">
            <?php if ($logoUrl !== ''): ?>
                <img
                    class="registration-otp-logo"
                    src="<?= $h($logoUrl) ?>"
                    alt=""
                >
            <?php endif; ?>

            <div>
                <p class="registration-otp-kicker">
                    <?= $h(
                        $brandSubtitle
                    ) ?>
                </p>

                <h1 class="registration-otp-title">
                    تأیید شماره همراه
                </h1>
            </div>
        </div>

        <p class="registration-otp-intro">
            کد تأیید ارسال‌شده به شماره
            <span class="registration-otp-mobile">
                <?= $h(
                    $digits(
                        $maskedMobile
                    )
                ) ?>
            </span>
            را وارد کنید.
        </p>
        <p
            class="admin-muted"
            data-public-registration-sms-hint
        >
            اگر کد تأیید را دریافت نکردید،
            مطمئن شوید دریافت پیامک‌های تبلیغاتی
            برای شماره همراه شما مسدود نباشد.
        </p>


        <?php if (
            $status !== ''
            && isset(
                $messages[$status]
            )
        ): ?>
            <div
                class="<?= $isSuccessStatus
                    ? 'admin-alert admin-alert--success'
                    : 'admin-alert admin-alert--danger' ?>"
                role="<?= $isSuccessStatus
                    ? 'status'
                    : 'alert' ?>"
            >
                <?= $h(
                    $messages[$status]
                ) ?>
            </div>
        <?php endif; ?>

        <?php if (
            !$attemptExpired
            && ($state['ok'] ?? false)
        ): ?>
            <form
                class="registration-otp-form"
                method="post"
                action="/register/verify"
                autocomplete="off"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= $h(
                        $csrf
                    ) ?>"
                >

                <label>
                    <span class="admin-field-label">
                        کد تأیید
                    </span>

                    <input
                        class="registration-otp-input"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        minlength="6"
                        maxlength="6"
                        pattern="[0-9۰-۹٠-٩]{6}"
                        autofocus
                        required
                    >
                </label>

                <button
                    type="submit"
                    class="admin-btn admin-btn--primary registration-otp-submit"
                >
                    تأیید و فعال‌سازی حساب
                </button>
            </form>

            <!--
                PUBLIC_REGISTRATION_BALE_MOBILE_ATTESTATION_A3_2B1_V2

                Bale does not receive the SMS OTP.
                It verifies ownership of the same mobile through the
                user's own shared Contact.
            -->
            <div
                class="registration-otp-secondary"
                data-public-registration-bale-verify
            >
                <p class="admin-muted">
                    اگر پیامک تأیید را دریافت نمی‌کنید،
                    می‌توانید مالکیت همین شماره همراه را
                    از طریق بله تأیید کنید.
                </p>

                <form
                    method="post"
                    action="/register/verify/bale"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= $h(
                            $csrf
                        ) ?>"
                    >

                    <button
                        type="submit"
                        class="admin-btn"
                    >
                        تأیید شماره از طریق بله
                    </button>
                </form>

                <p class="admin-muted">
                    در بله گزینه «اشتراک شماره همراه من»
                    را انتخاب کنید.
                    شماره‌ای که بله ارسال می‌کند باید
                    دقیقاً با شماره این ثبت‌نام یکسان باشد.
                    پس از تأیید در بله، به این صفحه برگردید
                    و دکمه بررسی وضعیت را بزنید.
                </p>

                <form
                    method="post"
                    action="/register/verify/bale/confirm"
                    data-public-registration-bale-confirm
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= $h(
                            $csrf
                        ) ?>"
                    >

                    <button
                        type="submit"
                        class="admin-btn"
                    >
                        بررسی وضعیت تأیید بله
                    </button>
                </form>
            </div>

            <div class="registration-otp-secondary">
                <?php if (
                    $resendWait > 0
                ): ?>
                    <button
                        type="button"
                        class="admin-btn"
                        disabled
                    >
                        ارسال مجدد پس از
                        <?= $h(
                            $digits(
                                $resendWait
                            )
                        ) ?>
                        ثانیه
                    </button>
                <?php else: ?>
                    <form
                        method="post"
                        action="/register/verify/resend"
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= $h(
                                $csrf
                            ) ?>"
                        >

                        <button
                            type="submit"
                            class="admin-btn"
                        >
                            ارسال دوباره کد
                        </button>
                    </form>
                <?php endif; ?>

                <div class="registration-otp-restart">
                    <a href="/register?restart=1">
                        اصلاح اطلاعات و شروع دوباره
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="registration-otp-secondary">
                <a
                    class="admin-btn admin-btn--primary"
                    href="/register?restart=1"
                >
                    شروع دوباره ثبت‌نام
                </a>
            </div>
        <?php endif; ?>

        <?php if ($devToken !== ''): ?>
            <div class="registration-otp-dev">
                کد محیط توسعه:
                <code>
                    <?= $h(
                        $digits(
                            $devToken
                        )
                    ) ?>
                </code>
            </div>
        <?php endif; ?>

    </section>
</main>
</body>
</html>
