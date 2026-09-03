<?php

$title = 'مدیریت راهنماها';

ob_start();
?>

<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/dashboard">
        داشبورد
    </a>
    <span>/</span>

    <a href="/admin/modules/system">
        مدیریت سامانه
    </a>
    <span>/</span>

    <span>راهنماها</span>
</nav>

<section
    class="admin-module-hub admin-module-hub--green"
>
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html(
            'book-open'
        ) ?>
    </div>

    <div>
        <h2>مدیریت راهنماها</h2>
        <p>
            مدیریت متمرکز متن‌های راهنما،
            توضیحات فرم‌ها و پیام‌های آموزشی
        </p>
    </div>
</section>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>مخزن راهنماهای سامانه</h2>

            <p class="admin-muted">
                این بخش برای انتقال تدریجی
                راهنماهای هاردکدشده به مدیریت
                متمرکز آماده شده است.
            </p>
        </div>
    </div>

    <div class="admin-empty-state">
        در مرحله بعد، هر راهنما با
        کلید یکتا، محدوده کاربرد،
        متن قابل ویرایش، ترتیب نمایش
        و وضعیت فعال/غیرفعال
        در این صفحه مدیریت خواهد شد.
    </div>
</section>

<?php

$content = ob_get_clean();

require __DIR__ . '/layout.php';
