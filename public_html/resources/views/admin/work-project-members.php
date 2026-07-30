<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$page = $page ?? [];
$project = $page['project'] ?? [];
$members = $page['members'] ?? [];
$users = $page['users'] ?? [];
$roleOptions = $page['role_options'] ?? [];
$errors = $errors ?? [];
$projectReference = (string) ($project['public_reference'] ?? '');
$projectUrl = '/admin/work/projects/' . rawurlencode($projectReference);
$isArchived = !empty($project['archived_at']);

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <a href="/admin/work/projects">پروژه‌ها</a><span>/</span>
    <a href="<?= admin_h($projectUrl) ?>"><?= admin_h($project['title'] ?? '') ?></a><span>/</span>
    <span>اعضا</span>
</nav>

<section class="admin-module-hub admin-module-hub--green">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('users') ?></div>
    <div>
        <h2>اعضای پروژه</h2>
        <p><?= admin_h($project['title'] ?? '') ?></p>
    </div>
    <a class="admin-module-hub__back" href="<?= admin_h($projectUrl) ?>">بازگشت به پروژه</a>
</section>

<?php if (isset($_GET['saved'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">عضو پروژه با موفقیت ثبت شد.</div></section>
<?php elseif (isset($_GET['updated'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">نقش عضو با موفقیت تغییر کرد.</div></section>
<?php elseif (isset($_GET['removed'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">عضو از پروژه خارج شد.</div></section>
<?php elseif (isset($_GET['error'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--danger">عملیات موردنظر انجام نشد.</div></section>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <section class="admin-section">
        <div class="admin-alert admin-alert--danger" role="alert">
            <strong>ثبت عضو انجام نشد.</strong>
            <ul><?php foreach ($errors as $memberError): ?><li><?= admin_h($memberError) ?></li><?php endforeach; ?></ul>
        </div>
    </section>
<?php endif; ?>

<?php if (!$isArchived): ?>
<section class="admin-section">
    <div class="admin-section__header">
        <div><h3>افزودن عضو</h3><p class="admin-muted">کاربر سامانه و نقش او در پروژه را انتخاب کنید.</p></div>
    </div>

    <?php if ($users === []): ?>
        <div class="admin-empty-state">همه کاربران فعال سامانه عضو این پروژه هستند.</div>
    <?php else: ?>
        <form method="post" action="<?= admin_h($projectUrl . '/members') ?>">
            <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
            <div class="admin-form-grid">
                <label>
                    <span>کاربر</span>
                    <select name="user_id" required>
                        <option value="">انتخاب کاربر</option>
                        <?php foreach ($users as $candidate): ?>
                            <option value="<?= admin_h((int) ($candidate['id'] ?? 0)) ?>">
                                <?= admin_h($candidate['display_name'] ?? '') ?><?= !empty($candidate['username']) ? ' — ' . admin_h($candidate['username']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>نقش در پروژه</span>
                    <select name="role_code" required>
                        <?php foreach ($roleOptions as $roleCode => $roleLabel): ?>
                            <option value="<?= admin_h($roleCode) ?>"<?= $roleCode === 'member' ? ' selected' : '' ?>><?= admin_h($roleLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="admin-form-actions"><button class="admin-button" type="submit">افزودن عضو</button></div>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="admin-section">
    <div class="admin-section__header">
        <div><h3>فهرست اعضا</h3><p class="admin-muted"><?= admin_h(\App\Support\AdminFormat::digits(count($members))) ?> عضو فعال</p></div>
    </div>

    <?php if ($members === []): ?>
        <div class="admin-empty-state">عضوی برای این پروژه ثبت نشده است.</div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>عضو</th><th>شناسه کاربر</th><th>نقش</th><th>تاریخ عضویت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($members as $member): ?>
                    <?php $memberId = (int) ($member['id'] ?? 0); $isOwner = (string) ($member['role_code'] ?? '') === 'owner'; ?>
                    <tr>
                        <td><strong><?= admin_h($member['display_name_snapshot'] ?? '') ?></strong></td>
                        <td dir="ltr"><?= admin_h($member['user_reference'] ?? '—') ?></td>
                        <td>
                            <?php if ($isOwner || $isArchived): ?>
                                <span class="admin-pill"><?= admin_h($member['role_title'] ?? '') ?></span>
                            <?php else: ?>
                                <form method="post" action="<?= admin_h($projectUrl . '/members/' . $memberId . '/role') ?>" class="admin-form-actions">
                                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                                    <select name="role_code" aria-label="نقش عضو">
                                        <?php foreach ($roleOptions as $roleCode => $roleLabel): ?>
                                            <option value="<?= admin_h($roleCode) ?>"<?= (string) ($member['role_code'] ?? '') === $roleCode ? ' selected' : '' ?>><?= admin_h($roleLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="admin-button admin-button--soft admin-button--compact" type="submit">ذخیره</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><?= admin_h(($member['joined_date_fa'] ?? '') ?: '—') ?></td>
                        <td>
                            <?php if ($isOwner): ?>
                                <span class="admin-muted">مالک پروژه</span>
                            <?php elseif (!$isArchived): ?>
                                <form method="post" action="<?= admin_h($projectUrl . '/members/' . $memberId . '/remove') ?>" onsubmit="return confirm('این عضو از پروژه خارج شود؟');">
                                    <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                                    <button class="admin-button admin-button--soft admin-button--compact" type="submit">حذف از پروژه</button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
