<?php

$forgotPasswordUiText =
    static function (
        string $contentKey
    ): string {
        return
            \App\Services\UiContent\UiContentInlineGuide::bodyText(
                $contentKey,
                'core',
                'forgot-password'
            );
    };

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

$title =
    $title
    ?? $forgotPasswordUiText('core.forgot-password.ui.page-title');

$phase =
    in_array(
        ($phase ?? 'request'),
        ['request', 'verify'],
        true
    )
        ? (string) $phase
        : 'request';

$sent =
    (bool) ($sent ?? false);

$identifier =
    (string) ($identifier ?? '');

$errorStatus =
    trim(
        (string) (
            $error_status
            ?? ''
        )
    );

$errorMessage =
    match ($errorStatus) {
        'password_confirmation' =>
            $forgotPasswordUiText('core.forgot-password.error.password-confirmation'),
        'password_policy' =>
            $forgotPasswordUiText('core.forgot-password.error.password-policy'),
        'password_identity' =>
            $forgotPasswordUiText('core.forgot-password.error.password-identity'),
        'same_password' =>
            $forgotPasswordUiText('core.forgot-password.error.same-password'),
        'invalid_or_expired_code' =>
            $forgotPasswordUiText('core.forgot-password.error.invalid-or-expired-code'),
        'reset_failed' =>
            $forgotPasswordUiText('core.forgot-password.error.reset-failed'),
        default =>
            '',
    };

$themeService =
    new \App\Services\AdminThemeService();

$theme =
    $themeService->systemTheme();

$themeAssets =
    $themeService->assetUrls();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title><?= admin_h($title) ?> | IPKF</title>
    <link
        rel="stylesheet"
        href="<?= admin_h($themeAssets['admin_css']) ?>"
    >
    <style id="admin-theme-vars"><?= "\n" . $themeService->cssVariables() . "\n" ?></style>

        <style id="admin-auth-password-reveal-style">
        .admin-form .admin-auth-password-control {
            position: relative;
            width: 100%;
        }

        .admin-form .admin-auth-password-control > input {
            width: 100%;
            padding-left: 2.65rem;
            padding-right: .875rem;
        }

        .admin-form
        .admin-auth-password-control
        > button.admin-auth-password-toggle {
            position: absolute !important;
            left: .85rem !important;
            right: auto !important;
            top: 50% !important;

            width: 1.15rem !important;
            min-width: 1.15rem !important;
            max-width: 1.15rem !important;

            height: 1.15rem !important;
            min-height: 1.15rem !important;
            max-height: 1.15rem !important;

            padding: 0 !important;
            margin: 0 !important;

            transform: translateY(-50%) !important;

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

        .admin-form
        .admin-auth-password-control
        > button.admin-auth-password-toggle:hover,
        .admin-form
        .admin-auth-password-control
        > button.admin-auth-password-toggle:focus,
        .admin-form
        .admin-auth-password-control
        > button.admin-auth-password-toggle:active {
            border: 0 !important;
            background: transparent !important;
            background-color: transparent !important;
            background-image: none !important;
            box-shadow: none !important;
            color: #53635b !important;
        }

        .admin-form
        .admin-auth-password-control
        > button.admin-auth-password-toggle:focus-visible {
            outline: 1px solid currentColor !important;
            outline-offset: 3px;
            border-radius: 2px !important;
        }

        .admin-form
        .admin-auth-password-control
        > button.admin-auth-password-toggle
        > svg {
            display: block !important;

            width: 1rem !important;
            height: 1rem !important;

            min-width: 1rem !important;
            min-height: 1rem !important;

            overflow: visible;

            color: inherit !important;
            stroke: currentColor;

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

</head>
<body
    class="admin-auth-page"
    data-admin-theme="<?= admin_h($theme['canonical_preset'] ?? $theme['active_preset'] ?? 'official_emerald') ?>"
    data-admin-theme-source="system"
>
    <main class="admin-auth">
        <section class="admin-auth__panel">
            <div class="admin-auth__brand">
                <span class="admin-auth__mark">?</span>
                <div>
                    <p class="admin-kicker"><?= admin_h($forgotPasswordUiText('core.forgot-password.ui.kicker')) ?></p>
                    <h1><?= admin_h($forgotPasswordUiText('core.forgot-password.ui.heading')) ?></h1>
                </div>
            </div>

            <p class="admin-muted"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml(
                'core.forgot-password.guide.01',
                'core',
                'forgot-password'
            ) ?></p>

            <?php if ($sent): ?>
                <div class="admin-notice"><?= \App\Services\UiContent\UiContentInlineGuide::noticeBodyHtml(
                    'core.forgot-password.guide.02',
                    'core',
                    'forgot-password'
                ) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div
                    class="admin-notice"
                    role="alert"
                ><?= admin_h($errorMessage) ?></div>
            <?php endif; ?>

            <?php if ($phase === 'request'): ?>
                <form
                    method="post"
                    action="/admin/forgot-password"
                    class="admin-form"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>"
                    >
                    <label>
                        <span><?= admin_h($forgotPasswordUiText('core.forgot-password.ui.identifier-label')) ?></span>
                        <input
                            name="login"
                            value="<?= admin_h($identifier) ?>"
                            autocomplete="username"
                            data-autofocus="true"
                            autofocus
                            required
                        >
                    </label>
                    <button type="submit">
                        <?= admin_h($forgotPasswordUiText('core.forgot-password.ui.request-code-action')) ?>
                    </button>
                </form>
            <?php else: ?>
                <form
                    method="post"
                    action="/admin/forgot-password/confirm"
                    class="admin-form"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>"
                    >
                    <input
                        type="hidden"
                        name="login"
                        value="<?= admin_h($identifier) ?>"
                    >

                    <label>
                        <span><?= admin_h($forgotPasswordUiText('core.forgot-password.ui.code-label')) ?></span>
                        <input
                            name="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            required
                            data-autofocus="true"
                            autofocus
                        >
                    </label>

                    <label>
                        <span><?= admin_h($forgotPasswordUiText('core.forgot-password.ui.new-password-label')) ?></span>
                        <div class="admin-auth-password-control" data-password-control>
                            <input
                                type="password"
                                name="password"
                                autocomplete="new-password"
                                minlength="8"
                                maxlength="128"
                                required
                            >
                            <button
                                type="button"
                                class="admin-auth-password-toggle"
                                data-password-toggle
                                aria-pressed="false"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                    focusable="false"
                                >
                                    <path
                                        d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="2.8"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    />
                                    <path
                                        class="admin-auth-password-eye-slash"
                                        d="M4 4 20 20"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </button>
                        </div>
                    </label>

                    <label>
                        <span><?= admin_h($forgotPasswordUiText('core.forgot-password.ui.password-confirmation-label')) ?></span>
                        <div class="admin-auth-password-control" data-password-control>
                            <input
                                type="password"
                                name="password_confirmation"
                                autocomplete="new-password"
                                minlength="8"
                                maxlength="128"
                                required
                            >
                            <button
                                type="button"
                                class="admin-auth-password-toggle"
                                data-password-toggle
                                aria-pressed="false"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                    focusable="false"
                                >
                                    <path
                                        d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="2.8"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    />
                                    <path
                                        class="admin-auth-password-eye-slash"
                                        d="M4 4 20 20"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </button>
                        </div>
                    </label>

                    <p class="admin-muted"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml(
                        'core.password.guide.01',
                        'core',
                        'password'
                    ) ?></p>

                    <button type="submit">
                        <?= admin_h($forgotPasswordUiText('core.forgot-password.ui.submit-password-action')) ?>
                    </button>
                </form>

                <div class="admin-auth-links">
                    <a href="/admin/forgot-password">
                        <?= admin_h($forgotPasswordUiText('core.forgot-password.ui.request-new-code-action')) ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="admin-auth-links">
                <a href="/admin/login"><?= admin_h($forgotPasswordUiText('core.forgot-password.ui.login-link')) ?></a>
                <a href="/"><?= admin_h($forgotPasswordUiText('core.forgot-password.ui.home-link')) ?></a>
            </div>
        </section>
    </main>

    <script
        src="<?= admin_h($themeAssets['admin_js']) ?>"
        defer
    ></script>

    <script id="admin-auth-password-reveal-script">
    (() => {
        document
            .querySelectorAll(
                '[data-password-control]'
            )
            .forEach((control) => {
                const input =
                    control.querySelector(
                        'input'
                    );

                const toggle =
                    control.querySelector(
                        '[data-password-toggle]'
                    );

                if (
                    !(input instanceof HTMLInputElement)
                    || !(toggle instanceof HTMLButtonElement)
                ) {
                    return;
                }

                const fieldLabel =
                    control.closest(
                        'label'
                    );

                const fieldTitle =
                    fieldLabel
                        ? fieldLabel.querySelector(
                            ':scope > span'
                        )
                        : null;

                if (
                    fieldTitle
                    && fieldTitle.textContent.trim() !== ''
                ) {
                    toggle.setAttribute(
                        'aria-label',
                        fieldTitle.textContent.trim()
                    );
                }

                toggle.addEventListener(
                    'click',
                    () => {
                        const revealed =
                            input.type === 'password';

                        input.type =
                            revealed
                                ? 'text'
                                : 'password';

                        toggle.setAttribute(
                            'aria-pressed',
                            revealed
                                ? 'true'
                                : 'false'
                        );

                        input.focus({
                            preventScroll: true,
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
            });
    })();
    </script>

</body>
</html>
