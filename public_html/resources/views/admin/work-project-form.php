<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$form = $form ?? [];
$options = $options ?? [];
$errors = $errors ?? [];
$isEdit = (bool) ($isEdit ?? false);
$projectReference = (string) ($form['public_reference'] ?? '');
$formAction = $isEdit
    ? '/admin/work/projects/' . rawurlencode($projectReference)
    : '/admin/work/projects';
$formHeading = $isEdit ? 'ویرایش پروژه' : 'ایجاد پروژه';
$startDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($form['start_date'] ?? ''));
$targetDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($form['target_date'] ?? ''));

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <a href="/admin/work/projects">پروژه‌ها</a><span>/</span>
    <span><?= admin_h($formHeading) ?></span>
</nav>

<section class="admin-module-hub admin-module-hub--green">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('organization') ?></div>
    <div>
        <h2><?= admin_h($formHeading) ?></h2>
        <p>اطلاعات پایه، زمان‌بندی، وضعیت و سطح دسترسی پروژه را مشخص کنید.</p>
    </div>
    <a class="admin-module-hub__back" href="<?= $isEdit ? admin_h('/admin/work/projects/' . rawurlencode($projectReference)) : '/admin/work/projects' ?>">بازگشت</a>
</section>

<?php if ($errors !== []): ?>
    <section class="admin-section">
        <div class="admin-alert admin-alert--danger" role="alert">
            <strong>ثبت اطلاعات انجام نشد.</strong>
            <ul>
                <?php foreach ($errors as $fieldError): ?>
                    <li><?= admin_h($fieldError) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<section class="admin-section">
    <form method="post" action="<?= admin_h($formAction) ?>">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
        <div class="admin-form-grid">
            <label class="admin-form-grid__wide">
                <span>عنوان پروژه</span>
                <input name="title" value="<?= admin_h($form['title'] ?? '') ?>" maxlength="255" required autofocus>
            </label>

            <label>
                <span>کد پروژه</span>
                <input name="code" value="<?= admin_h($form['code'] ?? '') ?>" maxlength="80" dir="ltr" placeholder="example-project" required>
                <small class="admin-muted">حروف انگلیسی کوچک، عدد و خط تیره</small>
            </label>

            <label>
                <span>وضعیت</span>
                <select name="status_code">
                    <?php foreach (($options['statuses'] ?? []) as $statusCode => $statusLabel): ?>
                        <option value="<?= admin_h($statusCode) ?>"<?= (string) ($form['status_code'] ?? 'active') === $statusCode ? ' selected' : '' ?>>
                            <?= admin_h($statusLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>سطح دسترسی</span>
                <select name="visibility_code">
                    <?php foreach (($options['visibilities'] ?? []) as $visibilityCode => $visibilityLabel): ?>
                        <option value="<?= admin_h($visibilityCode) ?>"<?= (string) ($form['visibility_code'] ?? 'private') === $visibilityCode ? ' selected' : '' ?>>
                            <?= admin_h($visibilityLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>عنوان سازمان</span>
                <input name="organization_snapshot" value="<?= admin_h($form['organization_snapshot'] ?? '') ?>" maxlength="255" placeholder="اختیاری">
            </label>

            <label>
                <span>شناسه سازمان</span>
                <input name="organization_reference" value="<?= admin_h($form['organization_reference'] ?? '') ?>" maxlength="100" dir="ltr" placeholder="اختیاری">
            </label>

            <label>
                <span>تاریخ شروع</span>
                <div class="admin-persian-date" data-persian-datepicker>
                    <input
                        type="text"
                        name="start_date_fa"
                        data-persian-date-input
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="۱۴۰۵/۰۵/۰۸"
                        value="<?= admin_h($startDateFa) ?>"
                    >
                    <input type="hidden" name="start_date" data-persian-date-output value="<?= admin_h($form['start_date'] ?? '') ?>">
                    <button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ شروع"><?= \App\Support\AdminIcon::html('calendar') ?></button>
                </div>
            </label>

            <label>
                <span>تاریخ هدف</span>
                <div class="admin-persian-date" data-persian-datepicker>
                    <input
                        type="text"
                        name="target_date_fa"
                        data-persian-date-input
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="۱۴۰۵/۰۶/۳۱"
                        value="<?= admin_h($targetDateFa) ?>"
                    >
                    <input type="hidden" name="target_date" data-persian-date-output value="<?= admin_h($form['target_date'] ?? '') ?>">
                    <button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ هدف"><?= \App\Support\AdminIcon::html('calendar') ?></button>
                </div>
            </label>

            <label class="admin-form-grid__wide">
                <span>شرح پروژه</span>
                <textarea name="description" rows="7" maxlength="20000" placeholder="هدف، محدوده و خروجی مورد انتظار پروژه"><?= admin_h($form['description'] ?? '') ?></textarea>
            </label>
        </div>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit"><?= $isEdit ? 'ذخیره تغییرات' : 'ایجاد پروژه' ?></button>
            <a class="admin-button admin-button--soft" href="<?= $isEdit ? admin_h('/admin/work/projects/' . rawurlencode($projectReference)) : '/admin/work/projects' ?>">انصراف</a>
        </div>
    </form>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
