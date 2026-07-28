<?php
$summary = $dashboard['summary'] ?? [];
$tasks = $dashboard['recent_tasks'] ?? [];
$statusLabels = ['backlog' => 'صف انتظار', 'planned' => 'برنامه‌ریزی', 'in_progress' => 'در حال انجام', 'blocked' => 'متوقف', 'review' => 'بازبینی', 'done' => 'انجام‌شده'];
?>
<section class="admin-page">
    <div class="admin-page__header"><div><h1>IPKF Work Management</h1><p>مرکز مدیریت پروژه‌ها، Workها و تسک‌های تیم</p></div></div>
    <div class="admin-stat-grid">
        <article class="admin-card"><strong><?= (int) ($summary['projects'] ?? 0) ?></strong><span>پروژه فعال</span></article>
        <article class="admin-card"><strong><?= (int) ($summary['works'] ?? 0) ?></strong><span>Work باز</span></article>
        <article class="admin-card"><strong><?= (int) ($summary['open_tasks'] ?? 0) ?></strong><span>تسک باز</span></article>
        <article class="admin-card"><strong><?= (int) ($summary['overdue_tasks'] ?? 0) ?></strong><span>تسک عقب‌افتاده</span></article>
    </div>
    <div class="admin-card"><h2>آخرین تسک‌ها</h2>
        <?php if ($tasks === []): ?><p class="admin-empty-state">هنوز تسکی ثبت نشده است. ساختار اولیه آماده است.</p>
        <?php else: ?><div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>تسک</th><th>پروژه / Work</th><th>وضعیت</th><th>اولویت</th><th>پیشرفت</th></tr></thead><tbody>
            <?php foreach ($tasks as $task): ?><tr>
                <td><?= htmlspecialchars((string) $task['title']) ?></td>
                <td><?= htmlspecialchars((string) $task['project_title']) ?> / <?= htmlspecialchars((string) $task['work_title']) ?></td>
                <td><?= htmlspecialchars($statusLabels[$task['status']] ?? (string) $task['status']) ?></td>
                <td><?= htmlspecialchars((string) $task['priority']) ?></td><td><?= (int) $task['progress_percent'] ?>٪</td>
            </tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
    </div>
</section>
