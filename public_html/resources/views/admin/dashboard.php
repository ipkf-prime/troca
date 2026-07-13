<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$canManageAccess = in_array('access', array_column($context['navigation']['system'] ?? [], 'key'), true);
$accessStatus = trim((string) ($_GET['access_status'] ?? ''));
$fa = static fn (string $value): string => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$cards = [
    ['label' => '&#x0648;&#x0636;&#x0639;&#x06CC;&#x062A; &#x0648;&#x0631;&#x0648;&#x062F;', 'value' => $fa('&#x0641;&#x0639;&#x0627;&#x0644;')],
    ['label' => '&#x0646;&#x0642;&#x0634; &#x0641;&#x0639;&#x0627;&#x0644;', 'value' => $context['active_assignment']['role_title'] ?? $fa('&#x0628;&#x062F;&#x0648;&#x0646; &#x0646;&#x0642;&#x0634;')],
    ['label' => '&#x0631;&#x0645;&#x0632; &#x06CC;&#x06A9;&#x0628;&#x0627;&#x0631;&#x0645;&#x0635;&#x0631;&#x0641;', 'value' => ($context['mfa']['enabled'] ?? false) ? $fa('&#x0641;&#x0639;&#x0627;&#x0644;') : $fa('&#x063A;&#x06CC;&#x0631;&#x0641;&#x0639;&#x0627;&#x0644;')],
    ['label' => '&#x0646;&#x0633;&#x062E;&#x0647;', 'value' => $context['version'] ?? ''],
];
$modules = $context['dashboard_modules'] ?? [];

ob_start();
?>
<?php if ($accessStatus === 'switched'): ?>
    <div class="admin-notice">&#x0646;&#x0642;&#x0634; &#x0641;&#x0639;&#x0627;&#x0644; &#x062A;&#x063A;&#x06CC;&#x06CC;&#x0631; &#x06A9;&#x0631;&#x062F;.</div>
<?php elseif ($accessStatus === 'forbidden'): ?>
    <div class="admin-alert">&#x0627;&#x0645;&#x06A9;&#x0627;&#x0646; &#x062A;&#x063A;&#x06CC;&#x06CC;&#x0631; &#x0628;&#x0647; &#x0627;&#x06CC;&#x0646; &#x0646;&#x0642;&#x0634; &#x0648;&#x062C;&#x0648;&#x062F; &#x0646;&#x062F;&#x0627;&#x0631;&#x062F;.</div>
<?php endif; ?>

<section class="admin-grid">
    <?php foreach ($cards as $card): ?>
        <article class="admin-card">
            <span><?= $card['label'] ?></span>
            <strong><?= admin_h($card['value']) ?></strong>
        </article>
    <?php endforeach; ?>
</section>

<?php if ($modules !== []): ?>
    <section class="admin-section admin-dashboard-modules">
        <div class="admin-section__header">
            <div>
                <h2>&#x0645;&#x0627;&#x0698;&#x0648;&#x0644;&#x200C;&#x0647;&#x0627;&#x06CC; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;</h2>
                <p class="admin-muted">&#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC; &#x0628;&#x0647; &#x0628;&#x062E;&#x0634;&#x200C;&#x0647;&#x0627; &#x0628;&#x0631; &#x0627;&#x0633;&#x0627;&#x0633; &#x0646;&#x0642;&#x0634; &#x0641;&#x0639;&#x0627;&#x0644; &#x0634;&#x0645;&#x0627; &#x0646;&#x0645;&#x0627;&#x06CC;&#x0634; &#x062F;&#x0627;&#x062F;&#x0647; &#x0645;&#x06CC;&#x200C;&#x0634;&#x0648;&#x062F;.</p>
            </div>
        </div>
        <div class="admin-module-launcher">
            <?php foreach ($modules as $module): ?>
                <a class="admin-module-launcher__tile admin-module-launcher__tile--<?= admin_h($module['color'] ?? 'blue') ?>" href="<?= admin_h($module['url'] ?? '#') ?>">
                    <span class="admin-module-launcher__icon">
                        <?= \App\Support\AdminIcon::html((string) ($module['icon'] ?? 'dashboard')) ?>
                    </span>
                    <span class="admin-module-launcher__body">
                        <strong><?= admin_h($module['title'] ?? '') ?></strong>
                        <small><?= admin_h($module['subtitle'] ?? $module['description'] ?? '') ?></small>
                    </span>
                    <span class="admin-module-launcher__enter" aria-hidden="true">&#x2190;</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>&#x062E;&#x0644;&#x0627;&#x0635;&#x0647; &#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC;</h2>
            <p class="admin-muted">&#x0646;&#x0642;&#x0634; &#x0641;&#x0639;&#x0627;&#x0644;&#x060C; &#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC; &#x062C;&#x0627;&#x0631;&#x06CC; &#x0634;&#x0645;&#x0627; &#x0631;&#x0627; &#x062F;&#x0631; &#x067E;&#x0646;&#x0644; &#x0645;&#x0634;&#x062E;&#x0635; &#x0645;&#x06CC;&#x200C;&#x06A9;&#x0646;&#x062F;.</p>
        </div>
        <?php if ($canManageAccess): ?>
            <a class="admin-button admin-button--soft" href="/admin/access">&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC;&#x200C;&#x0647;&#x0627;</a>
        <?php endif; ?>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>&#x0646;&#x0642;&#x0634;</th>
                    <th>&#x06A9;&#x062F;</th>
                    <th>&#x0627;&#x0648;&#x0644;&#x0648;&#x06CC;&#x062A;</th>
                    <th>&#x0648;&#x0636;&#x0639;&#x06CC;&#x062A;</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($context['assignments'] as $assignment): ?>
                    <?php $isActive = (int) ($context['active_assignment']['id'] ?? 0) === (int) $assignment['id']; ?>
                    <tr>
                        <td><?= admin_h($assignment['role_title'] ?? '') ?></td>
                        <td><?= admin_h($assignment['role_code'] ?? '') ?></td>
                        <td><?= admin_h($assignment['priority'] ?? '') ?></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="admin-pill">&#x0641;&#x0639;&#x0627;&#x0644;</span>
                            <?php else: ?>
                                <form method="post" action="/admin/access" class="admin-inline-form">
                                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                                    <input type="hidden" name="role_assignment_id" value="<?= (int) $assignment['id'] ?>">
                                    <button type="submit">&#x0627;&#x0646;&#x062A;&#x062E;&#x0627;&#x0628; &#x0646;&#x0642;&#x0634;</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
