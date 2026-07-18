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
$relatedCorrespondences = array_values(is_array($options['related_correspondences'] ?? null) ? $options['related_correspondences'] : []);
$storedRelations = array_values(is_array($form['relations'] ?? null) ? $form['relations'] : []);
$initialDirection = (string) ($form['direction_code'] ?? 'incoming');
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
        <input type="hidden" name="form_public_reference" value="<?=admin_h($form['public_reference']??'')?>">
        <?php if ($isEdit): ?><input type="hidden" name="lock_version" value="<?= admin_h($form['lock_version'] ?? 0) ?>"><?php endif; ?>

        <nav class="automation-draft-tabs" role="tablist" aria-label="مراحل ایجاد پیش‌نویس">
            <button type="button" class="automation-draft-tab" data-draft-tab="base" role="tab"><b>۱</b><span>اطلاعات پایه</span></button>
            <button type="button" class="automation-draft-tab" data-draft-tab="content" role="tab"><b>۲</b><span>متن مکاتبه</span></button>
            <button type="button" class="automation-draft-tab" data-draft-tab="parties" role="tab"><b>۳</b><span>فرستنده و گیرندگان</span></button>
            <button type="button" class="automation-draft-tab" data-draft-tab="review" role="tab"><b>۴</b><span>مرور و ثبت</span></button>
        </nav>

        <section class="automation-form-section automation-draft-panel" data-draft-panel="base" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>اطلاعات پایه</h3><p>ابتدا نوع مکاتبه را تعیین کنید؛ فرم و کنترل‌ها متناسب با آن تنظیم می‌شوند.</p></div></div>
            <div class="admin-form-grid automation-base-grid">
                <label class="admin-form-grid__wide automation-direction-picker"><span>نوع مکاتبه</span><?= $select('direction_code', $options['directions'] ?? [], (string) ($form['direction_code'] ?? 'incoming')) ?><small data-direction-help></small></label>
                <label class="admin-form-grid__wide"><span>موضوع</span><input name="subject" value="<?= admin_h($form['subject'] ?? '') ?>" maxlength="500" required></label>
                <fieldset class="automation-template-picker admin-form-grid__wide" data-template-picker data-direction-section="document">
                    <legend>قالب استاندارد نامه</legend>
                    <p>قالب را از فهرست انتخاب کنید؛ اندازه، زبان، هدر، فوتر و محل امضا با نسخه قالب تثبیت می‌شود. <a href="/admin/automation/templates">مشاهده فهرست قالب‌ها</a></p>
                    <label><span>انتخاب قالب</span><select name="document_template_reference" required data-template-select><option value="">انتخاب قالب نامه</option><?php foreach ($documentTemplates as $template): $reference=(string)($template['public_reference']??''); ?><option value="<?=admin_h($reference)?>" data-page="<?=admin_h($template['page_size_code']??'')?>" data-language="<?=admin_h(($template['language_code']??'')==='fa'?'فارسی':'انگلیسی')?>" data-signatures="<?=admin_h($template['signature_slots']??1)?>" data-version="<?=admin_h($template['version_number']??1)?>" <?= $reference===$selectedTemplateReference?'selected':'' ?>><?=admin_h($template['title_fa']??'')?></option><?php endforeach;?></select></label>
                    <div class="automation-template-summary" data-template-summary>یک قالب را انتخاب کنید.</div>
                </fieldset>
                <label><span>اولویت</span><?= $select('priority_code', $options['priorities'] ?? [], (string) ($form['priority_code'] ?? 'normal')) ?></label>
                <label><span>محرمانگی</span><?= $select('confidentiality_code', $options['confidentialities'] ?? [], (string) ($form['confidentiality_code'] ?? 'normal')) ?></label>
                <label><span>کانال</span><?= $select('channel_code', $options['channels'] ?? [], (string) ($form['channel_code'] ?? 'manual')) ?></label>
                <label data-direction-section="external"><span>شماره نامه بیرونی</span><input name="external_number" value="<?= admin_h($form['external_number'] ?? '') ?>" maxlength="190"></label>
                <label data-direction-section="external"><span>تاریخ نامه بیرونی</span><div class="admin-persian-date" data-persian-datepicker><input type="text" name="external_date_fa" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="۱۴۰۵/۰۴/۲۷" value="<?= admin_h($externalDateFa) ?>"><input type="hidden" name="external_date" data-persian-date-output value="<?= admin_h($form['external_date'] ?? '') ?>"><button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ"><?= \App\Support\AdminIcon::html('calendar') ?></button></div></label>
                <label class="admin-form-grid__wide"><span>خلاصه</span><textarea name="summary" rows="3" maxlength="2000"><?= admin_h($form['summary'] ?? '') ?></textarea></label>
            </div>
            <div class="automation-draft-navigation"><button class="admin-button" type="button" data-draft-next="content">ادامه: محتوای مکاتبه</button></div>
        </section>

        <section class="automation-form-section automation-draft-panel" data-draft-panel="content" role="tabpanel">
            <div class="automation-form-section__head"><div><h3 data-content-title>متن مکاتبه</h3><p data-content-help>محتوای نسخه جاری پیش‌نویس</p></div></div>
            <div class="admin-alert admin-alert--info admin-alert--compact" data-incoming-scan-note>نامه وارده از روی اصل نامه ثبت می‌شود؛ پس از ایجاد رکورد، تصویر یا PDF نامه را در تب «پیوست‌ها» بارگذاری کنید. قالب و متن تایپی برای وارده الزامی نیست.</div>
            <label class="automation-form__wide" data-direction-section="content"><span>متن یا محتوای نسخه جاری</span><textarea name="content" rows="10" maxlength="8000"><?= admin_h($form['content'] ?? '') ?></textarea></label>
            <?php if ($isEdit): ?><label class="automation-form__wide"><span>یادداشت تغییر</span><input name="change_note" maxlength="500" placeholder="شرح کوتاه تغییرات این نسخه"></label><?php endif; ?>
            <div class="automation-draft-navigation"><button class="admin-button admin-button--soft" type="button" data-draft-next="base">قبلی</button><button class="admin-button" type="button" data-draft-next="parties">ادامه: طرف‌های مکاتبه</button></div>
        </section>

        <section class="automation-form-section automation-party-editor automation-draft-panel" data-draft-panel="parties" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>طرف‌های مکاتبه</h3><p>حداقل یک طرف تعریف کنید. فیلدهای هر طرف متناسب با نوع انتخابی نمایش داده می‌شوند.</p></div></div>
            <div class="automation-party-list">
                <?php for ($index = 0; $index < 6; $index++):
                    $stored = $storedParties[$index] ?? [];
                    $defaultRole = $index === 0 ? 'sender' : ($index === 1 ? 'primary_recipient' : '');
                    $role = $arrayValue($partyRoles, $index, (string) ($stored['party_role_code'] ?? $defaultRole));
                    $defaultKind = '';
                    if ($index < 2) {
                        $defaultKind = $initialDirection === 'internal'
                            ? 'person'
                            : (($initialDirection === 'incoming' && $index === 0) || ($initialDirection === 'outgoing' && $index === 1) ? 'external' : 'person');
                    }
                    $kind = $arrayValue($partyKinds, $index, (string) ($stored['target_kind_code'] ?? $defaultKind));
                    $tokenValue = $arrayValue($partyTokens, $index, (string) ($stored['reference_token'] ?? ''));
                    $nameValue = $arrayValue($externalNames, $index, (string) ($stored['external_display_name'] ?? ''));
                    $organizationValue = $arrayValue($externalOrganizations, $index, (string) ($stored['external_organization_name'] ?? ''));
                    $contactValue = $arrayValue($externalContacts, $index, (string) ($stored['external_contact_or_address'] ?? ''));
                ?>
                    <details class="automation-party-card" data-automation-party <?= $index === 0 ? 'open' : '' ?>>
                        <summary><span>طرف <?= admin_h(\App\Support\AdminFormat::digits($index + 1)) ?></span><small data-party-summary></small></summary>
                        <div class="automation-party-row">
                            <label><span>نقش طرف</span><?= $partySelect('party_role_code[]', $options['party_roles'] ?? [], $role, 'انتخاب نقش') ?></label>
                            <label><span>نوع طرف</span><?= $partySelect('party_kind[]', $options['party_kinds'] ?? [], $kind, 'انتخاب نوع طرف') ?><small data-party-rule></small></label>
                            <label data-party-internal><span>مرجع داخلی</span><?= $referenceSelect($tokenValue) ?></label>
                            <label data-party-external><span>نام طرف بیرونی</span><input name="external_display_name[]" value="<?= admin_h($nameValue) ?>" maxlength="255" placeholder="نام شخص یا نماینده"></label>
                            <label data-party-external><span>سازمان بیرونی</span><input name="external_organization_name[]" value="<?= admin_h($organizationValue) ?>" maxlength="255"></label>
                            <label class="automation-party-row__wide" data-party-external><span>نشانی یا تماس بیرونی</span><input name="external_contact_or_address[]" value="<?= admin_h($contactValue) ?>" maxlength="1000"></label>
                        </div>
                    </details>
                <?php endfor; ?>
            </div>
            <div class="automation-form-section__head"><div><h3>عطف، پیرو و ارتباط با نامه‌های قبلی</h3><p>شماره و تاریخ نامه مرجع پس از انتخاب، در خروجی نامه قابل استفاده خواهد بود.</p></div></div>
            <div class="automation-party-list">
                <?php for($index=0;$index<2;$index++): $relation=$storedRelations[$index]??[]; ?>
                <div class="automation-party-row">
                    <label><span>نوع ارتباط</span><select name="relation_type_code[]"><option value="">بدون ارتباط</option><?php foreach($options['relation_types']??[] as $item):$code=(string)($item['code']??'');?><option value="<?=admin_h($code)?>" <?= $code===(string)($relation['relation_type_code']??'')?'selected':'' ?>><?=admin_h($item['label']??$code)?></option><?php endforeach;?></select></label>
                    <label><span>نامه مرجع</span><select name="related_correspondence_reference[]"><option value="">انتخاب نامه</option><?php foreach($relatedCorrespondences as $item):$ref=(string)($item['public_reference']??'');?><option value="<?=admin_h($ref)?>" <?= $ref===(string)($relation['target_public_reference']??'')?'selected':'' ?>><?=admin_h(($item['subject']??'بدون موضوع').' — '.($item['external_number']?:$ref))?></option><?php endforeach;?></select></label>
                    <label class="automation-party-row__wide"><span>توضیح ارتباط</span><input name="relation_note[]" maxlength="1000" value="<?=admin_h($relation['note']??'')?>" placeholder="مثلاً عطف به نامه شماره ..."></label>
                </div>
                <?php endfor; ?>
            </div>
            <div class="admin-alert admin-alert--info admin-alert--compact" data-party-direction-help>رونوشت و رونوشت مخفی از فهرست «نقش طرف» در همین بخش انتخاب می‌شوند. پیوست فایل پس از ایجاد پیش‌نویس، در تب «پیوست‌ها» افزوده می‌شود.</div>
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
        set('document_template_reference', selected('document_template_reference'));
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

    const templateSelect = form.querySelector('[data-template-select]');
    const templateSummary = form.querySelector('[data-template-summary]');
    function templatePreview() {
        const option = templateSelect?.selectedOptions?.[0];
        if (!templateSummary || !option?.value) { if (templateSummary) templateSummary.textContent='یک قالب را انتخاب کنید.'; return; }
        templateSummary.textContent = [option.dataset.page, option.dataset.language, option.dataset.signatures + ' امضا', 'نسخه ' + option.dataset.version].join(' · ');
    }
    templateSelect?.addEventListener('change', templatePreview);
    templatePreview();

    const directionSelect = form.elements.direction_code;
    const contentField = form.elements.content;
    const incomingScanNote = form.querySelector('[data-incoming-scan-note]');
    const directionHelp = form.querySelector('[data-direction-help]');
    const contentTitle = form.querySelector('[data-content-title]');
    const contentHelp = form.querySelector('[data-content-help]');
    const partyDirectionHelp = form.querySelector('[data-party-direction-help]');

    const directionCopy = {
        incoming: {
            help: 'وارده: اصل نامه بیرونی و تصویر/PDF آن ثبت می‌شود؛ قالب و متن تایپی لازم نیست.',
            contentTitle: 'تصویر نامه وارده',
            contentHelp: 'پس از ایجاد رکورد، اصل نامه اسکن‌شده را در پیوست‌ها بارگذاری کنید.',
            parties: 'در نامه وارده، فرستنده باید بیرونی و گیرنده باید از داخل سازمان باشد.'
        },
        outgoing: {
            help: 'صادره: متن با قالب استاندارد تولید می‌شود؛ فرستنده داخلی و گیرنده بیرونی است.',
            contentTitle: 'متن نامه صادره',
            contentHelp: 'متن نسخه جاری که با قالب انتخاب‌شده برای چاپ یا PDF ترکیب می‌شود.',
            parties: 'در نامه صادره، فرستنده باید داخلی و گیرنده اصلی باید بیرونی باشد.'
        },
        internal: {
            help: 'داخلی: قالب و متن دارد و همه فرستندگان و گیرندگان باید داخل سازمان باشند.',
            contentTitle: 'متن نامه داخلی',
            contentHelp: 'محتوای نسخه جاری نامه داخلی',
            parties: 'در نامه داخلی، استفاده از شخص یا سازمان بیرونی مجاز نیست.'
        }
    };

    function allowedPartyKinds(direction, role) {
        if (direction === 'internal') return ['person', 'organization', 'org_unit'];
        if (direction === 'incoming' && role === 'sender') return ['external'];
        if (direction === 'incoming' && role === 'primary_recipient') return ['person', 'organization', 'org_unit'];
        if (direction === 'outgoing' && role === 'sender') return ['person', 'organization', 'org_unit'];
        if (direction === 'outgoing' && role === 'primary_recipient') return ['external'];
        return ['external', 'person', 'organization', 'org_unit'];
    }

    function applyPartyRules(direction) {
        form.querySelectorAll('[data-automation-party]').forEach((card) => {
            const role = card.querySelector('[name="party_role_code[]"]');
            const kind = card.querySelector('[name="party_kind[]"]');
            if (!role || !kind) return;
            const allowed = allowedPartyKinds(direction, role.value);
            [...kind.options].forEach((option) => {
                option.disabled = option.value !== '' && !allowed.includes(option.value);
            });
            if (kind.value && !allowed.includes(kind.value)) kind.value = '';
            if (!kind.value && role.value && allowed.length === 1) kind.value = allowed[0];
            const hint = card.querySelector('[data-party-rule]');
            if (hint) {
                hint.textContent = allowed.length === 1 && allowed[0] === 'external'
                    ? 'فقط طرف بیرونی'
                    : (allowed.includes('external') ? '' : 'فقط مرجع داخلی');
            }
            kind.dispatchEvent(new Event('change'));
        });
    }

    function applyDirectionRules() {
        const direction = directionSelect?.value || 'incoming';
        const incoming = direction === 'incoming';
        const internal = direction === 'internal';
        const copy = directionCopy[direction] || directionCopy.incoming;

        form.dataset.correspondenceDirection = direction;
        if (directionHelp) directionHelp.textContent = copy.help;
        if (contentTitle) contentTitle.textContent = copy.contentTitle;
        if (contentHelp) contentHelp.textContent = copy.contentHelp;
        if (partyDirectionHelp) partyDirectionHelp.textContent = copy.parties + ' رونوشت و رونوشت مخفی نیز از نقش طرف انتخاب می‌شوند.';
        if (incomingScanNote) incomingScanNote.hidden = !incoming;

        form.querySelectorAll('[data-direction-section="document"]').forEach((section) => section.hidden = incoming);
        form.querySelectorAll('[data-direction-section="content"]').forEach((section) => section.hidden = incoming);
        form.querySelectorAll('[data-direction-section="external"]').forEach((section) => section.hidden = internal);

        if (templateSelect) {
            templateSelect.required = !incoming;
            templateSelect.disabled = incoming;
        }
        if (contentField) {
            contentField.required = !incoming;
            contentField.disabled = incoming;
        }
        for (const name of ['external_number', 'external_date_fa', 'external_date']) {
            if (form.elements[name]) form.elements[name].disabled = internal;
        }
        applyPartyRules(direction);
    }

    directionSelect?.addEventListener('change', applyDirectionRules);
    form.querySelectorAll('[name="party_role_code[]"]').forEach((role) => role.addEventListener('change', applyDirectionRules));
    applyDirectionRules();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
