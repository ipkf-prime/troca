<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$summary = $dashboard['summary'] ?? [];
$tasks = $dashboard['recent_tasks'] ?? [];
?>
<section class="admin-page">
    <div class="admin-page__header"><div><h1>IPKF Work Management</h1><p>&#x0645;&#x0631;&#x06A9;&#x0632; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x067E;&#x0631;&#x0648;&#x0698;&#x0647;&#x200C;&#x0647;&#x0627;&#x060C; Work&#x0647;&#x0627; &#x0648; &#x062A;&#x0633;&#x06A9;&#x200C;&#x0647;&#x0627;&#x06CC; &#x062A;&#x06CC;&#x0645;</p></div></div>
    <div class="admin-stat-grid">
        <article class="admin-card"><strong><?= (int) ($summary['projects'] ?? 0) ?></strong><span>&#x067E;&#x0631;&#x0648;&#x0698;&#x0647; &#x0641;&#x0639;&#x0627;&#x0644;</span></article>
        <article class="admin-card"><strong><?= (int) ($summary['works'] ?? 0) ?></strong><span>Work &#x0628;&#x0627;&#x0632;</span></article>
        <article class="admin-card"><strong><?= (int) ($summary['open_tasks'] ?? 0) ?></strong><span>&#x062A;&#x0633;&#x06A9; &#x0628;&#x0627;&#x0632;</span></article>
        <article class="admin-card"><strong><?= (int) ($summary['overdue_tasks'] ?? 0) ?></strong><span>&#x062A;&#x0633;&#x06A9; &#x0639;&#x0642;&#x0628;&#x200C;&#x0627;&#x0641;&#x062A;&#x0627;&#x062F;&#x0647;</span></article>
    </div>
    <div class="admin-card"><h2>&#x0622;&#x062E;&#x0631;&#x06CC;&#x0646; &#x062A;&#x0633;&#x06A9;&#x200C;&#x0647;&#x0627;</h2>
        <?php if ($tasks === []): ?><p class="admin-empty-state">&#x0647;&#x0646;&#x0648;&#x0632; &#x062A;&#x0633;&#x06A9;&#x06CC; &#x062B;&#x0628;&#x062A; &#x0646;&#x0634;&#x062F;&#x0647; &#x0627;&#x0633;&#x062A;&#x002E; &#x0633;&#x0627;&#x062E;&#x062A;&#x0627;&#x0631; &#x0627;&#x0648;&#x0644;&#x06CC;&#x0647; &#x0622;&#x0645;&#x0627;&#x062F;&#x0647; &#x0627;&#x0633;&#x062A;&#x002E;</p>
        <?php else: ?><div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>&#x062A;&#x0633;&#x06A9;</th><th>&#x067E;&#x0631;&#x0648;&#x0698;&#x0647; / Work</th><th>&#x0648;&#x0636;&#x0639;&#x06CC;&#x062A;</th><th>&#x0627;&#x0648;&#x0644;&#x0648;&#x06CC;&#x062A;</th><th>&#x067E;&#x06CC;&#x0634;&#x0631;&#x0641;&#x062A;</th></tr></thead><tbody>
            <?php foreach ($tasks as $task): ?><tr>
                <td><?= admin_h($task['title'] ?? '') ?></td>
                <td><?= admin_h($task['project_title'] ?? '') ?> / <?= admin_h($task['work_title'] ?? '-') ?></td>
                <td><?= admin_h($task['status_title'] ?? $task['status'] ?? '') ?></td>
                <td><?= admin_h($task['priority'] ?? '') ?></td><td><?= (int) ($task['progress_percent'] ?? 0) ?>%</td>
            </tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
    </div>
</section>
