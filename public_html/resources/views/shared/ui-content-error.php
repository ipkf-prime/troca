<?php

declare(strict_types=1);


$model =
    is_array($model ?? null)
        ? $model
        : [];


$page =
    is_array(
        $model['page']
        ?? null
    )
        ? $model['page']
        : [];


$branding =
    is_array(
        $model['branding']
        ?? null
    )
        ? $model['branding']
        : [];


$actions =
    is_array(
        $model['actions']
        ?? null
    )
        ? $model['actions']
        : [];


$status =
    max(
        400,
        min(
            599,
            (int) (
                $model['status']
                ?? 500
            )
        )
    );


$title =
    trim(
        (string) (
            $page['title']
            ?? 'درخواست قابل پردازش نیست'
        )
    );


$body =
    trim(
        (string) (
            $page['body']
            ?? ''
        )
    );


$brandTitle =
    trim(
        (string) (
            $branding[
                'brand_title'
            ]
            ?? 'سامانه'
        )
    );


$platformBrand =
    trim(
        (string) (
            $branding[
                'platform_brand_name'
            ]
            ?? 'سامانه'
        )
    );


$brandSubtitle =
    trim(
        (string) (
            $branding[
                'brand_subtitle'
            ]
            ?? ''
        )
    );


$breadcrumbs =
    is_array(
        $branding['breadcrumbs']
        ?? null
    )
        ? $branding['breadcrumbs']
        : [];


$logoEnabled =
    ($branding[
        'logo_enabled'
    ] ?? false)
    === true;


$logoUrl =
    trim(
        (string) (
            $branding['logo_url']
            ?? ''
        )
    );


$logoAlt =
    trim(
        (string) (
            $branding['logo_alt']
            ?? $brandTitle
        )
    );


$adminCss =
    (string) (
        $branding[
            'admin_css_url'
        ]
        ?? '/assets/admin/css/admin.css'
    );


$font =
    (string) (
        $branding[
            'font_family'
        ]
        ?? '"Vazirmatn", "Tahoma", sans-serif'
    );


$accent =
    (string) (
        $branding['accent']
        ?? '#1f6f4a'
    );


$accentSoft =
    (string) (
        $branding[
            'accent_soft'
        ]
        ?? '#e4f0e8'
    );


$background =
    (string) (
        $branding[
            'background'
        ]
        ?? '#f4f8f5'
    );


$surface =
    (string) (
        $branding['surface']
        ?? '#ffffff'
    );


$text =
    (string) (
        $branding['text']
        ?? '#16241d'
    );


$muted =
    (string) (
        $branding['muted']
        ?? '#5f7169'
    );


$border =
    (string) (
        $branding['border']
        ?? '#cadfd2'
    );


$iconCode =
    strtolower(
        trim(
            (string) (
                $page['icon_code']
                ?? ''
            )
        )
    );


$icon =
    match ($iconCode) {

        'file-x' =>
            '<svg viewBox="0 0 24 24">'
            . '<path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/>'
            . '<path d="M14 2v5h5"/>'
            . '<path d="m9.5 12.5 5 5"/>'
            . '<path d="m14.5 12.5-5 5"/>'
            . '</svg>',

        default =>
            '<svg viewBox="0 0 24 24">'
            . '<circle cx="12" cy="12" r="9"/>'
            . '<path d="M12 7.5v5.5"/>'
            . '<path d="M12 16.5h.01"/>'
            . '</svg>',
    };


$h =
    static fn (
        mixed $value
    ): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES
            | ENT_SUBSTITUTE,
            'UTF-8'
        );
?>
<!doctype html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">

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
        <?= $h($brandTitle) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= $h($adminCss) ?>"
        data-ui-platform-font-source="admin-css"
    >

    <style>
        :root {
            --error-font: <?= $font ?>;
            --error-accent: <?= $accent ?>;
            --error-accent-soft: <?= $accentSoft ?>;
            --error-bg: <?= $background ?>;
            --error-surface: <?= $surface ?>;
            --error-text: <?= $text ?>;
            --error-muted: <?= $muted ?>;
            --error-border: <?= $border ?>;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            direction: rtl;
            color: var(--error-text);
            font-family: var(--error-font);

            background:
                radial-gradient(
                    circle at 9% 12%,
                    color-mix(
                        in srgb,
                        var(--error-accent) 8%,
                        transparent
                    ),
                    transparent 31%
                ),
                linear-gradient(
                    145deg,
                    #fbfcfc,
                    var(--error-bg)
                );
        }

        .ui-error,
        .ui-error * {
            box-sizing: border-box;
        }

        .ui-error {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 18px;
        }

        .ui-error__wrap {
            width: min(100%, 810px);
        }

        .ui-error__brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin: 0 5px 14px;
        }

        .ui-error__identity {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
        }

        .ui-error__logo {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            display: grid;
            place-items: center;
            padding: 5px;
            overflow: hidden;
            border-radius: 15px;
            border: 1px solid var(--error-border);
            background: #fff;
            box-shadow: 0 6px 20px rgba(15,50,35,.06);
        }

        .ui-error__logo img {
            display: block;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .ui-error__brand-title {
            font-size: 14px;
            font-weight: 800;
            line-height: 1.7;
        }

        .ui-error__subtitle,
        .ui-error__platform {
            color: var(--error-muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .ui-error__card {
            position: relative;
            overflow: hidden;
            padding: clamp(28px, 6vw, 52px);
            border: 1px solid var(--error-border);
            border-radius: 25px;
            background: rgba(255,255,255,.96);
            box-shadow: 0 20px 60px rgba(20,55,40,.09);
        }

        .ui-error__card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
            background: var(--error-accent);
        }

        .ui-error__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 27px;
        }

        .ui-error__icon {
            width: 62px;
            height: 62px;
            display: grid;
            place-items: center;
            border-radius: 19px;
            color: var(--error-accent);
            background: var(--error-accent-soft);
        }

        .ui-error__icon svg {
            width: 29px;
            height: 29px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .ui-error__status {
            direction: ltr;
            color: var(--error-accent);
            font-family: var(--error-font);
            font-size: clamp(54px, 10vw, 82px);
            font-weight: 850;
            line-height: .9;
            opacity: .12;
        }

        .ui-error__title {
            margin: 0;
            font-size: clamp(24px, 4vw, 34px);
            font-weight: 800;
            line-height: 1.65;
        }

        .ui-error__body {
            max-width: 650px;
            margin: 10px 0 0;
            color: var(--error-muted);
            font-size: 14px;
            line-height: 2.05;
        }

        .ui-error__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 21px;
        }

        .ui-error__chip {
            padding: 5px 11px;
            border: 1px solid var(--error-border);
            border-radius: 999px;
            color: var(--error-muted);
            background: #f8faf9;
            font-size: 11px;
        }

        .ui-error__chip--status {
            color: var(--error-accent);
            background:
                color-mix(
                    in srgb,
                    var(--error-accent) 6%,
                    white
                );
        }

        .ui-error__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 27px;
        }

        .ui-error__action {
            min-height: 43px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 18px;
            border-radius: 12px;
            border: 1px solid var(--error-border);
            font-family: var(--error-font);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .ui-error__action--primary {
            color: #fff;
            border-color: var(--error-accent);
            background: var(--error-accent);
        }

        .ui-error__action--secondary {
            color: var(--error-text);
            background: #fff;
        }

        .ui-error__foot {
            margin-top: 12px;
            color: var(--error-muted);
            font-size: 10px;
            text-align: center;
            opacity: .75;
        }

        @media (max-width: 620px) {
            .ui-error {
                place-items: start center;
                padding: 16px 13px;
            }

            .ui-error__platform {
                display: none;
            }

            .ui-error__card {
                padding: 25px 19px;
                border-radius: 21px;
            }

            .ui-error__logo {
                width: 42px;
                height: 42px;
                flex-basis: 42px;
            }

            .ui-error__actions {
                display: grid;
            }

            .ui-error__action {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<main
    class="ui-error"
    data-ui-content-error="1"
>
    <div class="ui-error__wrap">

        <header class="ui-error__brand">

            <div class="ui-error__identity">

                <?php if (
                    $logoEnabled
                    &&
                    $logoUrl !== ''
                ): ?>

                    <div
                        class="ui-error__logo"
                        data-ui-brand-logo="1"
                    >
                        <img
                            src="<?= $h($logoUrl) ?>"
                            alt="<?= $h($logoAlt) ?>"
                        >
                    </div>

                <?php endif; ?>

                <div>
                    <div class="ui-error__brand-title">
                        <?= $h($brandTitle) ?>
                    </div>

                    <?php if ($brandSubtitle !== ''): ?>
                        <div class="ui-error__subtitle">
                            <?= $h($brandSubtitle) ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <?php if (
                $platformBrand !== ''
                &&
                $platformBrand !== $brandTitle
            ): ?>
                <div class="ui-error__platform">
                    <?= $h($platformBrand) ?>
                </div>
            <?php endif; ?>

        </header>


        <section class="ui-error__card">

            <div class="ui-error__top">

                <div class="ui-error__icon">
                    <?= $icon ?>
                </div>

                <div class="ui-error__status">
                    <?= $h($status) ?>
                </div>

            </div>


            <h1 class="ui-error__title">
                <?= $h($title) ?>
            </h1>


            <?php if ($body !== ''): ?>
                <p class="ui-error__body">
                    <?= nl2br(
                        $h($body),
                        false
                    ) ?>
                </p>
            <?php endif; ?>


            <div class="ui-error__chips">

                <span
                    class="
                        ui-error__chip
                        ui-error__chip--status
                    "
                >
                    کد وضعیت:
                    <?= $h($status) ?>
                </span>

                <?php foreach (
                    $breadcrumbs
                    as $label
                ): ?>

                    <?php
                    $label =
                        trim(
                            (string) $label
                        );

                    if (
                        $label === ''
                        ||
                        $label === $brandTitle
                    ) {
                        continue;
                    }
                    ?>

                    <span class="ui-error__chip">
                        <?= $h($label) ?>
                    </span>

                <?php endforeach; ?>

            </div>


            <?php if ($actions !== []): ?>

                <div class="ui-error__actions">

                    <?php foreach (
                        $actions
                        as $action
                    ): ?>

                        <?php
                        $type =
                            (string) (
                                $action['type']
                                ?? ''
                            );

                        $label =
                            (string) (
                                $action['label']
                                ?? ''
                            );

                        $style =
                            (string) (
                                $action['style']
                                ?? 'secondary'
                            );
                        ?>


                        <?php if ($type === 'back'): ?>

                            <button
                                type="button"
                                class="
                                    ui-error__action
                                    ui-error__action--<?= $h($style) ?>
                                    js-ui-error-back
                                "
                                data-fallback="<?= $h(
                                    $action['fallback']
                                    ?? '/'
                                ) ?>"
                            >
                                <?= $h($label) ?>
                            </button>

                        <?php elseif ($type === 'link'): ?>

                            <a
                                class="
                                    ui-error__action
                                    ui-error__action--<?= $h($style) ?>
                                "
                                href="<?= $h(
                                    $action['href']
                                    ?? '/'
                                ) ?>"
                            >
                                <?= $h($label) ?>
                            </a>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>


        <div class="ui-error__foot">
            در صورت تکرار مشکل،
            از مسیر پشتیبانی سامانه پیگیری کنید.
        </div>

    </div>
</main>


<script>
document.querySelectorAll(
    '.js-ui-error-back'
).forEach(function (button) {

    button.addEventListener(
        'click',
        function () {

            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            window.location.assign(
                button.getAttribute(
                    'data-fallback'
                )
                || '/'
            );
        }
    );
});
</script>

</body>
</html>
