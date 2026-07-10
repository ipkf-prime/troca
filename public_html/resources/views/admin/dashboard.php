<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$cards = [
    ['label' => 'وضعیت ورود', 'value' => 'فعال'],
    ['label' => 'نقش فعال', 'value' => $context['active_assignment']['role_title'] ?? 'بدون نقش'],
    ['label' => 'MFA', 'value' => ($context['mfa']['enabled'] ?? false) ? 'فعال' : 'غیرفعال'],
    ['label' => 'نسخه', 'value' => $context['version'] ?? ''],
];

ob_start();
?>
<section class="admin-grid">
    <?php foreach ($cards as $card): ?>
        <article class="admin-card">
            <span><?= admin_h($card['label']) ?></span>
            <strong><?= admin_h($card['value']) ?></strong>
        </article>
    <?php endforeach; ?>
</section>

<section class="admin-section">
    <h2>خلاصه دسترسی</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>نقش</th>
                    <th>کد</th>
                    <th>اولویت</th>
                    <th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($context['assignments'] as $assignment): ?>
                    <tr>
                        <td><?= admin_h($assignment['role_title'] ?? '') ?></td>
                        <td><?= admin_h($assignment['role_code'] ?? '') ?></td>
                        <td><?= admin_h($assignment['priority'] ?? '') ?></td>
                        <td><?= (int) ($context['active_assignment']['id'] ?? 0) === (int) $assignment['id'] ? 'فعال' : 'قابل انتخاب' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
