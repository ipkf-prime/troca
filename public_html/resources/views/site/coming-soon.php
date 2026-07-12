<?php
if (!function_exists('site_h')) {
    function site_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$siteMode = $siteMode ?? (string) \IPKF\Support\Env::get('SITE_MODE', 'coming_soon');
$theme = class_exists(\App\Services\AdminThemeService::class)
    ? (new \App\Services\AdminThemeService())->systemTheme()
    : [];
$brandName = trim((string) ($theme['brand_name'] ?? ''));
$brandName = $brandName !== '' ? $brandName : 'سامانه هوشمند تروکا';
$logoUrl = trim((string) ($theme['logo_url'] ?? ''));
$logoUrl = $logoUrl !== '' ? $logoUrl : '/assets/admin/images/logos/default-logo.svg';
$landingCss = '/assets/css/landing.css';
$landingCssPath = BASE_PATH . '/public' . $landingCss;
$landingCssVersion = is_readable($landingCssPath) ? (string) filemtime($landingCssPath) : (string) time();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= site_h($brandName) ?> | IPKF</title>
    <link rel="stylesheet" href="<?= site_h($landingCss) ?>?v=<?= site_h($landingCssVersion) ?>">
</head>
<body>
    <header class="site-header">
        <a class="site-brand" href="/" aria-label="<?= site_h($brandName) ?>">
            <img src="<?= site_h($logoUrl) ?>" alt="" onerror="this.style.display='none'">
            <span><?= site_h($brandName) ?></span>
        </a>
        <nav class="site-nav" aria-label="ناوبری اصلی">
            <a href="#intro">معرفی سامانه</a>
            <a href="#features">امکانات</a>
            <a href="#modules">ماژول‌ها</a>
            <a href="#roadmap">مسیر توسعه</a>
            <a href="/admin/login">ورود به پنل</a>
        </nav>
        <a class="site-header__cta" href="/admin/login">ورود به پنل مدیریت</a>
    </header>

    <main>
        <section id="intro" class="hero">
            <div class="hero__content">
                <?php if ($siteMode === 'coming_soon'): ?>
                    <p class="status-badge">نسخه آزمایشی / در حال آماده‌سازی</p>
                <?php endif; ?>
                <p class="hero__eyebrow">IPKF / Troca Enterprise Foundation</p>
                <h1>زیرساخت هوشمند مدیریت سازمانی و اتوماسیون فرایندها</h1>
                <p class="hero__lead">
                    IPKF یک بستر نرم‌افزاری قابل توسعه برای مدیریت کاربران، نقش‌ها، دسترسی‌ها، داشبوردها، ماژول‌های سازمانی و اتوماسیون مکاتبات است.
                </p>
                <p class="hero__text">
                    این سامانه به‌گونه‌ای طراحی شده که بتواند برای سازمان‌ها، شرکت‌ها، اتحادیه‌ها و مجموعه‌های مختلف، به‌صورت مرحله‌ای توسعه پیدا کند.
                </p>
                <div class="hero__actions">
                    <a class="btn btn--primary" href="/admin/login">ورود به پنل مدیریت</a>
                    <a class="btn btn--ghost" href="#features">مشاهده قابلیت‌ها</a>
                </div>
            </div>
            <aside class="hero-preview" aria-label="پیش‌نمایش داشبورد">
                <div class="hero-preview__top">
                    <span></span>
                    <strong>نمای مدیریتی</strong>
                </div>
                <div class="hero-preview__metric">
                    <small>وضعیت سامانه</small>
                    <strong>آماده دمو</strong>
                </div>
                <div class="hero-preview__grid">
                    <span>کاربران و نقش‌ها</span>
                    <span>امنیت ورود</span>
                    <span>دسترسی فعال</span>
                    <span>پوسته سازمانی</span>
                </div>
                <div class="hero-preview__flow">
                    <i></i><i></i><i></i>
                </div>
            </aside>
        </section>

        <section id="features" class="section">
            <div class="section__head">
                <p>قابلیت‌ها</p>
                <h2>زیرساخت آماده برای توسعه سامانه‌های سازمانی</h2>
            </div>
            <div class="feature-grid">
                <article>
                    <span>01</span>
                    <h3>مدیریت کاربران و نقش‌ها</h3>
                    <p>تخصیص نقش، دسترسی و سطح مجاز کاربران در پنل مدیریتی.</p>
                </article>
                <article>
                    <span>02</span>
                    <h3>پنل مدیریتی یکپارچه</h3>
                    <p>داشبورد، پروفایل، امنیت حساب، تنظیمات و دسترسی‌ها در یک محیط واحد.</p>
                </article>
                <article>
                    <span>03</span>
                    <h3>آماده برای اتوماسیون مکاتبات</h3>
                    <p>زیرساخت مناسب برای ثبت نامه، ارجاع، کارتابل، پیوست و سابقه گردش.</p>
                </article>
                <article>
                    <span>04</span>
                    <h3>قابل توسعه برای ماژول‌های سازمانی</h3>
                    <p>امکان توسعه CRM، ERP، فروشگاه، بات، گزارش‌ها و فرایندهای اختصاصی.</p>
                </article>
                <article>
                    <span>05</span>
                    <h3>امنیت و ورود چندمرحله‌ای</h3>
                    <p>پشتیبانی از ورود امن، رمز یکبارمصرف، کد بازیابی و مدیریت نشست‌ها.</p>
                </article>
                <article>
                    <span>06</span>
                    <h3>طراحی واکنش‌گرا</h3>
                    <p>قابل استفاده روی دسکتاپ، تبلت و موبایل با چیدمان فارسی خوانا.</p>
                </article>
            </div>
        </section>

        <section id="modules" class="automation section">
            <div>
                <p class="section-label">ماژول آینده</p>
                <h2>اتوماسیون مکاتبات و گردش کار</h2>
                <p>
                    در فازهای بعدی، سامانه امکان مدیریت نامه‌های وارده و صادره، کارتابل کاربران، ارجاع، پیوست، اصلاحات، یادداشت‌ها و سابقه کامل گردش را فراهم می‌کند.
                </p>
            </div>
            <div class="process-flow" aria-label="فرایند اتوماسیون">
                <span>ثبت نامه</span>
                <span>ارجاع</span>
                <span>اقدام</span>
                <span>تأیید / اصلاح</span>
                <span>بایگانی</span>
            </div>
        </section>

        <section id="roadmap" class="section">
            <div class="section__head">
                <p>مسیر توسعه</p>
                <h2>رشد مرحله‌ای، بدون اضافه کردن امکانات ناپایدار</h2>
            </div>
            <div class="roadmap">
                <article>
                    <strong>فاز فعلی</strong>
                    <p>زیرساخت پنل مدیریت، ورود امن، نقش‌ها و تنظیمات پایه</p>
                </article>
                <article>
                    <strong>فاز بعدی</strong>
                    <p>منوها و مسیرهای مبتنی بر مجوز</p>
                </article>
                <article>
                    <strong>فاز اتوماسیون</strong>
                    <p>نامه‌ها، کارتابل، ارجاع، پیوست و سابقه گردش</p>
                </article>
                <article>
                    <strong>فاز توسعه</strong>
                    <p>گزارش‌ها، اعلان‌ها، قالب نامه، امضا و فرایندهای قابل تنظیم</p>
                </article>
            </div>
        </section>

        <section class="final-cta">
            <h2>آماده بررسی نسخه نمایشی هستید؟</h2>
            <p>پنل مدیریت فعلی برای نمایش زیرساخت احراز هویت، نقش‌ها، دسترسی‌ها و پوسته سازمانی آماده است.</p>
            <a class="btn btn--primary" href="/admin/login">ورود به پنل مدیریت</a>
        </section>
    </main>

    <footer class="site-footer">
        <span>IPKF Framework</span>
        <span>Powered by ایده‌پردازان کیان فرتاک</span>
    </footer>
</body>
</html>
