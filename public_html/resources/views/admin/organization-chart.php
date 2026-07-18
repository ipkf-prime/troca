<?php
if (!function_exists('admin_h')) {
    function admin_h($v): string
    {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}
$chart = $chart ?? ['ok' => false, 'organizations' => []];

$renderUnit = function (string $ref, array $units) use (&$renderUnit): void {
    $u = $units[$ref];
    ?>
    <li class="admin-org-tree__unit">
        <div class="admin-org-tree__node">
            <strong><?= admin_h($u['title']) ?></strong>
            <span><?= admin_h(count($u['positions'])) ?> پست</span>
        </div>
        <?php if ($u['positions']): ?>
            <div class="admin-org-tree__positions">
                <?php foreach ($u['positions'] as $p): ?>
                    <article>
                        <b><?= admin_h($p['title']) ?></b>
                        <small><?= admin_h($p['occupant'] ?: 'بلاتصدی') ?></small>
                        <?php if ($p['is_head']): ?><em>مسئول واحد</em><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($u['children']): ?>
            <ul>
                <?php foreach ($u['children'] as $child) { $renderUnit($child, $units); } ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php
};

ob_start();
?>
<nav class="admin-breadcrumb"><a href="/admin/dashboard">داشبورد</a><span>/</span><a href="/admin/modules/organization">ساختار سازمانی</a><span>/</span><span>چارت سازمانی</span></nav>
<section class="admin-module-hub admin-module-hub--teal"><div><h2>چارت سازمانی</h2><p>نمایش واحدها، پست‌ها، مسئولان و جایگاه‌های بلاتصدی</p></div><div class="admin-actions-row"><a class="admin-button admin-button--soft" href="/admin/organization-setup">راه‌اندازی ساختار</a><a class="admin-button" href="/admin/appointments">مدیریت انتصاب‌ها</a></div></section>
<section class="admin-section">
<?php if (!$chart['ok']): ?>
    <div class="admin-alert">دریافت چارت سازمانی ممکن نیست.</div>
<?php elseif (!$chart['organizations']): ?>
    <div class="admin-empty-state">هنوز سازمان فعالی ثبت نشده است.</div>
<?php else: ?>
    <?php foreach ($chart['organizations'] as $org): ?>
        <article class="admin-org-chart">
            <header><h3><?= admin_h($org['title']) ?></h3><span class="admin-status-badge admin-status-badge--<?= $org['active'] ? 'active' : 'inactive' ?>"><?= $org['active'] ? 'فعال' : 'غیرفعال' ?></span></header>
            <?php if ($org['root_positions']): ?>
                <div class="admin-org-tree__positions admin-org-tree__positions--root">
                    <?php foreach ($org['root_positions'] as $p): ?><article><b><?= admin_h($p['title']) ?></b><small><?= admin_h($p['occupant'] ?: 'بلاتصدی') ?></small></article><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <ul class="admin-org-tree"><?php foreach ($org['root_units'] as $ref) { $renderUnit($ref, $org['units']); } ?></ul>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
</section>
<style>.admin-org-chart{border:1px solid var(--admin-border,#ddd);border-radius:16px;padding:18px;margin-bottom:18px}.admin-org-chart>header{display:flex;justify-content:space-between;align-items:center}.admin-org-tree,.admin-org-tree ul{list-style:none;padding-right:24px}.admin-org-tree__unit{border-right:2px solid var(--admin-border,#ddd);padding:10px 16px}.admin-org-tree__node{display:flex;gap:12px;align-items:center}.admin-org-tree__node span{font-size:.8rem;opacity:.7}.admin-org-tree__positions{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin:10px 0}.admin-org-tree__positions article{border:1px solid var(--admin-border,#ddd);border-radius:10px;padding:10px;display:flex;flex-direction:column}.admin-org-tree__positions small{opacity:.75}.admin-org-tree__positions em{font-size:.75rem;color:var(--admin-accent,#087)}@media(max-width:640px){.admin-org-tree,.admin-org-tree ul{padding-right:10px}}</style>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
