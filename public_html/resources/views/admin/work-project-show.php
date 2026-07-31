<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$project = $project ?? [];
$access = $access ?? [];
$projectReference = (string) ($project['public_reference'] ?? '');
$projectUrl = '/admin/work/projects/' . rawurlencode($projectReference);
$isArchived = !empty($project['archived_at']);
$saved = isset($_GET['saved']);
$startDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($project['start_date'] ?? ''));
$targetDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($project['target_date'] ?? ''));
$identityLabels = new \App\Services\UserIdentityLabelService();
$project['owner_display_name'] = $identityLabels->labelForReference(
    (string) ($project['owner_user_reference'] ?? ''),
    (string) ($project['owner_display_name'] ?? '')
);

ob_start();
require __DIR__ . '/work-ui-styles.php';
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <a href="/admin/work/projects">پروژه‌ها</a><span>/</span>
    <span><?= admin_h($project['title'] ?? '') ?></span>
</nav>

<section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
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

<section class="admin-section work-compact-section">
    <div class="admin-users-toolbar">
        <div>
            <span class="admin-pill"><?= admin_h($project['status_title'] ?? '') ?></span>
            <span class="admin-pill"><?= admin_h($project['visibility_title'] ?? '') ?></span>
            <?php if (!empty($access['role_title'])): ?><span class="admin-pill"><?= admin_h($access['role_title']) ?></span><?php endif; ?>
            <?php if ($isArchived): ?><span class="admin-status-badge">بایگانی‌شده</span><?php endif; ?>
        </div>
        <div class="admin-form-actions">
            <?php if (!$isArchived && !empty($access['can_manage_project'])): ?>
                <a class="admin-button" href="<?= admin_h($projectUrl . '/edit') ?>">ویرایش پروژه</a>
                <form method="post" action="<?= admin_h($projectUrl . '/archive') ?>" onsubmit="return confirm('پروژه بایگانی شود؟');">
                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                    <button class="admin-button work-button--danger" type="submit">بایگانی</button>
                </form>
            <?php elseif ($isArchived && !empty($access['can_manage_project'])): ?>
                <form method="post" action="<?= admin_h($projectUrl . '/restore') ?>">
                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                    <button class="admin-button" type="submit">بازیابی پروژه</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-action-grid work-project-actions">
        <?php if (!empty($access['can_manage_members'])): ?>
            <a class="admin-action-card" href="<?= admin_h($projectUrl . '/members') ?>">
                <div class="admin-action-card__icon"><?= \App\Support\AdminIcon::html('users') ?></div>
                <div><h4>اعضای پروژه</h4><p>افزودن مدیر، عضو و ناظر پروژه</p></div>
                <span class="work-action-button work-action-button--navigate">مدیریت اعضا</span>
            </a>
        <?php endif; ?>
        <a class="admin-action-card" href="<?= admin_h($projectUrl . '/items') ?>">
            <div class="admin-action-card__icon"><?= \App\Support\AdminIcon::html('status') ?></div>
            <div><h4>کارها و تسک‌ها</h4><p>ساخت کار، نقطه عطف، تسک و زیرتسک</p></div>
            <span class="work-action-button work-action-button--navigate">مشاهده کارها</span>
        </a>
    </div>

    <dl class="work-project-summary">
        <div><span>شناسه عمومی</span><strong dir="ltr"><?= admin_h($projectReference) ?></strong></div>
        <div><span>مالک</span><strong><?= admin_h(($project['owner_display_name'] ?? '') ?: '—') ?></strong></div>
        <div><span>سازمان</span><strong><?= admin_h(($project['organization_snapshot'] ?? '') ?: '—') ?></strong></div>
        <div><span>تاریخ شروع</span><strong><?= admin_h($startDateFa !== '' ? $startDateFa : '—') ?></strong></div>
        <div><span>تاریخ هدف</span><strong><?= admin_h($targetDateFa !== '' ? $targetDateFa : '—') ?></strong></div>
        <div><span>اعضا</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['member_count'] ?? 0))) ?></strong></div>
        <div><span>کل آیتم‌ها</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['item_count'] ?? 0))) ?></strong></div>
        <div><span>آیتم‌های باز</span><strong><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['open_item_count'] ?? 0))) ?></strong></div>
    </dl>

    <?php if (trim((string) ($project['description'] ?? '')) !== ''): ?>
        <div class="work-project-description">
            <h3>شرح پروژه</h3>
            <p style="white-space:pre-wrap"><?= admin_h($project['description']) ?></p>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
