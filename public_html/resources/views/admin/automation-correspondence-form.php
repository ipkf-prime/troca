<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}
$form = $form ?? [];
$options = $options ?? [];
$references = $references ?? [];
$errors = $errors ?? [];
$isEdit = (bool) ($isEdit ?? false);
$action = $isEdit ? '/admin/automation/correspondences/' . rawurlencode((string) ($form['public_reference'] ?? '')) . '/versions' : '/admin/automation/correspondences';
$select = static function (string $name, array $items, string $selected): string {
    $html = '<select name="' . admin_h($name) . '">';
    foreach ($items as $item) {
        $code = (string) ($item['code'] ?? '');
        $html .= '<option value="' . admin_h($code) . '"' . ($code === $selected ? ' selected' : '') . '>' . admin_h($item['label'] ?? $code) . '</option>';
    }
    return $html . '</select>';
};
$referenceSelect = static function (int $index) use ($references): string {
    $html = '<select name="party_reference_token[]"><option value="">انتخاب مرجع داخلی</option>';
    foreach (['users' => 'کاربران', 'persons' => 'اشخاص', 'organizations' => 'سازمان‌ها', 'org_units' => 'واحدها'] as $group => $label) {
        if (($references[$group] ?? []) === []) { continue; }
        $html .= '<optgroup label="' . admin_h($label) . '">';
        foreach ($references[$group] as $item) {
            $html .= '<option value="' . admin_h($item['token'] ?? '') . '">' . admin_h($item['label'] ?? '') . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html . '</select>';
};
ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb"><a href="/admin/dashboard">داشبورد</a><span>/</span><a href="/admin/automation">اتوماسیون</a><span>/</span><a href="/admin/automation/correspondences">مکاتبات</a><span>/</span><span><?= $isEdit ? 'ویرایش پیش نویس' : 'ایجاد پیش نویس' ?></span></nav>
<section class="admin-module-hub admin-module-hub--teal admin-users-heading"><div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></div><div><h2><?= $isEdit ? 'ویرایش پیش نویس مکاتبه' : 'ایجاد پیش نویس مکاتبه' ?></h2><p>ثبت اطلاعات پایه، طرف‌ها و نسخه immutable مکاتبه</p></div><a class="admin-module-hub__back" href="/admin/automation/correspondences">بازگشت به فهرست</a></section>
<?php if (($editable ?? true) !== true): ?><section class="admin-section"><div class="admin-alert">این مکاتبه دیگر پیش نویس قابل ویرایش نیست.</div></section><?php else: ?>
<section class="admin-section">
    <?php if ($errors !== []): ?><div class="admin-alert">اطلاعات فرم کامل یا معتبر نیست. لطفاً فیلدهای ضروری و طرف‌های مکاتبه را بررسی کنید.</div><?php endif; ?>
    <form class="automation-form" method="post" action="<?= admin_h($action) ?>">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="lock_version" value="<?= admin_h($form['lock_version'] ?? 0) ?>"><?php endif; ?>
        <div class="admin-form-grid">
            <label><span>موضوع</span><input name="subject" value="<?= admin_h($form['subject'] ?? '') ?>" maxlength="500" required></label>
            <label><span>نوع/جهت مکاتبه</span><?= $select('direction_code', $options['directions'] ?? [], (string) ($form['direction_code'] ?? 'incoming')) ?></label>
            <label><span>اولویت</span><?= $select('priority_code', $options['priorities'] ?? [], (string) ($form['priority_code'] ?? 'normal')) ?></label>
            <label><span>محرمانگی</span><?= $select('confidentiality_code', $options['confidentialities'] ?? [], (string) ($form['confidentiality_code'] ?? 'normal')) ?></label>
            <label><span>کانال</span><?= $select('channel_code', $options['channels'] ?? [], (string) ($form['channel_code'] ?? 'manual')) ?></label>
            <label><span>شماره بیرونی</span><input name="external_number" value="<?= admin_h($form['external_number'] ?? '') ?>" maxlength="190"></label>
            <label><span>تاریخ بیرونی</span><input type="date" name="external_date" value="<?= admin_h($form['external_date'] ?? '') ?>"></label>
        </div>
        <label class="automation-form__wide"><span>خلاصه</span><textarea name="summary" rows="3" maxlength="2000"><?= admin_h($form['summary'] ?? '') ?></textarea></label>
        <label class="automation-form__wide"><span>متن/محتوای نسخه جاری</span><textarea name="content" rows="8" maxlength="8000" required><?= admin_h($form['content'] ?? '') ?></textarea></label>
        <?php if ($isEdit): ?><label class="automation-form__wide"><span>یادداشت تغییر</span><input name="change_note" maxlength="500" placeholder="مثلاً اصلاح متن پیش نویس"></label><?php endif; ?>
        <section class="automation-party-editor"><div class="admin-section__header"><div><h3>طرف‌های مکاتبه</h3><p class="admin-muted">حداقل یک طرف تعریف کنید. برای طرف بیرونی، نام نمایشی الزامی است.</p></div></div>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="automation-party-row">
                    <label><span>نقش طرف</span><?= $select('party_role_code[]', $options['party_roles'] ?? [], $i === 0 ? 'sender' : 'primary_recipient') ?></label>
                    <label><span>نوع طرف</span><?= $select('party_kind[]', $options['party_kinds'] ?? [], $i === 0 ? 'external' : 'user') ?></label>
                    <label><span>مرجع داخلی</span><?= $referenceSelect($i) ?></label>
                    <label><span>نام طرف بیرونی</span><input name="external_display_name[]" maxlength="255" placeholder="نام شخص یا نماینده"></label>
                    <label><span>سازمان بیرونی</span><input name="external_organization_name[]" maxlength="255"></label>
                    <label class="automation-party-row__wide"><span>نشانی/تماس بیرونی</span><input name="external_contact_or_address[]" maxlength="1000"></label>
                </div>
            <?php endfor; ?>
        </section>
        <div class="admin-form-actions"><button class="admin-button" type="submit"><?= $isEdit ? 'ذخیره نسخه جدید' : 'ایجاد پیش نویس' ?></button><a class="admin-button admin-button--soft" href="/admin/automation/correspondences">انصراف</a></div>
    </form>
</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
