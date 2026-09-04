<?php

declare(strict_types=1);

if (!function_exists('public_register_h')) {
    function public_register_h(mixed $value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$title = $title ?? 'ثبت‌نام';
$status = $status ?? '';

$errors = is_array($errors ?? null)
    ? $errors
    : [];

$old = is_array($old ?? null)
    ? $old
    : [];

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

$success =
    $status === 'success';
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
        <?= public_register_h($title) ?>
        |
        <?= public_register_h($brandName) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= public_register_h(
            $themeAssets['admin_css'] ?? ''
        ) ?>"
    >

    <style id="admin-theme-vars">
        <?= "\n"
            . $themeService->cssVariables()
            . "\n" ?>
    </style>

    <style>
        .register-page {
            min-height: 100vh;
            padding:
                clamp(24px, 5vh, 56px)
                18px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .register-card {
            width: min(100%, 720px);
            background: var(--admin-surface, #fff);
            border: 1px solid var(--admin-border, #dfe8e3);
            border-radius: 20px;
            box-shadow:
                0 18px 50px rgba(15, 80, 43, .10);
            padding:
                clamp(24px, 4vw, 38px);
        }

        .register-brand {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .register-brand__logo {
            width: 62px;
            height: 62px;
            flex: 0 0 62px;
            object-fit: contain;
            border-radius: 16px;
        }

        .register-brand__content {
            min-width: 0;
        }

        .register-brand__kicker {
            margin: 0 0 6px;
            color: var(--admin-primary, #0f7a3f);
            font-size: .9rem;
            font-weight: 700;
        }

        .register-brand__title {
            margin: 0;
            color: var(--admin-text, #1f2933);
            font-size: clamp(1.35rem, 3vw, 1.75rem);
            line-height: 1.5;
        }

        .register-intro {
            margin: 0 0 26px;
            color: var(--admin-text-muted, #64748b);
            line-height: 2;
            font-size: .96rem;
        }

        .register-alert {
            margin-bottom: 20px;
        }

        .register-form {
            display: grid;
            gap: 20px;
        }

        .register-row {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 18px;
            align-items: start;
        }

        .register-row--single {
            grid-template-columns: 1fr;
        }

        .register-field {
            display: grid;
            gap: 8px;
            min-width: 0;
        }

        .register-field__label {
            display: flex;
            align-items: center;
            gap: 6px;
            min-height: 26px;
            color: var(--admin-text, #1f2933);
            font-weight: 700;
            font-size: .94rem;
        }

        .register-field__optional {
            color: var(--admin-text-muted, #64748b);
            font-size: .82rem;
            font-weight: 500;
        }

        .register-field input {
            width: 100%;
            height: 54px;
            min-height: 54px;
            box-sizing: border-box;
            border-radius: 12px;
        }

        .register-field input:focus {
            outline: none;
        }

        .register-field__error {
            display: block;
            min-height: 20px;
            color: #b42318;
            font-size: .82rem;
            line-height: 1.6;
        }

        .register-password-help {
            margin:
                -6px
                2px
                0;
            color: var(--admin-text-muted, #64748b);
            font-size: .84rem;
            line-height: 1.8;
        }

        .register-actions {
            display: grid;
            gap: 14px;
            margin-top: 2px;
        }

        .register-submit {
            width: 100%;
            min-height: 54px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 800;
        }

        .register-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding-top: 4px;
            flex-wrap: wrap;
        }

        .register-links a {
            color: var(--admin-primary, #0f7a3f);
            font-size: .9rem;
            font-weight: 700;
            text-decoration: none;
        }

        .register-links a:hover {
            text-decoration: underline;
        }

        .register-honeypot {
            position: absolute !important;
            inset-inline-start: -10000px !important;
            width: 1px !important;
            height: 1px !important;
            overflow: hidden !important;
        }

        .register-success {
            display: grid;
            gap: 18px;
            text-align: center;
            padding: 14px 0 4px;
        }

        .register-success__mark {
            width: 72px;
            height: 72px;
            margin-inline: auto;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background:
                var(--admin-primary-soft, #e8f5ee);
            color:
                var(--admin-primary, #0f7a3f);
            font-size: 2.2rem;
            font-weight: 900;
        }

        .register-success h2 {
            margin: 0 0 8px;
        }

        .register-success p {
            margin: 0;
            line-height: 1.9;
        }

        .register-success__links {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .register-success__primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding-inline: 24px;
            border-radius: 11px;
            background:
                var(--admin-primary, #0f7a3f);
            color: #fff !important;
            text-decoration: none !important;
            font-weight: 800;
        }

        @media (max-width: 720px) {
            .register-page {
                padding:
                    16px
                    12px;
            }

            .register-card {
                padding:
                    24px
                    18px;
                border-radius: 16px;
            }

            .register-row {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .register-links {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .register-brand {
                align-items: flex-start;
            }
        }
    </style>
</head>

<body
    class="admin-auth-page"
    data-admin-theme="<?= public_register_h(
        $theme['canonical_preset']
        ?? $theme['active_preset']
        ?? 'official_emerald'
    ) ?>"
    data-admin-theme-source="system"
>
<main class="register-page">
    <section class="register-card">

        <div class="register-brand">
            <?php if ($logoUrl !== ''): ?>
                <img
                    class="register-brand__logo"
                    src="<?= public_register_h($logoUrl) ?>"
                    alt=""
                >
            <?php endif; ?>

            <div class="register-brand__content">
                <p class="register-brand__kicker">
                    <?= public_register_h(
                        $brandSubtitle
                    ) ?>
                </p>

                <h1 class="register-brand__title">
                    ثبت‌نام در
                    <?= public_register_h(
                        $brandName
                    ) ?>
                </h1>
            </div>
        </div>

        <?php if ($success): ?>

            <div class="register-success">
                <div
                    class="register-success__mark"
                    aria-hidden="true"
                >
                    ✓
                </div>

                <div>
                    <h2>
                        ثبت‌نام با موفقیت انجام شد
                    </h2>

                    <p class="admin-muted">
                        حساب شما با نقش پایه
                        «کاربر» ایجاد شد.
                        برای ادامه وارد سامانه شوید.
                    </p>
                </div>

                <div class="register-success__links">
                    <a
                        class="register-success__primary"
                        href="/admin/login"
                    >
                        ورود به سامانه
                    </a>

                    <a href="/">
                        بازگشت به صفحه اصلی
                    </a>
                </div>
            </div>

        <?php else: ?>

            <p class="register-intro">
                برای ایجاد حساب کاربری،
                اطلاعات اولیه زیر را وارد کنید.
                پس از این مرحله،
                شماره همراه شما با کد یک‌بارمصرف
                تأیید خواهد شد.
                اطلاعات هویتی و سازمانی موردنیاز
                برای نقش‌های بالاتر،
                هنگام ارتقای دسترسی تکمیل خواهد شد.
            </p>

            <?php if (
                isset($errors['general'])
            ): ?>
                <div
                    class="admin-alert register-alert"
                >
                    <?= public_register_h(
                        $errors['general']
                    ) ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="/register"
                class="register-form"
                autocomplete="on"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= public_register_h(
                        (
                            new \IPKF\Security\Csrf()
                        )->token()
                    ) ?>"
                >

                <div
                    class="register-honeypot"
                    aria-hidden="true"
                >
                    <label>
                        <span>وب‌سایت</span>
                        <input
                            name="website"
                            tabindex="-1"
                            autocomplete="off"
                        >
                    </label>
                </div>

                <div
                    class="register-row register-row--single"
                >
                    <label class="register-field">
                        <span
                            class="register-field__label"
                        >
                            نام و نام خانوادگی
                        </span>

                        <input
                            name="full_name"
                            value="<?= public_register_h(
                                $old['full_name']
                                ?? ''
                            ) ?>"
                            maxlength="150"
                            autocomplete="name"
                            autofocus
                            required
                        >

                        <small
                            class="register-field__error"
                        >
                            <?= public_register_h(
                                $errors['full_name']
                                ?? ''
                            ) ?>
                        </small>
                    </label>
                </div>

                <div class="register-row">
                    <label class="register-field">
                        <span
                            class="register-field__label"
                        >
                            شماره موبایل
                        </span>

                        <input
                            name="mobile"
                            value="<?= public_register_h(
                                $old['mobile']
                                ?? ''
                            ) ?>"
                            inputmode="tel"
                            autocomplete="tel"
                            placeholder="۰۹۱۲۱۲۳۴۵۶۷"
                            required
                        >

                        <small
                            class="register-field__error"
                        >
                            <?= public_register_h(
                                $errors['mobile']
                                ?? ''
                            ) ?>
                        </small>
                    </label>

                    <label class="register-field">
                        <span
                            class="register-field__label"
                        >
                            ایمیل
                            <small
                                class="register-field__optional"
                            >
                                اختیاری
                            </small>
                        </span>

                        <input
                            name="email"
                            type="email"
                            value="<?= public_register_h(
                                $old['email']
                                ?? ''
                            ) ?>"
                            autocomplete="email"
                            dir="ltr"
                        >

                        <small
                            class="register-field__error"
                        >
                            <?= public_register_h(
                                $errors['email']
                                ?? ''
                            ) ?>
                        </small>
                    </label>
                </div>

                <div class="register-row">
                    <label class="register-field">
                        <span
                            class="register-field__label"
                        >
                            کلمه عبور
                        </span>

                        <input
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            minlength="8"
                            maxlength="128"
                            required
                        >

                        <small
                            class="register-field__error"
                        >
                            <?= public_register_h(
                                $errors['password']
                                ?? ''
                            ) ?>
                        </small>
                    </label>

                    <label class="register-field">
                        <span
                            class="register-field__label"
                        >
                            تکرار کلمه عبور
                        </span>

                        <input
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            minlength="8"
                            maxlength="128"
                            required
                        >

                        <small
                            class="register-field__error"
                        >
                            <?= public_register_h(
                                $errors[
                                    'password_confirmation'
                                ]
                                ?? ''
                            ) ?>
                        </small>
                    </label>
                </div>

                <p class="register-password-help">
                    کلمه عبور باید حداقل
                    ۸ نویسه و شامل حداقل
                    یک حرف و یک عدد باشد.
                </p>

                <div class="register-actions">
                    <button
                        class="register-submit"
                        type="submit"
                    >
                        ایجاد حساب کاربری
                    </button>

                    <div class="register-links">
                        <a href="/admin/login">
                            قبلاً حساب دارید؟
                            ورود به سامانه
                        </a>

                        <a href="/">
                            بازگشت به صفحه اصلی
                        </a>
                    </div>
                </div>
            </form>

        <?php endif; ?>

    </section>
</main>

<script
    src="<?= public_register_h(
        $themeAssets['admin_js'] ?? ''
    ) ?>"
    defer
></script>
</body>
</html>
