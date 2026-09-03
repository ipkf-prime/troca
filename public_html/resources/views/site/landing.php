<?php

if (!function_exists('landing_h')) {
    function landing_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$page = is_array($page ?? null) ? $page : [];
$settings = is_array($page['settings'] ?? null)
    ? $page['settings'] : [];
$theme = is_array($page['theme'] ?? null)
    ? $page['theme'] : [];
$navigation = is_array($page['navigation'] ?? null)
    ? $page['navigation'] : [];
$slides = is_array($page['slides'] ?? null)
    ? $page['slides'] : [];
$announcements =
    is_array($page['announcements'] ?? null)
        ? $page['announcements'] : [];
$cards = is_array($page['cards'] ?? null)
    ? $page['cards'] : [];
$footerLinks =
    is_array($page['footer_links'] ?? null)
        ? $page['footer_links'] : [];
$runtime = is_array($page['runtime'] ?? null)
    ? $page['runtime'] : [];

$brand = trim(
    (string) ($theme['brand_name'] ?? '')
);
$brand = $brand !== ''
    ? $brand
    : (string) (
        $settings['page_title']
        ?? 'سامانه'
    );

$logo = trim(
    (string) ($theme['logo_url'] ?? '')
);

$pageTitle = $brand;

$description = trim(
    (string) (
        $settings['meta_description']
        ?? ''
    )
);

$loginLabel =
    trim(
        (string) (
            $settings['login_label']
            ?? 'ورود به سامانه'
        )
    );

$showRegister =
    ($settings['show_register'] ?? '0')
    === '1';

$registerLabel =
    trim(
        (string) (
            $settings['register_label']
            ?? 'ثبت‌نام'
        )
    );

$registerUrl =
    trim(
        (string) (
            $settings['register_url']
            ?? '/register'
        )
    );

$css = '/assets/css/public-landing.css';
$js = '/assets/js/public-landing.js';

$cssVersion = is_readable(
    BASE_PATH . '/public' . $css
)
    ? filemtime(BASE_PATH . '/public' . $css)
    : 1;

$jsVersion = is_readable(
    BASE_PATH . '/public' . $js
)
    ? filemtime(BASE_PATH . '/public' . $js)
    : 1;
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title><?= landing_h($pageTitle) ?></title>
    <?php if ($description !== ''): ?>
        <meta
            name="description"
            content="<?= landing_h($description) ?>"
        >
    <?php endif; ?>
    <link
        rel="stylesheet"
        href="<?= landing_h($css) ?>?v=<?= (int) $cssVersion ?>"
    >
</head>
<body>
<header class="public-header">
    <a class="public-brand" href="/">
        <?php if ($logo !== ''): ?>
            <img
                src="<?= landing_h($logo) ?>"
                alt="<?= landing_h($brand) ?>"
            >
        <?php endif; ?>
        <strong><?= landing_h($brand) ?></strong>
    </a>

    <button
        class="nav-toggle"
        type="button"
        aria-label="نمایش منو"
        aria-expanded="false"
        data-nav-toggle
    >☰</button>

    <nav class="public-nav" data-nav>
        <?php foreach ($navigation as $item): ?>
            <a
                href="<?= landing_h($item['action_url'] ?? '#') ?>"
                target="<?= landing_h($item['action_target'] ?? '_self') ?>"
                <?= ($item['action_target'] ?? '') === '_blank'
                    ? 'rel="noopener noreferrer"' : '' ?>
            >
                <?= landing_h($item['title'] ?? '') ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="public-auth">
        <?php if ($showRegister): ?>
            <a
                class="btn btn--ghost"
                href="<?= landing_h($registerUrl) ?>"
            >
                <?= landing_h($registerLabel) ?>
            </a>
        <?php endif; ?>

        <a
            class="btn btn--primary"
            href="/admin/login"
        >
            <?= landing_h($loginLabel) ?>
        </a>
    </div>
</header>

<main>
<section id="intro" class="slider" data-slider>
    <?php foreach ($slides as $index => $slide): ?>
        <article
            class="slide<?= $index === 0 ? ' is-active' : '' ?>"
            data-slide
        >
            <?php
            $desktop = trim(
                (string) ($slide['image_url'] ?? '')
            );
            $mobile = trim(
                (string) ($slide['mobile_image_url'] ?? '')
            );
            ?>

            <?php if ($desktop !== ''): ?>
                <picture class="slide__media">
                    <?php if ($mobile !== ''): ?>
                        <source
                            media="(max-width: 720px)"
                            srcset="<?= landing_h($mobile) ?>"
                        >
                    <?php endif; ?>
                    <img
                        src="<?= landing_h($desktop) ?>"
                        alt=""
                    >
                </picture>
            <?php endif; ?>

            <div class="slide__overlay"></div>

            <div class="slide__content">
                <?php if (trim((string) ($slide['eyebrow'] ?? '')) !== ''): ?>
                    <p class="eyebrow">
                        <?= landing_h($slide['eyebrow']) ?>
                    </p>
                <?php endif; ?>

                <h1>
                    <?= landing_h($slide['title'] ?? $pageTitle) ?>
                </h1>

                <?php if (trim((string) ($slide['body'] ?? '')) !== ''): ?>
                    <p class="lead">
                        <?= nl2br(landing_h($slide['body'])) ?>
                    </p>
                <?php endif; ?>

                <?php if (trim((string) ($slide['action_url'] ?? '')) !== ''): ?>
                    <a
                        class="btn btn--primary"
                        href="<?= landing_h($slide['action_url']) ?>"
                        target="<?= landing_h($slide['action_target'] ?? '_self') ?>"
                    >
                        <?= landing_h(
                            $slide['action_text']
                            ?: $loginLabel
                        ) ?>
                    </a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if (count($slides) > 1): ?>
        <div class="slider-dots" data-slider-dots></div>
    <?php endif; ?>
</section>

<?php if ($announcements !== []): ?>
<section
    id="announcements"
    class="section announcements"
>
    <div class="section-head">
        <span>اطلاع‌رسانی</span>
        <h2>اطلاعیه‌ها</h2>
    </div>

    <div class="announcement-list">
        <?php foreach ($announcements as $item): ?>
            <article>
                <h3><?= landing_h($item['title'] ?? '') ?></h3>
                <p><?= nl2br(landing_h($item['body'] ?? '')) ?></p>

                <?php if (trim((string) ($item['action_url'] ?? '')) !== ''): ?>
                    <a href="<?= landing_h($item['action_url']) ?>">
                        <?= landing_h(
                            $item['action_text']
                            ?: 'مشاهده'
                        ) ?>
                    </a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($cards !== []): ?>
<section id="services" class="section">
    <div class="section-head">
        <span>خدمات یکپارچه</span>
        <h2><?= landing_h($description) ?></h2>
    </div>

    <div class="card-grid">
        <?php foreach ($cards as $card): ?>
            <article class="service-card">
                <?php
                $iconCode = trim(
                    (string) ($card['icon'] ?? '')
                );

                $iconAliases = [
                    'layers' => 'sliders',
                    'shield' => 'user-shield',
                    'grid' => 'gears',
                ];

                $iconCode =
                    $iconAliases[$iconCode]
                    ?? $iconCode;
                ?>

                <?php if (
                    $iconCode !== ''
                    && class_exists(
                        \App\Support\AdminIcon::class
                    )
                    && \App\Support\AdminIcon::supports(
                        $iconCode
                    )
                ): ?>
                    <span class="service-card__icon">
                        <?= \App\Support\AdminIcon::html(
                            $iconCode,
                            'public-card-icon'
                        ) ?>
                    </span>
                <?php endif; ?>

                <h3><?= landing_h($card['title'] ?? '') ?></h3>
                <p><?= nl2br(landing_h($card['body'] ?? '')) ?></p>

                <?php if (trim((string) ($card['action_url'] ?? '')) !== ''): ?>
                    <a href="<?= landing_h($card['action_url']) ?>">
                        <?= landing_h(
                            $card['action_text']
                            ?: 'مشاهده'
                        ) ?>
                    </a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section
    class="runtime-strip"
    aria-label="وضعیت سامانه"
>
    <?php
    $runtimeSlots =
        is_array(
            $page['runtime_slots']
            ?? null
        )
            ? $page['runtime_slots']
            : [
                'right' => [],
                'center' => [],
                'left' => [],
            ];
    ?>

    <?php foreach (
        ['right', 'center', 'left']
        as $runtimeZone
    ): ?>
        <div
            class="runtime-strip__zone runtime-strip__zone--<?= landing_h(
                $runtimeZone
            ) ?>"
        >
            <?php foreach (
                $runtimeSlots[$runtimeZone]
                    ?? []
                as $runtimeItem
            ): ?>
                <span
                    class="runtime-item runtime-item--<?= landing_h(
                        $runtimeItem['kind']
                        ?? 'default'
                    ) ?>"
                >
                    <?php if (
                        (
                            $runtimeItem['kind']
                            ?? ''
                        ) === 'status'
                    ): ?>
                        <i
                            aria-hidden="true"
                        ></i>
                    <?php endif; ?>

                    <?= landing_h(
                        $runtimeItem['text']
                        ?? ''
                    ) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>
</main>

<?php if (!empty($theme['footer_enabled'])): ?>
<footer class="public-footer">
    <div>
        <strong><?= landing_h($brand) ?></strong>
        <p>
            <?= landing_h(
                $theme['footer_text']
                ?? ''
            ) ?>
        </p>
    </div>

    <nav>
        <?php foreach ($footerLinks as $item): ?>
            <a
                href="<?= landing_h($item['action_url'] ?? '#') ?>"
                target="<?= landing_h($item['action_target'] ?? '_self') ?>"
            >
                <?= landing_h($item['title'] ?? '') ?>
            </a>
        <?php endforeach; ?>
    </nav>
</footer>
<?php endif; ?>

<script
    src="<?= landing_h($js) ?>?v=<?= (int) $jsVersion ?>"
    defer
></script>
</body>
</html>
