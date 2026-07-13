<?php
$accountPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$accountLinks = [
    '/admin/profile' => 'پروفایل کاربری',
    '/admin/account' => 'اطلاعات حساب',
    '/admin/security' => 'امنیت و ورود',
    '/admin/profile/access' => 'نقش‌ها و دسترسی‌های من',
    '/admin/password' => 'تغییر کلمه عبور',
    '/admin/my-theme' => 'پوسته نمایشی من',
];
?>
<nav class="admin-subnav" aria-label="بخش‌های حساب کاربری">
    <?php foreach ($accountLinks as $href => $label): ?>
        <a class="<?= $accountPath === $href ? 'is-active' : '' ?>" href="<?= admin_h($href) ?>"><?= admin_h($label) ?></a>
    <?php endforeach; ?>
</nav>
