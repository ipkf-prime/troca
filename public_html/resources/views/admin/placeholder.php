<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$message = $message ?? 'این بخش در نسخه‌های بعدی تکمیل می‌شود.';

ob_start();
?>
<section class="admin-section">
    <h2><?= admin_h($title ?? 'بخش در حال آماده‌سازی') ?></h2>
    <div class="admin-empty-state"><?= admin_h($message) ?></div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
