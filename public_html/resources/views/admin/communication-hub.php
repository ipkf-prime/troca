<?php

$items = is_array($items ?? null) ? $items : [];
ob_start();
require BASE_PATH
    . '/resources/views/admin/partials/communication-style.php';
?>
<section class="communication-panel">
    <h2>پیام‌ها و اعلان‌ها</h2>
    <p class="communication-muted">
        منو، ترتیب، دسترسی، Badge و مسیرهای این بخش از
        Registry دیتابیس خوانده می‌شوند.
    </p>
</section>

<div class="communication-grid" style="margin-top:1rem">
    <?php foreach ($items as $item): ?>
        <a
            class="communication-card"
            href="<?= admin_h($item['url']) ?>"
        >
            <h3><?= admin_h($item['title']) ?></h3>
            <p><?= admin_h($item['description']) ?></p>
            <?php if (($item['badge'] ?? '') !== ''): ?>
                <span class="communication-badge">
                    <?= admin_h($item['badge']) ?>
                </span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
