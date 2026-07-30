<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$project = $project ?? [];
$projectReference = (string) ($project['public_reference'] ?? '');
$projectUrl = '/admin/work/projects/' . rawurlencode($projectReference);
$isArchived = !empty($project['archived_at']);
$saved = isset($_GET['saved']);
$startDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($project['start_date'] ?? ''));
$targetDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($project['target_date'] ?? ''));

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <a href="/admin/work/projects">پروژه‌ها</a><span>/</span>
    <span><?= admin_h($project['title'] ?? '') ?></span>
</nav>

<section class="admin-module-hub admin-module-hub--green">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('organization') ?></div>
    <div>
        <h2><?= admin_h($project['title'] ?? '') ?></h2>
        <p dir="ltr"><?= admin_h($project['code'] ?? '') ?></p>
    </div>
    <a class="admin-module-hub__back" href="/admin/work/projects">بازگشت به پروژه‌ها</a>
</section>

<?php if ($saved): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">اطلاعات پروژه با موفقیت ذخیره شد.</div></section>
<?php endif; ?>

<section class="admin-section">
    <div class="admin-users-toolbar">
        <div>
            <span class="admin-pill"><?= admin_h($project['status_title'] ?? '') ?></span>
            <span class="admin-pill"><?= admin_h($project['visibility_title'] ?? '') ?></span>
            <?php if ($isArchived): ?><span class="admin-status-badge">بایگانی‌شده</span><?php endif; ?>
        </div>
        <div class="admin-form-actions">
            <?php if (!$isArchived): ?>
                <a class="admin-button" href="<?= admin_h($projectUrl . '/edit') ?>">ویرایش پروژه</a>
                <form method="post" action="<?= admin_h($projectUrl . '/archive') ?>" onsubmit="return confirm('پروژه بایگانی شود؟');">
                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                    <button class="admin-button admin-button--soft" type="submit">بایگانی</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= admin_h($projectUrl . '/restore') ?>">
                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                    <button class="admin-button" type="submit">بازیابی پروژه</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <dl class="admin-field-list">
        <div><span>شناسه عمومی</span><strong dir="ltr"><?= admin_h($projectReference) ?></strong></div>
        <div><span>مالک</span><strong><?= admin_h((($project['owner_display_name'] ?? '') ?: (($project['owner_user_reference'] ?? '') ?: '—'))) ?></strong></div>
        <div><span>سازمان</span><strong><?= admin_h($project['organization_snapshot'] ?: '—') ?></strong></div>
        <div><span>تاریخ شروع</span><strong><?= admin_h($startDateFa !== '' ? $startDateFa : '—') ?></strong></div>
        <div><span>تاریخ هدف</span><strong><?= admin_h($targetDateFa !== '' ? $targetDateFa : '—') ?></strong></div>
        <div><span>اعضا</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['member_count'] ?? 0))) ?></strong></div>
        <div><span>کل آیتم‌ها</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['item_count'] ?? 0))) ?></strong></div>
        <div><span>آیتم‌های باز</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['open_item_count'] ?? 0))) ?></strong></div>
    </dl>
</section>

<section class="admin-section">
    <h3>شرح پروژه</h3>
    <?php if (trim((string) ($project['description'] ?? '')) === ''): ?>
        <div class="admin-empty-state">برای این پروژه هنوز شرحی ثبت نشده است.</div>
    <?php else: ?>
        <p style="white-space:pre-wrap"><?= admin_h($project['description']) ?></p>
    <?php endif; ?>
</section>

<section class="admin-section">
    <h3>مدیریت اجرایی</h3>
    <div class="admin-action-grid">
        <article class="admin-action-card">
            <div class="admin-action-card__icon"><?= \App\Support\AdminIcon::html('users') ?></div>
            <div><h4>اعضای پروژه</h4><p>افزودن مدیر، عضو و ناظر پروژه</p></div>
            <span class="admin-pill">مرحله بعد</span>
        </article>
        <article class="admin-action-card">
            <div class="admin-action-card__icon"><?= \App\Support\AdminIcon::html('status') ?></div>
            <div><h4>کارها و تسک‌ها</h4><p>ساخت کار، نقطه عطف، تسک و زیرتسک</p></div>
            <span class="admin-pill">مرحله بعد</span>
        </article>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
