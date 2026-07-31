<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$project = $project ?? [];
$form = $form ?? [];
$options = $options ?? [];
$errors = $errors ?? [];
$isEdit = (bool) ($isEdit ?? false);
$projectReference = (string) ($project['public_reference'] ?? '');
$itemReference = (string) ($form['public_reference'] ?? '');
$baseUrl = '/admin/work/projects/' . rawurlencode($projectReference) . '/items';
$formAction = $isEdit
    ? $baseUrl . '/' . rawurlencode($itemReference)
    : $baseUrl;
$formHeading = $isEdit ? 'ویرایش آیتم' : 'ایجاد آیتم';
$startDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($form['start_date'] ?? ''));
$dueDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($form['due_date'] ?? ''));

ob_start();
require __DIR__ . '/work-ui-styles.php';
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <a href="/admin/work/projects">پروژه‌ها</a><span>/</span>
    <a href="<?= admin_h('/admin/work/projects/' . rawurlencode($projectReference)) ?>"><?= admin_h($project['title'] ?? '') ?></a><span>/</span>
    <a href="<?= admin_h($baseUrl) ?>">کارها و تسک‌ها</a><span>/</span>
    <span><?= admin_h($formHeading) ?></span>
</nav>

<section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('circle-check') ?></div>
    <div>
        <h2><?= admin_h($formHeading) ?></h2>
        <p><?= admin_h($project['title'] ?? '') ?></p>
    </div>
    <a class="admin-module-hub__back" href="<?= admin_h($baseUrl) ?>">بازگشت</a>
</section>

<?php if ($errors !== []): ?>
    <section class="admin-section">
        <div class="admin-alert admin-alert--danger" role="alert">
            <strong>ثبت اطلاعات انجام نشد.</strong>
            <ul>
                <?php foreach ($errors as $error): ?><li><?= admin_h($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<section class="admin-section work-compact-section">
    <form method="post" action="<?= admin_h($formAction) ?>">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">

        <div class="work-item-form-grid">
            <label class="work-item-field-wide">
                <span>عنوان</span>
                <input name="title" value="<?= admin_h($form['title'] ?? '') ?>" maxlength="500" required autofocus>
            </label>

            <label>
                <span>نوع آیتم</span>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="item_type" value="<?= admin_h($form['item_type'] ?? 'task') ?>">
                    <select disabled>
                        <option><?= admin_h(($options['types'][$form['item_type'] ?? 'task'] ?? 'تسک')) ?></option>
                    </select>
                <?php else: ?>
                    <select name="item_type" required>
                        <?php foreach (($options['types'] ?? []) as $code => $label): ?>
                            <option value="<?= admin_h($code) ?>"<?= (string) ($form['item_type'] ?? 'task') === $code ? ' selected' : '' ?>><?= admin_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </label>

            <label>
                <span>والد</span>
                <select name="parent_reference">
                    <option value="">بدون والد</option>
                    <?php foreach (($options['parents'] ?? []) as $parent): ?>
                        <option value="<?= admin_h($parent['public_reference'] ?? '') ?>"<?= (string) ($form['parent_reference'] ?? '') === (string) ($parent['public_reference'] ?? '') ? ' selected' : '' ?>>
                            <?= admin_h(($options['types'][$parent['item_type'] ?? ''] ?? '') . ' — ' . ($parent['title'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>وضعیت</span>
                <select name="status_code" required>
                    <?php foreach (($options['statuses'] ?? []) as $code => $label): ?>
                        <option value="<?= admin_h($code) ?>"<?= (string) ($form['status_code'] ?? 'planned') === $code ? ' selected' : '' ?>><?= admin_h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>اولویت</span>
                <select name="priority_code">
                    <?php foreach (($options['priorities'] ?? []) as $code => $label): ?>
                        <option value="<?= admin_h($code) ?>"<?= (string) ($form['priority_code'] ?? 'normal') === $code ? ' selected' : '' ?>><?= admin_h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>مسئول</span>
                <select name="assignee_reference">
                    <option value="">بدون مسئول</option>
                    <?php foreach (($options['members'] ?? []) as $member): ?>
                        <option value="<?= admin_h($member['user_reference'] ?? '') ?>"<?= (string) ($form['assignee_reference'] ?? '') === (string) ($member['user_reference'] ?? '') ? ' selected' : '' ?>>
                            <?= admin_h($member['display_name_snapshot'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>درصد پیشرفت</span>
                <input type="number" name="progress_percent" min="0" max="100" value="<?= admin_h($form['progress_percent'] ?? 0) ?>">
            </label>

            <label>
                <span>تاریخ شروع</span>
                <div class="admin-persian-date" data-persian-datepicker>
                    <input type="text" name="start_date_fa" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="۱۴۰۵/۰۵/۰۸" value="<?= admin_h($startDateFa) ?>">
                    <input type="hidden" name="start_date" data-persian-date-output value="<?= admin_h($form['start_date'] ?? '') ?>">
                    <button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ شروع"><?= \App\Support\AdminIcon::html('calendar') ?></button>
                </div>
            </label>

            <label>
                <span>تاریخ سررسید</span>
                <div class="admin-persian-date" data-persian-datepicker>
                    <input type="text" name="due_date_fa" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="۱۴۰۵/۰۶/۳۱" value="<?= admin_h($dueDateFa) ?>">
                    <input type="hidden" name="due_date" data-persian-date-output value="<?= admin_h($form['due_date'] ?? '') ?>">
                    <button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ سررسید"><?= \App\Support\AdminIcon::html('calendar') ?></button>
                </div>
            </label>

            <label>
                <span>برآورد زمان (دقیقه)</span>
                <input type="number" name="estimate_minutes" min="0" max="1000000" value="<?= admin_h($form['estimate_minutes'] ?? '') ?>" placeholder="اختیاری">
            </label>
        </div>

        <details class="work-form-more"<?= trim((string) ($form['description'] ?? '')) !== '' ? ' open' : '' ?>>
            <summary>شرح و جزئیات</summary>
            <label>
                <span>شرح</span>
                <textarea name="description" rows="5" maxlength="20000" placeholder="شرح، خروجی مورد انتظار و نکات اجرایی"><?= admin_h($form['description'] ?? '') ?></textarea>
            </label>
        </details>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit"><?= $isEdit ? 'ذخیره تغییرات' : 'ایجاد آیتم' ?></button>
            <a class="admin-button admin-button--soft" href="<?= admin_h($baseUrl) ?>">انصراف</a>
            <?php if ($isEdit): ?>
                <button class="admin-button work-button--danger" type="submit" formaction="<?= admin_h($baseUrl . '/' . rawurlencode($itemReference) . '/archive') ?>" onclick="return confirm('این آیتم بایگانی شود؟');">بایگانی</button>
            <?php endif; ?>
        </div>
    </form>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';