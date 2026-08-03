<?php

$items = is_array($items ?? null) ? $items : [];
ob_start();
require BASE_PATH
    . '/resources/views/admin/partials/communication-style.php';
?>
<div class="communication-grid">
    <?php foreach ($items as $item): ?>
        <a
            class="communication-card"
            href="<?= admin_h((string) ($item['url'] ?? '#')) ?>"
            data-communication-link
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
<script>
document.querySelectorAll('[data-communication-link]').forEach(function (card) {
    card.addEventListener('click', function (event) {
        var href = card.getAttribute('href');
        if (href && href !== '#') { event.preventDefault(); window.location.assign(href); }
    });
});
</script>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
