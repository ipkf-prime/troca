<?php

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$form = $form ?? [];
$options = $options ?? [];
$references = $references ?? [];
$errors = $errors ?? [];
$isEdit = (bool) ($isEdit ?? false);
$action = $isEdit
    ? '/admin/automation/correspondences/' . rawurlencode((string) ($form['public_reference'] ?? '')) . '/versions'
    : '/admin/automation/correspondences';

$select = static function (string $name, array $items, string $selected): string {
    $html = '<select name="' . admin_h($name) . '">';
    foreach ($items as $item) {
        $code = (string) ($item['code'] ?? '');
        $html .= '<option value="' . admin_h($code) . '"' . ($code === $selected ? ' selected' : '') . '>'
            . admin_h($item['label'] ?? $code)
            . '</option>';
    }

    return $html . '</select>';
};

$partySelect = static function (string $name, array $items, string $selected, string $emptyLabel): string {
    $html = '<select name="' . admin_h($name) . '"><option value="">' . admin_h($emptyLabel) . '</option>';
    foreach ($items as $item) {
        $code = (string) ($item['code'] ?? '');
        $html .= '<option value="' . admin_h($code) . '"' . ($code === $selected ? ' selected' : '') . '>'
            . admin_h($item['label'] ?? $code)
            . '</option>';
    }

    return $html . '</select>';
};

$referenceSelect = static function (string $selected = '') use ($references): string {
    $html = '<select name="party_reference_token[]"><option value="">انتخاب مرجع داخلی</option>';
    foreach (['users' => 'کاربران', 'persons' => 'اشخاص', 'organizations' => 'سازمان‌ها', 'org_units' => 'واحدها'] as $group => $label) {
        if (($references[$group] ?? []) === []) {
            continue;
        }
        $html .= '<optgroup label="' . admin_h($label) . '">';
        foreach ($references[$group] as $item) {
            $token = (string) ($item['token'] ?? '');
            $html .= '<option value="' . admin_h($token) . '"' . ($token === $selected ? ' selected' : '') . '>'
                . admin_h($item['label'] ?? '')
                . '</option>';
        }
        $html .= '</optgroup>';
    }

    return $html . '</select>';
};

$arrayValue = static function (array $values, int $index, string $default = ''): string {
    return (string) ($values[$index] ?? $default);
};

$storedParties = array_values(is_array($form['parties'] ?? null) ? $form['parties'] : []);
$partyRoles = array_values(is_array($form['party_role_code'] ?? null) ? $form['party_role_code'] : []);
$partyKinds = array_values(is_array($form['party_kind'] ?? null) ? $form['party_kind'] : []);
$partyTokens = array_values(is_array($form['party_reference_token'] ?? null) ? $form['party_reference_token'] : []);
$externalNames = array_values(is_array($form['external_display_name'] ?? null) ? $form['external_display_name'] : []);
$externalOrganizations = array_values(is_array($form['external_organization_name'] ?? null) ? $form['external_organization_name'] : []);
$externalContacts = array_values(is_array($form['external_contact_or_address'] ?? null) ? $form['external_contact_or_address'] : []);
$documentTemplates = array_values(is_array($options['document_templates'] ?? null) ? $options['document_templates'] : []);
$selectedTemplateReference = trim((string) ($form['document_template_reference'] ?? ''));
if ($selectedTemplateReference === '' && $documentTemplates !== []) {
    $selectedTemplateReference = (string) ($documentTemplates[0]['public_reference'] ?? '');
}
$externalDateFa = trim((string) ($form['external_date_fa'] ?? ''));
if ($externalDateFa === '') {
    $externalDateFa = \App\Support\PersianDate::fromGregorianDate((string) ($form['external_date'] ?? ''));
}

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/automation">اتوماسیون</a><span>/</span>
    <a href="/admin/automation/correspondences">مکاتبات</a><span>/</span>
    <span><?= $isEdit ? 'ویرایش پیش‌نویس' : 'ایجاد پیش‌نویس' ?></span>
</nav>

<section class="admin-module-hub admin-module-hub--teal admin-users-heading">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></div>
    <div>
        <h2><?= $isEdit ? 'ویرایش پیش‌نویس مکاتبه' : 'ایجاد پیش‌نویس مکاتبه' ?></h2>
        <p>اطلاعات پایه، متن و طرف‌های مکاتبه را ثبت کنید.</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/automation/correspondences">بازگشت به فهرست</a>
</section>

<?php if (($editable ?? true) !== true): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--warning">این مکاتبه دیگر پیش‌نویس قابل ویرایش نیست.</div></section>
<?php else: ?>
    <?php if ($errors !== []): ?>
        <div class="admin-alert admin-alert--danger" role="alert">اطلاعات فرم کامل یا معتبر نیست. فیلدهای ضروری، تاریخ و طرف‌های مکاتبه را بررسی کنید.</div>
    <?php endif; ?>

    <form class="automation-form automation-form--structured automation-tab-workspace" method="post" action="<?= admin_h($action) ?>" data-automation-draft-tabs>
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="lock_version" value="<?= admin_h($form['lock_version'] ?? 0) ?>"><?php endif; ?>

        <nav class="automation-draft-tabs" role="tablist" aria-label="مراحل ایجاد پیش‌نویس">
            <button type="button" class="automation-draft-tab" data-draft-tab="base" role="tab"><b>۱</b><span>اطلاعات پایه</span></button>
            <button type="button" class="automation-draft-tab" data-draft-tab="content" role="tab"><b>۲</b><span>متن مکاتبه</span></button>
            <button type="button" class="automation-draft-tab" data-draft-tab="parties" role="tab"><b>۳</b><span>فرستنده و گیرندگان</span></button>
            <button type="button" class="automation-draft-tab" data-draft-tab="review" role="tab"><b>۴</b><span>مرور و ثبت</span></button>
        </nav>

        <section class="automation-form-section automation-draft-panel" data-draft-panel="base" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>اطلاعات پایه</h3><p>مشخصات عمومی و طبقه‌بندی مکاتبه</p></div></div>
            <div class="admin-form-grid automation-base-grid">
                <label class="admin-form-grid__wide"><span>موضوع</span><input name="subject" value="<?= admin_h($form['subject'] ?? '') ?>" maxlength="500" required></label>
                <fieldset class="automation-template-picker admin-form-grid__wide">
                    <legend>قالب استاندارد نامه</legend>
                    <p>اندازه صفحه، زبان، هدر، فوتر و محل امضا با انتخاب قالب تثبیت می‌شود.</p>
                    <div class="automation-template-picker__grid">
                        <?php foreach ($documentTemplates as $template):
                            $reference = (string) ($template['public_reference'] ?? '');
                            $checked = $reference === $selectedTemplateReference;
                        ?>
                            <label class="automation-template-option">
                                <input type="radio" name="document_template_reference" value="<?= admin_h($reference) ?>" <?= $checked ? 'checked' : '' ?> required>
                                <span class="automation-template-option__page automation-template-option__page--<?= strtolower(admin_h($template['page_size_code'] ?? 'a4')) ?>"><i></i><i></i><i></i></span>
                                <strong><?= admin_h($template['title_fa'] ?? '') ?></strong>
                                <small><?= admin_h(($template['page_size_code'] ?? '') . ' · ' . (($template['language_code'] ?? '') === 'fa' ? 'فارسی' : 'English') . ' · ' . ($template['signature_slots'] ?? 1) . ' امضا · نسخه ' . ($template['version_number'] ?? 1)) ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <label><span>نوع یا جهت مکاتبه</span><?= $select('direction_code', $options['directions'] ?? [], (string) ($form['direction_code'] ?? 'incoming')) ?></label>
                <label><span>اولویت</span><?= $select('priority_code', $options['priorities'] ?? [], (string) ($form['priority_code'] ?? 'normal')) ?></label>
                <label><span>محرمانگی</span><?= $select('confidentiality_code', $options['confidentialities'] ?? [], (string) ($form['confidentiality_code'] ?? 'normal')) ?></label>
                <label><span>کانال</span><?= $select('channel_code', $options['channels'] ?? [], (string) ($form['channel_code'] ?? 'manual')) ?></label>
                <label><span>شماره بیرونی</span><input name="external_number" value="<?= admin_h($form['external_number'] ?? '') ?>" maxlength="190"></label>
                <label><span>تاریخ بیرونی</span><div class="admin-persian-date" data-persian-datepicker><input type="text" name="external_date_fa" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="۱۴۰۵/۰۴/۲۷" value="<?= admin_h($externalDateFa) ?>"><input type="hidden" name="external_date" data-persian-date-output value="<?= admin_h($form['external_date'] ?? '') ?>"><button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ"><?= \App\Support\AdminIcon::html('calendar') ?></button></div></label>
                <label class="admin-form-grid__wide"><span>خلاصه</span><textarea name="summary" rows="3" maxlength="2000"><?= admin_h($form['summary'] ?? '') ?></textarea></label>
            </div>
            <div class="automation-draft-navigation"><button class="admin-button" type="button" data-draft-next="content">ادامه: متن مکاتبه</button></div>
        </section>

        <section class="automation-form-section automation-draft-panel" data-draft-panel="content" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>متن مکاتبه</h3><p>محتوای نسخه جاری پیش‌نویس</p></div></div>
            <label class="automation-form__wide"><span>متن یا محتوای نسخه جاری</span><textarea name="content" rows="10" maxlength="8000" required><?= admin_h($form['content'] ?? '') ?></textarea></label>
            <?php if ($isEdit): ?><label class="automation-form__wide"><span>یادداشت تغییر</span><input name="change_note" maxlength="500" placeholder="شرح کوتاه تغییرات این نسخه"></label><?php endif; ?>
            <div class="automation-draft-navigation"><button class="admin-button admin-button--soft" type="button" data-draft-next="base">قبلی</button><button class="admin-button" type="button" data-draft-next="parties">ادامه: طرف‌های مکاتبه</button></div>
        </section>

        <section class="automation-form-section automation-party-editor automation-draft-panel" data-draft-panel="parties" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>طرف‌های مکاتبه</h3><p>حداقل یک طرف تعریف کنید. فیلدهای هر طرف متناسب با نوع انتخابی نمایش داده می‌شوند.</p></div></div>
            <div class="automation-party-list">
                <?php for ($index = 0; $index < 3; $index++):
                    $stored = $storedParties[$index] ?? [];
                    $role = $arrayValue($partyRoles, $index, (string) ($stored['party_role_code'] ?? ($index === 0 ? 'sender' : '')));
                    $kind = $arrayValue($partyKinds, $index, (string) ($stored['target_kind_code'] ?? ($index === 0 ? 'external' : '')));
                    $tokenValue = $arrayValue($partyTokens, $index, (string) ($stored['reference_token'] ?? ''));
                    $nameValue = $arrayValue($externalNames, $index, (string) ($stored['external_display_name'] ?? ''));
                    $organizationValue = $arrayValue($externalOrganizations, $index, (string) ($stored['external_organization_name'] ?? ''));
                    $contactValue = $arrayValue($externalContacts, $index, (string) ($stored['external_contact_or_address'] ?? ''));
                ?>
                    <details class="automation-party-card" data-automation-party <?= $index === 0 ? 'open' : '' ?>>
                        <summary><span>طرف <?= admin_h(\App\Support\AdminFormat::digits($index + 1)) ?></span><small data-party-summary></small></summary>
                        <div class="automation-party-row">
                            <label><span>نقش طرف</span><?= $partySelect('party_role_code[]', $options['party_roles'] ?? [], $role, 'انتخاب نقش') ?></label>
                            <label><span>نوع طرف</span><?= $partySelect('party_kind[]', $options['party_kinds'] ?? [], $kind, 'انتخاب نوع طرف') ?></label>
                            <label data-party-internal><span>مرجع داخلی</span><?= $referenceSelect($tokenValue) ?></label>
                            <label data-party-external><span>نام طرف بیرونی</span><input name="external_display_name[]" value="<?= admin_h($nameValue) ?>" maxlength="255" placeholder="نام شخص یا نماینده"></label>
                            <label data-party-external><span>سازمان بیرونی</span><input name="external_organization_name[]" value="<?= admin_h($organizationValue) ?>" maxlength="255"></label>
                            <label class="automation-party-row__wide" data-party-external><span>نشانی یا تماس بیرونی</span><input name="external_contact_or_address[]" value="<?= admin_h($contactValue) ?>" maxlength="1000"></label>
                        </div>
                    </details>
                <?php endfor; ?>
            </div>
            <div class="automation-draft-navigation"><button class="admin-button admin-button--soft" type="button" data-draft-next="content">قبلی</button><button class="admin-button" type="button" data-draft-next="review">ادامه: مرور و ثبت</button></div>
        </section>

        <section class="automation-form-section automation-draft-panel automation-draft-review" data-draft-panel="review" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>مرور و ثبت</h3><p>پیش از ثبت، اطلاعات اصلی پیش‌نویس را مرور کنید.</p></div></div>
            <div class="automation-draft-review__grid">
                <div><span>موضوع</span><strong data-draft-review="subject">—</strong></div>
                <div><span>نوع مکاتبه</span><strong data-draft-review="direction_code">—</strong></div>
                <div><span>اولویت</span><strong data-draft-review="priority_code">—</strong></div>
                <div><span>قالب نامه</span><strong data-draft-review="document_template_reference">—</strong></div>
                <div><span>طرف‌های تکمیل‌شده</span><strong data-draft-review="parties">۰</strong></div>
            </div>
            <div class="admin-alert admin-alert--info admin-alert--compact">ثبت نهایی در این مرحله، پیش‌نویس را ایجاد می‌کند؛ گردش کار و شماره ثبت رسمی هنوز آغاز نمی‌شود.</div>
            <div class="admin-form-actions automation-form-actions automation-draft-navigation">
                <button class="admin-button admin-button--soft" type="button" data-draft-next="parties">قبلی</button>
                <button class="admin-button" type="submit"><?= $isEdit ? 'ذخیره نسخه جدید' : 'ایجاد پیش‌نویس' ?></button>
                <a class="admin-button admin-button--soft" href="/admin/automation/correspondences">انصراف</a>
            </div>
        </section>
    </form>
<?php endif; ?>
<script>
(function () {
    const form = document.querySelector('[data-automation-draft-tabs]');
    if (!form) return;

    const tabs = [...form.querySelectorAll('[data-draft-tab]')];
    const panels = [...form.querySelectorAll('[data-draft-panel]')];
    const names = tabs.map((tab) => tab.dataset.draftTab);

    function review() {
        const value = (name) => form.elements[name]?.value?.trim() || '—';
        const selected = (name) => {
            const field = form.elements[name];
            return field?.selectedOptions?.[0]?.textContent?.trim() || '—';
        };
        const set = (name, valueText) => {
            const target = form.querySelector('[data-draft-review="' + name + '"]');
            if (target) target.textContent = valueText;
        };
        const roles = [...form.querySelectorAll('[name="party_role_code[]"]')];
        const kinds = [...form.querySelectorAll('[name="party_kind[]"]')];
        const count = roles.filter((role, index) => role.value && kinds[index]?.value).length;
        set('subject', value('subject'));
        set('direction_code', selected('direction_code'));
        set('priority_code', selected('priority_code'));
        const template = form.querySelector('[name="document_template_reference"]:checked');
        set('document_template_reference', template?.closest('label')?.querySelector('strong')?.textContent?.trim() || '—');
        set('parties', String(count).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]));
    }

    function activate(name, focusTab = false) {
        if (!names.includes(name)) name = 'base';
        tabs.forEach((tab) => {
            const active = tab.dataset.draftTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.setAttribute('tabindex', active ? '0' : '-1');
            if (active && focusTab) tab.focus();
        });
        panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.draftPanel === name));
        if (name === 'review') review();
        try { history.replaceState(null, '', '#draft-' + name); } catch (error) {}
    }

    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.draftTab)));
    form.querySelectorAll('[data-draft-next]').forEach((button) => button.addEventListener('click', () => {
        activate(button.dataset.draftNext, true);
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    form.addEventListener('invalid', (event) => {
        const panel = event.target.closest('[data-draft-panel]');
        if (panel) activate(panel.dataset.draftPanel);
    }, true);
    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            const invalid = form.querySelector(':invalid');
            const panel = invalid?.closest('[data-draft-panel]');
            if (panel) activate(panel.dataset.draftPanel);
            invalid?.focus();
        }
    });

    const requested = location.hash.startsWith('#draft-') ? location.hash.slice(7) : 'base';
    activate(requested);
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
