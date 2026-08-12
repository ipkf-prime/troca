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
$errorMessages = [
    'subject_required' =>
        'موضوع مکاتبه الزامی است.',
    'content_required' =>
        'متن نامه الزامی است.',
    'document_template_required' =>
        'انتخاب قالب استاندارد نامه الزامی است.',
    'invalid_lookup' =>
        'یکی از گزینه‌های انتخاب‌شده در فرم معتبر نیست؛ لطفاً دوباره انتخاب کنید.',
    'organization_required' =>
        'سازمان فعال کاربر برای ثبت مکاتبه مشخص نیست.',
    'sender_required' =>
        'فرستنده مکاتبه مشخص نشده است.',
    'receiver_required' =>
        'حداقل یک گیرنده اصلی باید مشخص شود.',
    'party_required' =>
        'حداقل یک طرف مکاتبه باید مشخص شود.',
    'external_party_required' =>
        'نام طرف بیرونی مکاتبه باید وارد شود.',
    'invalid_core_reference' =>
        'مرجع داخلی انتخاب‌شده معتبر نیست؛ لطفاً طرف داخلی را دوباره انتخاب کنید.',
    'invalid_party_role' =>
        'نقش یکی از طرف‌های مکاتبه معتبر نیست.',
    'incoming_sender_must_be_external' =>
        'فرستنده نامه وارده باید بیرون از سازمان باشد.',
    'incoming_receiver_must_be_internal' =>
        'گیرنده نامه وارده باید داخل سازمان باشد.',
    'outgoing_sender_must_be_internal' =>
        'فرستنده نامه صادره باید داخل سازمان باشد.',
    'outgoing_receiver_must_be_external' =>
        'گیرنده نامه صادره باید بیرون از سازمان باشد.',
    'internal_parties_must_be_internal' =>
        'در نامه داخلی، فرستنده و گیرندگان باید داخل سازمان باشند.',
    'invalid_relation' =>
        'اطلاعات عطف، پیرو یا ارتباط با نامه قبلی کامل یا معتبر نیست.',
    'invalid_date' =>
        'تاریخ واردشده معتبر نیست.',
    'not_editable' =>
        'این مکاتبه دیگر در وضعیت قابل ویرایش نیست.',
    'forbidden_scope' =>
        'شما به این مکاتبه در محدوده سازمانی فعلی دسترسی ندارید.',
    'stale_update' =>
        'این پیش‌نویس هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی و دوباره بررسی کنید.',
    'runtime_unavailable' =>
        'ثبت اطلاعات به دلیل خطای داخلی سامانه انجام نشد؛ دوباره تلاش کنید.',
    'invalid' =>
        'بخشی از اطلاعات فرم کامل یا معتبر نیست.',
];

$errorLabels = [];

foreach ($errors as $error) {
    $code = trim((string) $error);

    $errorLabels[] =
        $errorMessages[$code]
        ?? 'بخشی از اطلاعات فرم کامل یا معتبر نیست.';
}

$errorLabels =
    array_values(
        array_unique(
            $errorLabels
        )
    );

$baseErrorCodes = [
    'subject_required',
    'document_template_required',
    'invalid_lookup',
    'organization_required',
    'invalid_date',
];

$contentErrorCodes = [
    'content_required',
];

$partyErrorCodes = [
    'sender_required',
    'receiver_required',
    'party_required',
    'external_party_required',
    'invalid_core_reference',
    'invalid_party_role',
    'incoming_sender_must_be_external',
    'incoming_receiver_must_be_internal',
    'outgoing_sender_must_be_internal',
    'outgoing_receiver_must_be_external',
    'internal_parties_must_be_internal',
    'invalid_relation',
];

$initialErrorTab = '';

foreach ($errors as $error) {
    if (in_array((string) $error, $baseErrorCodes, true)) {
        $initialErrorTab = 'base';
        break;
    }
}

if ($initialErrorTab === '') {
    foreach ($errors as $error) {
        if (in_array((string) $error, $contentErrorCodes, true)) {
            $initialErrorTab = 'content';
            break;
        }
    }
}

if ($initialErrorTab === '') {
    foreach ($errors as $error) {
        if (in_array((string) $error, $partyErrorCodes, true)) {
            $initialErrorTab = 'parties';
            break;
        }
    }
}
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

$referenceSelect = static function (
    string $selected = ''
) use ($references): string {
    $html =
        '<select name="party_reference_token[]">'
        . '<option value="">انتخاب مرجع داخلی</option>';

    $groups = [
        'users' => 'کاربر',
        'persons' => 'شخص',
        'organizations' => 'سازمان',
        'org_units' => 'واحد',
    ];

    foreach ($groups as $group => $prefix) {
        foreach (
            $references[$group] ?? []
            as $item
        ) {
            $token =
                (string) (
                    $item['token']
                    ?? ''
                );

            $label =
                trim(
                    (string) (
                        $item['label']
                        ?? ''
                    )
                );

            if (
                $token === ''
                || $label === ''
            ) {
                continue;
            }

            $display =
                $prefix
                . ' — '
                . $label;

            $html .=
                '<option value="'
                . admin_h($token)
                . '"'
                . (
                    $token === $selected
                        ? ' selected'
                        : ''
                )
                . '>'
                . admin_h($display)
                . '</option>';
        }
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
$directionLabels = ['incoming' => 'نامه وارده', 'outgoing' => 'نامه صادره', 'internal' => 'نامه داخلی'];
$directionLabel = $directionLabels[$initialDirection] ?? 'مکاتبه';
$visiblePartyCount = max(2, min(6, count($storedParties)));
$selectedTemplateReference =
    trim(
        (string) (
            $form['document_template_reference']
            ?? ''
        )
    );
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
    </div>
    <a class="admin-module-hub__back" href="/admin/automation/correspondences">بازگشت به فهرست</a>
</section>

<?php if (($editable ?? true) !== true): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--warning">این مکاتبه دیگر پیش‌نویس قابل ویرایش نیست.</div></section>
<?php else: ?>
    <?php if ($errorLabels !== []): ?>
        <div class="admin-alert admin-alert--danger" role="alert">
            <?php foreach ($errorLabels as $errorLabel): ?>
                <div><?= admin_h($errorLabel) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="automation-form automation-form--structured automation-tab-workspace" method="post" action="<?= admin_h($action) ?>" data-automation-draft-tabs data-correspondence-direction="<?= admin_h($initialDirection) ?>" data-initial-error-tab="<?= admin_h($initialErrorTab) ?>">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
        <input type="hidden" name="form_public_reference" value="<?=admin_h($form['public_reference']??'')?>">
        <input type="hidden" name="direction_code" value="<?= admin_h($initialDirection) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="lock_version" value="<?= admin_h($form['lock_version'] ?? 0) ?>"><?php endif; ?>

        <nav class="automation-draft-tabs" role="tablist" aria-label="مراحل ایجاد پیش‌نویس">
            <button type="button" class="automation-draft-tab" data-draft-tab="base" role="tab"><b>۱</b><span>اطلاعات پایه</span></button>
            <?php if ($initialDirection !== 'incoming'): ?><button type="button" class="automation-draft-tab" data-draft-tab="content" role="tab"><b>۲</b><span>متن مکاتبه</span></button><?php endif; ?>
            <button type="button" class="automation-draft-tab" data-draft-tab="parties" role="tab"><b><?= $initialDirection === 'incoming' ? '۲' : '۳' ?></b><span>فرستنده و گیرندگان</span></button>
            <button type="button" class="automation-draft-tab" data-draft-tab="review" role="tab"><b><?= $initialDirection === 'incoming' ? '۳' : '۴' ?></b><span>مرور و ثبت</span></button>
        </nav>

        <section class="automation-form-section automation-draft-panel" data-draft-panel="base" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>اطلاعات پایه <span class="admin-status-badge"><?= admin_h($directionLabel) ?></span></h3></div></div>
            <div class="admin-form-grid automation-base-grid">
                <label class="admin-form-grid__wide"><span>موضوع</span><input name="subject" value="<?= admin_h($form['subject'] ?? '') ?>" maxlength="500" required></label>
                <?php if ($initialDirection !== 'incoming'): ?><fieldset class="automation-template-picker admin-form-grid__wide" data-template-picker>
                    <legend>قالب استاندارد نامه</legend>

                    <div class="admin-form-grid__wide">
                        <a
                            class="admin-button admin-button--soft"
                            href="/admin/automation/templates"
                            target="_blank"
                            rel="noopener"
                        >
                            مشاهده قالب‌های استاندارد
                        </a>
                    </div>

                    <label><span>انتخاب قالب</span><select name="document_template_reference" required data-template-select><option value="">انتخاب قالب نامه</option><?php foreach ($documentTemplates as $template): $reference=(string)($template['public_reference']??''); ?><option value="<?=admin_h($reference)?>" data-page="<?=admin_h($template['page_size_code']??'')?>" data-language="<?=admin_h(($template['language_code']??'')==='fa'?'فارسی':'انگلیسی')?>" data-signatures="<?=admin_h($template['signature_slots']??1)?>" data-version="<?=admin_h($template['version_number']??1)?>" <?= $reference===$selectedTemplateReference?'selected':'' ?>><?=admin_h($template['title_fa']??'')?></option><?php endforeach;?></select></label>
                    <div class="automation-template-summary" data-template-summary>یک قالب را انتخاب کنید.</div>
                </fieldset><?php endif; ?>
                <label><span>اولویت</span><?= $select('priority_code', $options['priorities'] ?? [], (string) ($form['priority_code'] ?? 'normal')) ?></label>
                <label><span>محرمانگی</span><?= $select('confidentiality_code', $options['confidentialities'] ?? [], (string) ($form['confidentiality_code'] ?? 'normal')) ?></label>
                <label><span>کانال</span><?= $select('channel_code', $options['channels'] ?? [], (string) ($form['channel_code'] ?? 'manual')) ?></label>
                <?php if ($initialDirection === 'incoming'): ?><label><span>شماره نامه بیرونی</span><input name="external_number" value="<?= admin_h($form['external_number'] ?? '') ?>" maxlength="190"></label>
                <label><span>تاریخ نامه بیرونی</span><div class="admin-persian-date" data-persian-datepicker><input type="text" name="external_date_fa" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="مثلاً ۱۴۰۵/۰۱/۰۱" value="<?= admin_h($externalDateFa) ?>"><input type="hidden" name="external_date" data-persian-date-output value="<?= admin_h($form['external_date'] ?? '') ?>"><button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ"><?= \App\Support\AdminIcon::html('calendar') ?></button></div></label><?php endif; ?>
                <label class="admin-form-grid__wide"><span>خلاصه</span><textarea name="summary" rows="3" maxlength="2000"><?= admin_h($form['summary'] ?? '') ?></textarea></label>
            </div>
            <div class="automation-draft-navigation"><button class="admin-button" type="button" data-draft-next="<?= $initialDirection === 'incoming' ? 'parties' : 'content' ?>">ادامه</button></div>
        </section>

        <?php if ($initialDirection !== 'incoming'): ?><section class="automation-form-section automation-draft-panel" data-draft-panel="content" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>متن مکاتبه</h3></div></div>
            <label class="automation-form__wide"><span>متن نامه</span><textarea name="content" rows="10" maxlength="8000" required><?= admin_h($form['content'] ?? '') ?></textarea></label>
            <?php if ($isEdit): ?><label class="automation-form__wide"><span>یادداشت تغییر</span><input name="change_note" maxlength="500" placeholder="شرح کوتاه تغییرات این نسخه"></label><?php endif; ?>
            <div class="automation-draft-navigation"><button class="admin-button admin-button--soft" type="button" data-draft-next="base">قبلی</button><button class="admin-button" type="button" data-draft-next="parties">ادامه: طرف‌های مکاتبه</button></div>
        </section><?php endif; ?>

        <section class="automation-form-section automation-party-editor automation-draft-panel" data-draft-panel="parties" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>فرستنده و گیرندگان</h3></div></div>
            <div class="automation-form-section__head">
                <div>
                    <h4>گیرندگان اصلی، رونوشت و رونوشت مخفی</h4>
                    <p class="admin-muted">
                        برای هر مخاطب، نقش او در مکاتبه را مشخص کنید.
                    </p>
                </div>
            </div>

            <div class="automation-party-list">
                <?php for ($index = 0; $index < 6; $index++):
                    $stored = $storedParties[$index] ?? [];
                    $defaultRole = $index === 0 ? 'sender' : ($index < $visiblePartyCount ? 'primary_recipient' : '');
                    $role = $arrayValue($partyRoles, $index, (string) ($stored['party_role_code'] ?? $defaultRole));
                    $defaultKind = '';
                    if ($index < $visiblePartyCount) {
                        $defaultKind = $initialDirection === 'internal'
                            ? 'person'
                            : (($initialDirection === 'incoming' && $index === 0) || ($initialDirection === 'outgoing' && $index === 1) ? 'external' : 'person');
                    }
                    $kind = $arrayValue($partyKinds, $index, (string) ($stored['target_kind_code'] ?? $defaultKind));
                    $inputKind = $kind !== ''
                        ? $kind
                        : ($initialDirection === 'internal'
                            ? 'person'
                            : (($initialDirection === 'incoming' && $index === 0) || ($initialDirection === 'outgoing' && $index > 0) ? 'external' : 'person'));
                    $tokenValue = $arrayValue($partyTokens, $index, (string) ($stored['reference_token'] ?? ''));
                    $nameValue = $arrayValue($externalNames, $index, (string) ($stored['external_display_name'] ?? ''));
                    $organizationValue = $arrayValue($externalOrganizations, $index, (string) ($stored['external_organization_name'] ?? ''));
                    $contactValue = $arrayValue($externalContacts, $index, (string) ($stored['external_contact_or_address'] ?? ''));
                    $partyTitle =
                        $index === 0
                            ? 'فرستنده'
                            : match ($role) {
                                'cc' => 'رونوشت',
                                'bcc' => 'رونوشت مخفی',
                                default =>
                                    'گیرنده'
                                    . (
                                        $index > 1
                                            ? ' '
                                            . \App\Support\AdminFormat::digits(
                                                $index
                                            )
                                            : ''
                                    ),
                            };
                ?>
                    <article class="automation-party-card automation-party-card--simple" data-automation-party data-recipient-row="<?= admin_h($index) ?>" <?= $index >= $visiblePartyCount ? 'hidden' : '' ?>>
                        <header><strong data-party-title><?= admin_h($partyTitle) ?></strong><?php if ($index > 1): ?><button type="button" class="automation-recipient-remove" data-remove-recipient aria-label="حذف گیرنده">×</button><?php endif; ?></header>
                        <div class="automation-party-row">
                            <?php if ($index === 0): ?>
                                <input
                                    type="hidden"
                                    name="party_role_code[]"
                                    value="sender"
                                >
                            <?php else: ?>
                                <label>
                                    <span>نقش گیرنده</span>
                                    <select
                                        name="party_role_code[]"
                                        data-party-role
                                    >
                                        <option
                                            value="primary_recipient"
                                            <?= $role === 'primary_recipient'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            گیرنده اصلی
                                        </option>

                                        <option
                                            value="cc"
                                            <?= $role === 'cc'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            رونوشت
                                        </option>

                                        <option
                                            value="bcc"
                                            <?= $role === 'bcc'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            رونوشت مخفی
                                        </option>
                                    </select>
                                </label>
                            <?php endif; ?>

                            <input
                                type="hidden"
                                name="party_kind[]"
                                value="<?= admin_h($kind) ?>"
                            >
                            <?php if ($inputKind === 'external'): ?>
                                <input type="hidden" name="party_reference_token[]" value="">
                                <label data-party-external><span><?= $index === 0 ? 'نام فرستنده یا نماینده' : 'نام گیرنده یا نماینده' ?></span><input name="external_display_name[]" value="<?= admin_h($nameValue) ?>" maxlength="255" placeholder="نام شخص یا نماینده"></label>
                                <label data-party-external><span>سازمان بیرونی</span><input name="external_organization_name[]" value="<?= admin_h($organizationValue) ?>" maxlength="255"></label>
                                <label class="automation-party-row__wide" data-party-external><span>نشانی یا تماس بیرونی</span><input name="external_contact_or_address[]" value="<?= admin_h($contactValue) ?>" maxlength="1000"></label>
                            <?php else: ?>
                                <label class="automation-party-row__wide" data-party-internal><span><?= $index === 0 ? 'فرستنده داخلی' : 'گیرنده داخلی' ?></span><?= $referenceSelect($tokenValue) ?></label>
                                <input type="hidden" name="external_display_name[]" value="">
                                <input type="hidden" name="external_organization_name[]" value="">
                                <input type="hidden" name="external_contact_or_address[]" value="">
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endfor; ?>
            </div>
            <button class="admin-button admin-button--soft automation-add-recipient" type="button" data-add-recipient><span aria-hidden="true">+</span> افزودن گیرنده</button>
            <div class="automation-form-section__head"><div><h3>عطف، پیرو و ارتباط با نامه‌های قبلی</h3></div></div>
            <div class="automation-party-list">
                <?php for($index=0;$index<2;$index++): $relation=$storedRelations[$index]??[]; ?>
                <div class="automation-party-row">
                    <label><span>نوع ارتباط</span><select name="relation_type_code[]"><option value="">بدون ارتباط</option><?php foreach($options['relation_types']??[] as $item):$code=(string)($item['code']??'');?><option value="<?=admin_h($code)?>" <?= $code===(string)($relation['relation_type_code']??'')?'selected':'' ?>><?=admin_h($item['label']??$code)?></option><?php endforeach;?></select></label>
                    <label><span>نامه مرجع</span><select name="related_correspondence_reference[]"><option value="">انتخاب نامه</option><?php foreach($relatedCorrespondences as $item):$ref=(string)($item['public_reference']??'');?><option value="<?=admin_h($ref)?>" <?= $ref===(string)($relation['target_public_reference']??'')?'selected':'' ?>><?=admin_h(($item['subject']??'بدون موضوع').' — '.($item['external_number']?:$ref))?></option><?php endforeach;?></select></label>
                    <label class="automation-party-row__wide"><span>توضیح ارتباط</span><input name="relation_note[]" maxlength="1000" value="<?=admin_h($relation['note']??'')?>" placeholder="مثلاً عطف به نامه شماره ..."></label>
                </div>
                <?php endfor; ?>
            </div>
            <div class="automation-draft-navigation"><button class="admin-button admin-button--soft" type="button" data-draft-next="<?= $initialDirection === 'incoming' ? 'base' : 'content' ?>">قبلی</button><button class="admin-button" type="button" data-draft-next="review">ادامه: مرور و ثبت</button></div>
        </section>

        <section class="automation-form-section automation-draft-panel automation-draft-review" data-draft-panel="review" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>مرور و ثبت</h3></div></div>
            <div class="automation-draft-review__grid">
                <div><span>موضوع</span><strong data-draft-review="subject">—</strong></div>
                <div><span>نوع مکاتبه</span><strong data-draft-review="direction_code">—</strong></div>
                <div><span>اولویت</span><strong data-draft-review="priority_code">—</strong></div>
                <div><span>قالب نامه</span><strong data-draft-review="document_template_reference">—</strong></div>
                <div><span>طرف‌های تکمیل‌شده</span><strong data-draft-review="parties">۰</strong></div>
            </div>
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
        const count = [
            ...form.querySelectorAll(
                '[data-automation-party]'
            )
        ].filter((row) => {
            if (row.hidden) {
                return false;
            }

            const role = row.querySelector(
                '[name="party_role_code[]"]'
            )?.value?.trim();

            const kind = row.querySelector(
                '[name="party_kind[]"]'
            )?.value?.trim();

            if (!role || !kind) {
                return false;
            }

            if (kind === 'external') {
                return Boolean(
                    row.querySelector(
                        '[name="external_display_name[]"]'
                    )?.value?.trim()
                );
            }

            return Boolean(
                row.querySelector(
                    '[name="party_reference_token[]"]'
                )?.value?.trim()
            );
        }).length;
        set('subject', value('subject'));
        const directionLabels = { incoming: 'نامه وارده', outgoing: 'نامه صادره', internal: 'نامه داخلی' };
        set('direction_code', directionLabels[form.elements.direction_code?.value] || '—');
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

    const errorTab = form.dataset.initialErrorTab || '';
    const requested = errorTab || (
        location.hash.startsWith('#draft-')
            ? location.hash.slice(7)
            : 'base'
    );
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

    const direction = form.dataset.correspondenceDirection || 'incoming';
    const recipientRows = [...form.querySelectorAll('[data-recipient-row]')];
    const addRecipient = form.querySelector('[data-add-recipient]');

    const syncPartyTitle = (row) => {
        if (!row) return;

        const index = Number(
            row.dataset.recipientRow || 0
        );

        const title = row.querySelector(
            '[data-party-title]'
        );

        if (!title) return;

        if (index === 0) {
            title.textContent = 'فرستنده';
            return;
        }

        const role = row.querySelector(
            '[name="party_role_code[]"]'
        )?.value;

        if (role === 'cc') {
            title.textContent = 'رونوشت';
            return;
        }

        if (role === 'bcc') {
            title.textContent = 'رونوشت مخفی';
            return;
        }

        const digits = String(index).replace(
            /\d/g,
            (digit) =>
                '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]
        );

        title.textContent =
            index > 1
                ? 'گیرنده ' + digits
                : 'گیرنده';
    };

    form.querySelectorAll(
        '[data-party-role]'
    ).forEach((field) => {
        field.addEventListener(
            'change',
            () => syncPartyTitle(
                field.closest('[data-recipient-row]')
            )
        );
    });

    recipientRows.forEach(syncPartyTitle);
    const fixedKind = (index) => {
        if (direction === 'internal') return 'person';
        if (direction === 'incoming') return index === 0 ? 'external' : 'person';
        return index === 0 ? 'person' : 'external';
    };
    const syncAddButton = () => {
        if (addRecipient) addRecipient.hidden = !recipientRows.some((row, index) => index > 1 && row.hidden);
    };
    addRecipient?.addEventListener('click', () => {
        const row = recipientRows.find((candidate, index) => index > 1 && candidate.hidden);
        if (!row) return;
        const index = Number(row.dataset.recipientRow || 0);
        row.hidden = false;
        const role = row.querySelector('[name="party_role_code[]"]');
        const kind = row.querySelector('[name="party_kind[]"]');
        if (role) {
            role.value = 'primary_recipient';
        }

        syncPartyTitle(row);

        if (kind) {
            kind.value = fixedKind(index);
            kind.dispatchEvent(new Event('change'));
        }
        row.querySelector('[data-party-internal] select, [data-party-external] input')?.focus();
        syncAddButton();
    });
    form.querySelectorAll('[data-remove-recipient]').forEach((button) => button.addEventListener('click', () => {
        const row = button.closest('[data-recipient-row]');
        if (!row) return;
        row.querySelectorAll('input').forEach((field) => field.value = '');
        row.querySelectorAll('select').forEach((field) => field.value = '');
        row.hidden = true;
        syncAddButton();
    }));
    syncAddButton();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
