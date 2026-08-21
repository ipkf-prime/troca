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

$externalOrganizationReferences =
    array_values(
        is_array(
            $form[
                'external_organization_public_reference'
            ] ?? null
        )
            ? $form[
                'external_organization_public_reference'
            ]
            : []
    );

$externalContactPointReferences =
    array_values(
        is_array(
            $form[
                'external_contact_point_public_reference'
            ] ?? null
        )
            ? $form[
                'external_contact_point_public_reference'
            ]
            : []
    );
$documentTemplates = array_values(is_array($options['document_templates'] ?? null) ? $options['document_templates'] : []);
$relatedCorrespondences = array_values(is_array($options['related_correspondences'] ?? null) ? $options['related_correspondences'] : []);
$storedRelations = array_values(is_array($form['relations'] ?? null) ? $form['relations'] : []);

$externalDirectory =
    array_values(
        is_array(
            $options[
                'external_directory'
            ] ?? null
        )
            ? $options[
                'external_directory'
            ]
            : []
    );
$receiveChannelCodes = [
    'manual',
    'postal',
    'courier',
    'hand_delivery',
    'fax',
    'email',
    'system',
];

$receiveChannels =
    array_values(
        array_filter(
            is_array($options['channels'] ?? null)
                ? $options['channels']
                : [],
            static fn (array $item): bool =>
                in_array(
                    (string) ($item['code'] ?? ''),
                    $receiveChannelCodes,
                    true
                )
        )
    );

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

    <form class="automation-form automation-form--structured automation-tab-workspace" method="post" enctype="multipart/form-data" action="<?= admin_h($action) ?>" data-automation-draft-tabs data-correspondence-direction="<?= admin_h($initialDirection) ?>" data-initial-error-tab="<?= admin_h($initialErrorTab) ?>">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
        <input type="hidden" name="form_public_reference" value="<?=admin_h($form['public_reference']??'')?>">
        <input type="hidden" name="direction_code" value="<?= admin_h($initialDirection) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="lock_version" value="<?= admin_h($form['lock_version'] ?? 0) ?>"><?php endif; ?>

        <nav class="automation-draft-tabs" role="tablist" aria-label="مراحل ایجاد پیش‌نویس">
            <button type="button" class="automation-draft-tab" data-draft-tab="base" role="tab"><b>۱</b><span>اطلاعات پایه</span></button>
            <?php if ($initialDirection !== 'incoming'): ?><button type="button" class="automation-draft-tab" data-draft-tab="content" role="tab"><b>۲</b><span>متن مکاتبه</span></button><?php endif; ?>
            <button type="button" class="automation-draft-tab" data-draft-tab="attachments" role="tab"><b><?= $initialDirection === 'incoming' ? '۲' : '۳' ?></b><span>پیوست‌ها</span></button>
            <button type="button" class="automation-draft-tab" data-draft-tab="parties" role="tab"><b><?= $initialDirection === 'incoming' ? '۳' : '۴' ?></b><span>فرستنده و گیرندگان</span></button>
            <button type="button" class="automation-draft-tab" data-draft-tab="review" role="tab"><b><?= $initialDirection === 'incoming' ? '۴' : '۵' ?></b><span>مرور و ثبت</span></button>
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
                <?php if ($initialDirection === 'incoming'): ?>
                <label>
                    <span>روش دریافت</span>
                    <?= $select(
                        'channel_code',
                        $receiveChannels,
                        (string) ($form['channel_code'] ?? 'manual')
                    ) ?>
                </label>
                <?php endif; ?>
                <?php if ($initialDirection === 'incoming'): ?><label><span>شماره نامه بیرونی</span><input name="external_number" value="<?= admin_h($form['external_number'] ?? '') ?>" maxlength="190"></label>
                <label><span>تاریخ نامه بیرونی</span><div class="admin-persian-date" data-persian-datepicker><input type="text" name="external_date_fa" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="مثلاً ۱۴۰۵/۰۱/۰۱" value="<?= admin_h($externalDateFa) ?>"><input type="hidden" name="external_date" data-persian-date-output value="<?= admin_h($form['external_date'] ?? '') ?>"><button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ"><?= \App\Support\AdminIcon::html('calendar') ?></button></div></label><?php endif; ?>
                <label class="admin-form-grid__wide"><span>خلاصه</span><textarea name="summary" rows="3" maxlength="2000"><?= admin_h($form['summary'] ?? '') ?></textarea></label>
            </div>
            <div class="automation-draft-navigation"><button class="admin-button" type="button" data-draft-next="<?= $initialDirection === 'incoming' ? 'attachments' : 'content' ?>">ادامه</button></div>
        </section>

        <?php if ($initialDirection !== 'incoming'): ?><section class="automation-form-section automation-draft-panel" data-draft-panel="content" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>متن مکاتبه</h3></div></div>
            <?php /* Rich editor owns name="content". */ require __DIR__ . '/partials/automation-correspondence-rich-editor.php'; ?>
            <?php if ($isEdit): ?><label class="automation-form__wide"><span>یادداشت تغییر</span><input name="change_note" maxlength="500" placeholder="شرح کوتاه تغییرات این نسخه"></label><?php endif; ?>
            <div class="automation-draft-navigation"><button class="admin-button admin-button--soft" type="button" data-draft-next="base">قبلی</button><button class="admin-button" type="button" data-draft-next="attachments">ادامه: پیوست‌ها</button></div>
        </section><?php endif; ?>

        <?php /* correspondence-attachment-wizard-v1 */ ?>
        <section
            class="automation-form-section automation-draft-panel automation-attachment-panel"
            data-draft-panel="attachments"
            role="tabpanel"
        >
            <div class="automation-form-section__head">
                <div>
                    <h3>پیوست‌های مکاتبه</h3>
                    <p class="admin-muted">
                        حداکثر ۳ فایل؛ هر فایل ۱۰ و مجموع فایل‌ها ۲۰ مگابایت
                    </p>
                </div>
            </div>

            <div
                class="automation-attachment-dropzone"
                data-attachment-dropzone
            >
                <input
                    type="file"
                    name="attachments[]"
                    accept=".pdf,.docx,.jpg,.jpeg,.png"
                    multiple
                    data-attachment-input
                >

                <div
                    class="automation-attachment-dropzone__icon"
                    aria-hidden="true"
                >
                    <?= \App\Support\AdminIcon::html('file-lines') ?>
                </div>

                <strong>انتخاب فایل‌های پیوست</strong>

                <span>
                    PDF، Word، JPG یا PNG
                </span>

                <button
                    type="button"
                    class="admin-button admin-button--soft"
                    data-attachment-select
                >
                    انتخاب فایل
                </button>
            </div>

            <?php /* attachment-per-file-metadata-v1 */ ?>
            <p class="admin-muted automation-attachment-metadata-help">
                عنوان و نوع هر فایل را پس از انتخاب، در ردیف همان فایل مشخص کنید.
            </p>

            <div
                class="automation-attachment-message admin-alert admin-alert--danger"
                data-attachment-message
                role="alert"
                hidden
            ></div>

            <div
                class="automation-attachment-list"
                data-attachment-list
                aria-live="polite"
            >
                <p class="admin-empty-state">
                    فایلی انتخاب نشده است.
                </p>
            </div>

            <div class="automation-draft-navigation">
                <button
                    class="admin-button admin-button--soft"
                    type="button"
                    data-draft-next="<?= $initialDirection === 'incoming' ? 'base' : 'content' ?>"
                >
                    قبلی
                </button>

                <button
                    class="admin-button"
                    type="button"
                    data-draft-next="parties"
                >
                    ادامه: طرف‌های مکاتبه
                </button>
            </div>
        </section>
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

                    $organizationReferenceValue =
                        $arrayValue(
                            $externalOrganizationReferences,
                            $index,
                            (string) (
                                $stored[
                                    'external_organization_public_reference'
                                ]
                                ?? ''
                            )
                        );

                    $contactPointReferenceValue =
                        $arrayValue(
                            $externalContactPointReferences,
                            $index,
                            (string) (
                                $stored[
                                    'external_contact_point_public_reference'
                                ]
                                ?? ''
                            )
                        );

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
                                <input
                                    type="hidden"
                                    name="party_reference_token[]"
                                    value=""
                                >

                                <?php /* external-recipient-directory-ui-v3b */ ?>

                                <label data-party-external>
                                    <span><?= $index === 0
                                        ? 'نام فرستنده یا نماینده'
                                        : 'نام مخاطب یا نماینده' ?></span>

                                    <input
                                        name="external_display_name[]"
                                        value="<?= admin_h($nameValue) ?>"
                                        maxlength="255"
                                        placeholder="نام شخص، واحد یا نماینده"
                                        data-external-display-name
                                    >
                                </label>

                                <?php if ($initialDirection === 'outgoing'): ?>

                                    <label data-party-external>
                                        <span>سازمان بیرونی</span>

                                        <div
                                            class="automation-external-directory-select-row"
                                            data-external-directory-select-row
                                        >
                                        <select
                                            name="external_organization_public_reference[]"
                                            data-external-directory-organization
                                        >
                                            <option value="">
                                                انتخاب سازمان بیرونی
                                            </option>

                                            <?php foreach ($externalDirectory as $directoryOrganization):
                                                $directoryOrganizationReference =
                                                    (string) (
                                                        $directoryOrganization[
                                                            'public_reference'
                                                        ]
                                                        ?? ''
                                                    );

                                                $directoryOrganizationTitle =
                                                    (string) (
                                                        $directoryOrganization[
                                                            'title'
                                                        ]
                                                        ?? ''
                                                    );
                                            ?>
                                                <option
                                                    value="<?= admin_h($directoryOrganizationReference) ?>"
                                                    data-title="<?= admin_h($directoryOrganizationTitle) ?>"
                                                    <?= $directoryOrganizationReference === $organizationReferenceValue
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= admin_h($directoryOrganizationTitle) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button
                                            type="button"
                                            class="admin-button admin-button--soft automation-external-directory-quick-button"
                                            data-external-directory-quick-open
                                            title="افزودن سریع سازمان بیرونی"
                                        >
                                            + سازمان جدید
                                        </button>
                                        </div>
                                    </label>

                                    <label data-party-external>
                                        <span>مقصد مکاتباتی</span>

                                        <select
                                            name="external_contact_point_public_reference[]"
                                            data-external-directory-point
                                        >
                                            <option value="">
                                                انتخاب مقصد مکاتباتی
                                            </option>

                                            <?php foreach ($externalDirectory as $directoryOrganization):
                                                $directoryOrganizationReference =
                                                    (string) (
                                                        $directoryOrganization[
                                                            'public_reference'
                                                        ]
                                                        ?? ''
                                                    );

                                                foreach (
                                                    $directoryOrganization[
                                                        'contact_points'
                                                    ] ?? []
                                                    as $directoryPoint
                                                ):
                                                    $directoryPointReference =
                                                        (string) (
                                                            $directoryPoint[
                                                                'public_reference'
                                                            ]
                                                            ?? ''
                                                        );

                                                    $directoryPointTitle =
                                                        (string) (
                                                            $directoryPoint[
                                                                'title'
                                                            ]
                                                            ?? ''
                                                        );
                                            ?>
                                                <option
                                                    value="<?= admin_h($directoryPointReference) ?>"
                                                    data-organization-reference="<?= admin_h($directoryOrganizationReference) ?>"
                                                    data-title="<?= admin_h($directoryPointTitle) ?>"
                                                    <?= $directoryPointReference === $contactPointReferenceValue
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= admin_h($directoryPointTitle) ?>
                                                </option>
                                            <?php
                                                endforeach;
                                            endforeach;
                                            ?>
                                        </select>
                                    </label>

                                    <input
                                        type="hidden"
                                        name="external_organization_name[]"
                                        value="<?= admin_h($organizationValue) ?>"
                                        data-external-organization-snapshot
                                    >

                                    <input
                                        type="hidden"
                                        name="external_contact_or_address[]"
                                        value="<?= admin_h($contactValue) ?>"
                                        data-external-contact-snapshot
                                    >

                                <?php else: ?>

                                    <input
                                        type="hidden"
                                        name="external_organization_public_reference[]"
                                        value="<?= admin_h($organizationReferenceValue) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="external_contact_point_public_reference[]"
                                        value="<?= admin_h($contactPointReferenceValue) ?>"
                                    >

                                    <label data-party-external>
                                        <span>سازمان بیرونی</span>

                                        <input
                                            name="external_organization_name[]"
                                            value="<?= admin_h($organizationValue) ?>"
                                            maxlength="255"
                                        >
                                    </label>

                                    <label
                                        class="automation-party-row__wide"
                                        data-party-external
                                    >
                                        <span>نشانی یا تماس بیرونی</span>

                                        <input
                                            name="external_contact_or_address[]"
                                            value="<?= admin_h($contactValue) ?>"
                                            maxlength="1000"
                                        >
                                    </label>

                                <?php endif; ?>

                            <?php else: ?>

                                <label
                                    class="automation-party-row__wide"
                                    data-party-internal
                                >
                                    <span><?= $index === 0
                                        ? 'فرستنده داخلی'
                                        : 'گیرنده داخلی' ?></span>

                                    <?= $referenceSelect($tokenValue) ?>
                                </label>

                                <input
                                    type="hidden"
                                    name="external_display_name[]"
                                    value=""
                                >

                                <input
                                    type="hidden"
                                    name="external_organization_name[]"
                                    value=""
                                >

                                <input
                                    type="hidden"
                                    name="external_contact_or_address[]"
                                    value=""
                                >

                                <input
                                    type="hidden"
                                    name="external_organization_public_reference[]"
                                    value=""
                                >

                                <input
                                    type="hidden"
                                    name="external_contact_point_public_reference[]"
                                    value=""
                                >

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
            <div class="automation-draft-navigation"><button class="admin-button admin-button--soft" type="button" data-draft-next="attachments">قبلی</button><button class="admin-button" type="button" data-draft-next="review">ادامه: مرور و ثبت</button></div>
        </section>

        <section class="automation-form-section automation-draft-panel automation-draft-review" data-draft-panel="review" role="tabpanel">
            <div class="automation-form-section__head"><div><h3>مرور و ثبت</h3></div></div>
            <div class="automation-draft-review__grid">
                <div><span>موضوع</span><strong data-draft-review="subject">—</strong></div>
                <div><span>نوع مکاتبه</span><strong data-draft-review="direction_code">—</strong></div>
                <div><span>اولویت</span><strong data-draft-review="priority_code">—</strong></div>
                <div><span>قالب نامه</span><strong data-draft-review="document_template_reference">—</strong></div>
                <div><span>طرف‌های تکمیل‌شده</span><strong data-draft-review="parties">۰</strong></div>
                <div><span>تعداد پیوست</span><strong data-draft-review="attachments">۰</strong></div>
            </div>
            <div class="admin-form-actions automation-form-actions automation-draft-navigation">
                <button class="admin-button admin-button--soft" type="button" data-draft-next="parties">قبلی</button>
                <button class="admin-button" type="submit"><?= $isEdit ? 'ذخیره نسخه جدید' : 'ایجاد پیش‌نویس' ?></button>
                <a class="admin-button admin-button--soft" href="/admin/automation/correspondences">انصراف</a>
            </div>
        </section>

        <?php if ($initialDirection === 'outgoing'): ?>
            <?php /* external-directory-quick-modal-v3c */ ?>

            <dialog
                class="automation-external-directory-modal"
                data-external-directory-modal
                aria-labelledby="external-directory-modal-title"
            >
                <div class="automation-external-directory-modal__head">
                    <div>
                        <strong id="external-directory-modal-title">
                            افزودن سریع سازمان بیرونی
                        </strong>

                        <p>
                            سازمان و مقصد مکاتباتی را بدون خروج از پیش‌نویس ثبت کنید.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="automation-external-directory-modal__close"
                        data-external-directory-quick-close
                        aria-label="بستن"
                    >
                        ×
                    </button>
                </div>

                <div class="automation-external-directory-modal__body">
                    <div
                        class="automation-external-directory-modal__message"
                        data-external-directory-quick-message
                        hidden
                    ></div>

                    <label>
                        <span>نام رسمی سازمان *</span>

                        <input
                            type="text"
                            maxlength="255"
                            autocomplete="off"
                            placeholder="مثلاً سازمان ... "
                            data-external-directory-quick-title
                        >
                    </label>

                    <label>
                        <span>عنوان کوتاه</span>

                        <input
                            type="text"
                            maxlength="190"
                            autocomplete="off"
                            placeholder="اختیاری"
                            data-external-directory-quick-short-title
                        >
                    </label>

                    <label>
                        <span>مقصد مکاتباتی *</span>

                        <input
                            type="text"
                            maxlength="255"
                            autocomplete="off"
                            placeholder="مثلاً دبیرخانه مرکزی"
                            data-external-directory-quick-point-title
                        >
                    </label>

                    <label>
                        <span>نام رابط / مسئول</span>

                        <input
                            type="text"
                            maxlength="255"
                            autocomplete="off"
                            placeholder="اختیاری"
                            data-external-directory-quick-contact-person
                        >
                    </label>

                    <p class="admin-muted">
                        برای سازمان موجود از فهرست «سازمان بیرونی» همان گیرنده استفاده کنید.
                    </p>
                </div>

                <div class="automation-external-directory-modal__actions">
                    <a
                        class="admin-button admin-button--soft"
                        href="/admin/automation/external-organizations"
                        target="_blank"
                        rel="noopener"
                    >
                        مدیریت کامل سازمان‌ها
                    </a>

                    <div>
                        <button
                            type="button"
                            class="admin-button admin-button--soft"
                            data-external-directory-quick-close
                        >
                            انصراف
                        </button>

                        <button
                            type="button"
                            class="admin-button"
                            data-external-directory-quick-save
                        >
                            ثبت و انتخاب
                        </button>
                    </div>
                </div>
            </dialog>
        <?php endif; ?>

    </form>
<?php endif; ?>

<style>
/* attachment-wizard-single-row-v1 */
.automation-draft-tabs {
    grid-template-columns: none;
    grid-auto-flow: column;
    grid-auto-columns: minmax(0, 1fr);
    gap: 8px;
    overflow-x: auto;
    overflow-y: hidden;
    overscroll-behavior-inline: contain;
    scrollbar-width: thin;
}

.automation-draft-tab {
    min-width: 0;
    padding: 10px 8px;
    gap: 7px;
    font-size: .9rem;
    white-space: nowrap;
}

.automation-draft-tab b {
    width: 25px;
    min-width: 25px;
    height: 25px;
    font-size: .82rem;
}

.automation-draft-tab span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 900px) {
    .automation-draft-tabs {
        grid-auto-columns:
            minmax(150px, 1fr);
    }

    .automation-draft-tab {
        padding-inline: 7px;
        font-size: .84rem;
    }
}
/* correspondence-attachment-wizard-v1 */
.automation-attachment-panel {
    gap: 18px;
}

.automation-attachment-dropzone {
    min-height: 180px;
    border: 2px dashed var(--admin-border);
    border-radius: 16px;
    background: var(--admin-surface);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 24px;
    text-align: center;
    position: relative;
    transition:
        border-color .18s ease,
        background-color .18s ease;
}

.automation-attachment-dropzone.is-dragging {
    border-color: var(--admin-primary);
    background: rgba(15, 139, 123, .08);
}

.automation-attachment-dropzone > input[type="file"] {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    opacity: 0;
    pointer-events: none;
}

.automation-attachment-dropzone__icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    color: var(--admin-primary);
    background: rgba(15, 139, 123, .12);
    display: grid;
    place-items: center;
}

.automation-attachment-dropzone__icon svg {
    width: 25px;
    height: 25px;
}

.automation-attachment-dropzone > span {
    color: var(--admin-muted);
    font-size: .9rem;
}

.automation-attachment-role {
    max-width: 360px;
}

.automation-attachment-list {
    display: grid;
    gap: 10px;
}

.automation-attachment-item {
    display: grid;
    grid-template-columns:
        auto
        minmax(0, 1fr)
        auto;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    background: var(--admin-surface);
}

.automation-attachment-item__icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    color: var(--admin-primary);
    background: rgba(15, 139, 123, .1);
}

.automation-attachment-item__body {
    min-width: 0;
}

.automation-attachment-metadata-help {
    margin: 0;
}

.automation-attachment-item__fields {
    display: grid;
    grid-template-columns:
        minmax(0, 1fr)
        minmax(180px, .65fr);
    gap: 10px;
    margin-top: 10px;
}

.automation-attachment-item__fields label {
    display: grid;
    gap: 5px;
}

.automation-attachment-item__fields label > span {
    margin: 0;
    color: var(--admin-muted);
    font-size: .8rem;
}

.automation-attachment-item__fields input,
.automation-attachment-item__fields select {
    width: 100%;
    min-width: 0;
}

.automation-attachment-item__body strong,
.automation-attachment-item__body span {
    display: block;
}

.automation-attachment-item__body strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.automation-attachment-item__body span {
    color: var(--admin-muted);
    margin-top: 3px;
    font-size: .84rem;
}

.automation-attachment-remove {
    border: 0;
    background: transparent;
    color: #b42318;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
}

.automation-attachment-remove:hover {
    background: rgba(180, 35, 24, .08);
}

@media (max-width: 640px) {
    .automation-attachment-item {
        grid-template-columns:
            auto
            minmax(0, 1fr);
    }

    .automation-attachment-item__fields {
        grid-template-columns: 1fr;
    }

    .automation-attachment-remove {
        grid-column: 1 / -1;
        justify-self: end;
    }
}
.automation-external-directory-select-row {
    display: grid;
    grid-template-columns:
        minmax(0, 1fr)
        auto;
    gap: 8px;
    align-items: center;
}

.automation-external-directory-quick-button {
    white-space: nowrap;
    min-height: 38px;
}

.automation-external-directory-modal {
    width: min(
        620px,
        calc(100vw - 28px)
    );
    border: 0;
    border-radius: 16px;
    padding: 0;
    direction: rtl;
    box-shadow:
        0 24px 70px
        rgba(0, 0, 0, .22);
}

.automation-external-directory-modal::backdrop {
    background:
        rgba(18, 39, 36, .48);
    backdrop-filter: blur(2px);
}

.automation-external-directory-modal__head,
.automation-external-directory-modal__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
}

.automation-external-directory-modal__head {
    border-bottom:
        1px solid
        rgba(0, 0, 0, .08);
}

.automation-external-directory-modal__head strong {
    display: block;
    font-size: 16px;
}

.automation-external-directory-modal__head p {
    margin: 5px 0 0;
    opacity: .72;
    font-size: 12px;
}

.automation-external-directory-modal__close {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 50%;
    cursor: pointer;
    font-size: 22px;
    line-height: 1;
}

.automation-external-directory-modal__body {
    display: grid;
    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );
    gap: 14px;
    padding: 18px;
}

.automation-external-directory-modal__body label {
    display: grid;
    gap: 6px;
}

/*
 * external-directory-quick-modal-full-width-v3c1
 *
 * Keep the quick-create dialog visually compact and
 * predictable: every input occupies the full row.
 */
.automation-external-directory-modal__body label,
.automation-external-directory-modal__message,
.automation-external-directory-modal__body .admin-muted {
    grid-column: 1 / -1;
}

.automation-external-directory-modal__message {
    border-radius: 10px;
    padding: 10px 12px;
    background:
        rgba(176, 50, 50, .09);
}

.automation-external-directory-modal__actions {
    border-top:
        1px solid
        rgba(0, 0, 0, .08);
}

.automation-external-directory-modal__actions > div {
    display: flex;
    gap: 8px;
}

@media (max-width: 720px) {
    .automation-external-directory-select-row {
        grid-template-columns: 1fr;
    }

    .automation-external-directory-modal__body {
        grid-template-columns: 1fr;
    }

    .automation-external-directory-modal__body label {
        grid-column: 1 / -1;
    }

    .automation-external-directory-modal__actions {
        align-items: stretch;
        flex-direction: column-reverse;
    }

    .automation-external-directory-modal__actions > div {
        width: 100%;
    }

    .automation-external-directory-modal__actions button {
        flex: 1;
    }
}
</style>

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
                    ||
                    row.querySelector(
                        '[name="external_organization_public_reference[]"]'
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
        const attachmentCount = form.querySelector('[data-attachment-input]')?.files?.length || 0;
        set('attachments', String(attachmentCount).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]));
    }

    const attachmentInput =
        form.querySelector(
            '[data-attachment-input]'
        );

    const attachmentSelect =
        form.querySelector(
            '[data-attachment-select]'
        );

    const attachmentDropzone =
        form.querySelector(
            '[data-attachment-dropzone]'
        );

    const attachmentList =
        form.querySelector(
            '[data-attachment-list]'
        );

    const attachmentMessage =
        form.querySelector(
            '[data-attachment-message]'
        );

    const attachmentRules = {
        maxFiles: 3,
        maxEach: 10 * 1024 * 1024,
        maxTotal: 20 * 1024 * 1024,
        extensions: [
            'pdf',
            'docx',
            'jpg',
            'jpeg',
            'png',
        ],
    };

    const attachmentFaNumber = (value) =>
        String(value).replace(
            /\d/g,
            (digit) =>
                '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]
        );

    const attachmentFileSize = (bytes) => {
        if (bytes < 1024 * 1024) {
            return attachmentFaNumber(
                Math.max(
                    1,
                    Math.ceil(bytes / 1024)
                )
            ) + ' کیلوبایت';
        }

        return attachmentFaNumber(
            (
                bytes
                / (1024 * 1024)
            ).toFixed(1)
        ) + ' مگابایت';
    };

    const showAttachmentError = (
        message = ''
    ) => {
        if (!attachmentMessage) {
            return;
        }

        attachmentMessage.textContent =
            message;

        attachmentMessage.hidden =
            message === '';
    };

    const validateAttachments = (
        files
    ) => {
        if (
            files.length
            > attachmentRules.maxFiles
        ) {
            return 'حداکثر ۳ فایل قابل انتخاب است.';
        }

        let total = 0;

        for (const file of files) {
            total += file.size;

            const extension =
                file.name
                    .split('.')
                    .pop()
                    ?.toLowerCase()
                || '';

            if (
                !attachmentRules
                    .extensions
                    .includes(extension)
            ) {
                return 'فرمت یکی از فایل‌ها مجاز نیست.';
            }

            if (
                file.size < 1
                || file.size
                    > attachmentRules.maxEach
            ) {
                return 'حجم هر فایل باید حداکثر ۱۰ مگابایت باشد.';
            }
        }

        if (
            total
            > attachmentRules.maxTotal
        ) {
            return 'مجموع حجم فایل‌ها باید حداکثر ۲۰ مگابایت باشد.';
        }

        return '';
    };

    const applyAttachmentFiles = (
        files
    ) => {
        if (
            !attachmentInput
            || typeof DataTransfer
                === 'undefined'
        ) {
            return;
        }

        const transfer =
            new DataTransfer();

        files.forEach(
            (file) =>
                transfer.items.add(file)
        );

        attachmentInput.files =
            transfer.files;

        renderAttachments();
    };

    const attachmentMetadata =
        new Map();

    function renderAttachments() {
        if (
            !attachmentInput
            || !attachmentList
        ) {
            return;
        }

        const files = [
            ...attachmentInput.files
        ];

        const error =
            validateAttachments(files);

        showAttachmentError(error);

        attachmentInput
            .setCustomValidity(error);

        attachmentList
            .replaceChildren();

        if (attachmentSelect) {
            const limitReached =
                files.length
                >= attachmentRules.maxFiles;

            attachmentSelect.disabled =
                limitReached;

            attachmentSelect.textContent =
                limitReached
                    ? 'حداکثر ۳ فایل انتخاب شده'
                    : (
                        files.length === 0
                            ? 'انتخاب فایل'
                            : 'افزودن فایل دیگر'
                    );
        }

        if (files.length === 0) {
            const empty =
                document.createElement('p');

            empty.className =
                'admin-empty-state';

            empty.textContent =
                'فایلی انتخاب نشده است.';

            attachmentList.append(empty);

            return;
        }

        files.forEach(
            (file, index) => {
                const item =
                    document.createElement(
                        'article'
                    );

                item.className =
                    'automation-attachment-item';

                const icon =
                    document.createElement(
                        'span'
                    );

                icon.className =
                    'automation-attachment-item__icon';

                icon.textContent = '📎';

                icon.setAttribute(
                    'aria-hidden',
                    'true'
                );

                const body =
                    document.createElement(
                        'div'
                    );

                body.className =
                    'automation-attachment-item__body';

                const name =
                    document.createElement(
                        'strong'
                    );

                name.textContent =
                    file.name;

                const meta =
                    document.createElement(
                        'span'
                    );

                meta.textContent =
                    attachmentFileSize(
                        file.size
                    );

                body.append(
                    name,
                    meta
                );

                const fields =
                    document.createElement(
                        'div'
                    );

                fields.className =
                    'automation-attachment-item__fields';

                const titleLabel =
                    document.createElement(
                        'label'
                    );

                const titleCaption =
                    document.createElement(
                        'span'
                    );

                titleCaption.textContent =
                    'عنوان پیوست';

                const titleInput =
                    document.createElement(
                        'input'
                    );

                titleInput.type = 'text';
                titleInput.name =
                    'attachment_titles[]';
                titleInput.maxLength = 255;
                titleInput.placeholder =
                    'اختیاری';

                const roleLabel =
                    document.createElement(
                        'label'
                    );

                const roleCaption =
                    document.createElement(
                        'span'
                    );

                roleCaption.textContent =
                    'نوع پیوست';

                const roleSelect =
                    document.createElement(
                        'select'
                    );

                roleSelect.name =
                    'attachment_role_codes[]';

                [
                    [
                        'enclosure',
                        'پیوست',
                    ],
                    [
                        'supporting',
                        'مدرک پشتیبان',
                    ],
                    [
                        'scan',
                        'تصویر اسکن‌شده',
                    ],
                    [
                        'main',
                        'فایل اصلی',
                    ],
                ].forEach(
                    ([
                        value,
                        label,
                    ]) => {
                        const option =
                            document.createElement(
                                'option'
                            );

                        option.value =
                            value;

                        option.textContent =
                            label;

                        roleSelect.append(
                            option
                        );
                    }
                );

                titleLabel.append(
                    titleCaption,
                    titleInput
                );

                roleLabel.append(
                    roleCaption,
                    roleSelect
                );

                fields.append(
                    titleLabel,
                    roleLabel
                );

                body.append(fields);

                const remove =
                    document.createElement(
                        'button'
                    );

                remove.type = 'button';

                remove.className =
                    'automation-attachment-remove';

                remove.textContent = 'حذف';

                remove.setAttribute(
                    'aria-label',
                    'حذف فایل '
                        + file.name
                );

                remove.addEventListener(
                    'click',
                    () => {
                        applyAttachmentFiles(
                            files.filter(
                                (
                                    unused,
                                    fileIndex
                                ) =>
                                    fileIndex
                                    !== index
                            )
                        );
                    }
                );

                const metadataKey =
                    [
                        file.name,
                        file.size,
                        file.lastModified,
                    ].join('|');

                item.dataset
                    .attachmentMetadataKey =
                    metadataKey;

                const previous =
                    attachmentMetadata.get(
                        metadataKey
                    );

                if (previous) {
                    titleInput.value =
                        previous.title;

                    roleSelect.value =
                        previous.role;
                }

                const rememberMetadata = () => {
                    attachmentMetadata.set(
                        metadataKey,
                        {
                            title:
                                titleInput.value,
                            role:
                                roleSelect.value,
                        }
                    );
                };

                titleInput.addEventListener(
                    'input',
                    rememberMetadata
                );

                roleSelect.addEventListener(
                    'change',
                    rememberMetadata
                );

                rememberMetadata();

                item.append(
                    icon,
                    body,
                    remove
                );

                attachmentList.append(
                    item
                );
            }
        );
    }

    /**
     * attachment-incremental-picker-v1
     *
     * Native file inputs replace their existing FileList whenever
     * the file dialog is opened again. Preserve the current files
     * before opening the dialog, then merge them with the new
     * selection.
     */
    let attachmentFilesBeforeDialog = [];

    const attachmentFileIdentity = (
        file
    ) => [
        file.name,
        file.size,
        file.lastModified,
    ].join('|');

    const mergeAttachmentFiles = (
        currentFiles,
        selectedFiles
    ) => {
        const merged = new Map();

        [
            ...currentFiles,
            ...selectedFiles,
        ].forEach(
            (file) => {
                merged.set(
                    attachmentFileIdentity(file),
                    file
                );
            }
        );

        return [
            ...merged.values(),
        ];
    };

    const openAttachmentPicker = () => {
        if (!attachmentInput) {
            return;
        }

        const existingFiles = [
            ...attachmentInput.files,
        ];

        if (
            existingFiles.length
            >= attachmentRules.maxFiles
        ) {
            return;
        }

        attachmentFilesBeforeDialog =
            existingFiles;

        attachmentInput.click();
    };

    attachmentSelect
        ?.addEventListener(
            'click',
            openAttachmentPicker
        );

    attachmentDropzone
        ?.addEventListener(
            'click',
            (event) => {
                if (
                    event.target
                        instanceof Element
                    && event.target.closest(
                        'button'
                    )
                ) {
                    return;
                }

                openAttachmentPicker();
            }
        );

    attachmentInput
        ?.addEventListener(
            'change',
            () => {
                const newlySelected = [
                    ...attachmentInput.files,
                ];

                const merged =
                    mergeAttachmentFiles(
                        attachmentFilesBeforeDialog,
                        newlySelected
                    );

                attachmentFilesBeforeDialog = [];

                applyAttachmentFiles(merged);
            }
        );

    [
        'dragenter',
        'dragover',
    ].forEach(
        (eventName) => {
            attachmentDropzone
                ?.addEventListener(
                    eventName,
                    (event) => {
                        event.preventDefault();

                        attachmentDropzone
                            .classList
                            .add(
                                'is-dragging'
                            );
                    }
                );
        }
    );

    [
        'dragleave',
        'drop',
    ].forEach(
        (eventName) => {
            attachmentDropzone
                ?.addEventListener(
                    eventName,
                    (event) => {
                        event.preventDefault();

                        attachmentDropzone
                            .classList
                            .remove(
                                'is-dragging'
                            );
                    }
                );
        }
    );

    attachmentDropzone
        ?.addEventListener(
            'drop',
            (event) => {
                const files = [
                    ...(
                        event
                            .dataTransfer
                            ?.files
                        || []
                    ),
                ];

                applyAttachmentFiles(
                    files
                );
            }
        );

    renderAttachments();
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
    const syncExternalDirectory = (row) => {
        if (!row) return;

        const kind =
            row.querySelector(
                '[name="party_kind[]"]'
            )?.value || '';

        const role =
            row.querySelector(
                '[name="party_role_code[]"]'
            )?.value || '';

        const organization =
            row.querySelector(
                '[data-external-directory-organization]'
            );

        const point =
            row.querySelector(
                '[data-external-directory-point]'
            );

        if (!organization || !point) {
            return;
        }

        const organizationSnapshot =
            row.querySelector(
                '[data-external-organization-snapshot]'
            );

        const contactSnapshot =
            row.querySelector(
                '[data-external-contact-snapshot]'
            );

        const displayName =
            row.querySelector(
                '[data-external-display-name]'
            );

        const organizationReference =
            organization.value || '';

        [...point.options].forEach(
            (option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                const belongs =
                    organizationReference !== ''
                    &&
                    option.dataset.organizationReference
                        === organizationReference;

                option.hidden = !belongs;
                option.disabled = !belongs;
            }
        );

        const selectedPoint =
            point.selectedOptions?.[0];

        if (
            point.value !== ''
            && (
                !selectedPoint
                ||
                selectedPoint.dataset.organizationReference
                    !== organizationReference
            )
        ) {
            point.value = '';
        }

        const required =
            direction === 'outgoing'
            && !row.hidden
            && kind === 'external'
            && role === 'primary_recipient';

        organization.required = required;
        point.required = required;

        if (
            organization.value !== ''
            && organizationSnapshot
        ) {
            const title =
                organization.selectedOptions?.[0]
                    ?.dataset.title || '';

            organizationSnapshot.value =
                title;

            if (
                displayName
                && displayName.value.trim() === ''
                && title !== ''
            ) {
                displayName.value =
                    title;
            }
        }

        if (
            point.value !== ''
            && contactSnapshot
        ) {
            const title =
                point.selectedOptions?.[0]
                    ?.dataset.title || '';

            contactSnapshot.value =
                title;
        }
    };

    recipientRows.forEach(
        syncExternalDirectory
    );

    form.querySelectorAll(
        '[data-external-directory-organization]'
    ).forEach((field) => {
        field.addEventListener(
            'change',
            () => {
                const row =
                    field.closest(
                        '[data-recipient-row]'
                    );

                const point =
                    row?.querySelector(
                        '[data-external-directory-point]'
                    );

                if (point) {
                    point.value = '';
                }

                syncExternalDirectory(row);
            }
        );
    });

    form.querySelectorAll(
        '[data-external-directory-point]'
    ).forEach((field) => {
        field.addEventListener(
            'change',
            () =>
                syncExternalDirectory(
                    field.closest(
                        '[data-recipient-row]'
                    )
                )
        );
    });

    form.querySelectorAll(
        '[data-party-role]'
    ).forEach((field) => {
        field.addEventListener(
            'change',
            () =>
                syncExternalDirectory(
                    field.closest(
                        '[data-recipient-row]'
                    )
                )
        );
    });

    /*
     * external-directory-quick-modal-v3c
     */
    const quickDirectoryModal =
        form.querySelector(
            '[data-external-directory-modal]'
        );

    const quickDirectoryTitle =
        form.querySelector(
            '[data-external-directory-quick-title]'
        );

    const quickDirectoryShortTitle =
        form.querySelector(
            '[data-external-directory-quick-short-title]'
        );

    const quickDirectoryPointTitle =
        form.querySelector(
            '[data-external-directory-quick-point-title]'
        );

    const quickDirectoryContactPerson =
        form.querySelector(
            '[data-external-directory-quick-contact-person]'
        );

    const quickDirectoryMessage =
        form.querySelector(
            '[data-external-directory-quick-message]'
        );

    const quickDirectorySave =
        form.querySelector(
            '[data-external-directory-quick-save]'
        );

    let quickDirectoryRow = null;
    let quickDirectoryOrganizationReference = '';

    const showQuickDirectoryMessage = (
        message = ''
    ) => {
        if (!quickDirectoryMessage) {
            return;
        }

        quickDirectoryMessage.textContent =
            message;

        quickDirectoryMessage.hidden =
            message === '';
    };

    const resetQuickDirectory = () => {
        quickDirectoryOrganizationReference = '';

        if (quickDirectoryTitle) {
            quickDirectoryTitle.value = '';
            quickDirectoryTitle.disabled = false;
        }

        if (quickDirectoryShortTitle) {
            quickDirectoryShortTitle.value = '';
            quickDirectoryShortTitle.disabled = false;
        }

        if (quickDirectoryPointTitle) {
            quickDirectoryPointTitle.value = '';
        }

        if (quickDirectoryContactPerson) {
            quickDirectoryContactPerson.value = '';
        }

        showQuickDirectoryMessage('');
    };

    const upsertDirectoryOrganization = (
        row,
        organization
    ) => {
        const select =
            row?.querySelector(
                '[data-external-directory-organization]'
            );

        const reference =
            organization?.public_reference || '';

        const title =
            organization?.title || '';

        if (
            !select
            || reference === ''
            || title === ''
        ) {
            return;
        }

        let option =
            [...select.options].find(
                (candidate) =>
                    candidate.value === reference
            );

        if (!option) {
            option =
                document.createElement(
                    'option'
                );

            option.value =
                reference;

            select.appendChild(
                option
            );
        }

        option.textContent =
            title;

        option.dataset.title =
            title;

        select.value =
            reference;
    };

    const upsertDirectoryPoint = (
        row,
        organizationReference,
        point
    ) => {
        const select =
            row?.querySelector(
                '[data-external-directory-point]'
            );

        const reference =
            point?.public_reference || '';

        const title =
            point?.title || '';

        if (
            !select
            || organizationReference === ''
            || reference === ''
            || title === ''
        ) {
            return;
        }

        let option =
            [...select.options].find(
                (candidate) =>
                    candidate.value === reference
            );

        if (!option) {
            option =
                document.createElement(
                    'option'
                );

            option.value =
                reference;

            select.appendChild(
                option
            );
        }

        option.textContent =
            title;

        option.dataset.title =
            title;

        option.dataset.organizationReference =
            organizationReference;

        select.value =
            reference;
    };

    form.querySelectorAll(
        '[data-external-directory-quick-open]'
    ).forEach((button) => {
        button.addEventListener(
            'click',
            () => {
                quickDirectoryRow =
                    button.closest(
                        '[data-recipient-row]'
                    );

                resetQuickDirectory();

                if (
                    quickDirectoryModal
                    && typeof quickDirectoryModal
                        .showModal === 'function'
                ) {
                    quickDirectoryModal
                        .showModal();

                    setTimeout(
                        () =>
                            quickDirectoryTitle
                                ?.focus(),
                        0
                    );
                }
            }
        );
    });

    form.querySelectorAll(
        '[data-external-directory-quick-close]'
    ).forEach((button) => {
        button.addEventListener(
            'click',
            () => {
                quickDirectoryModal?.close();
                quickDirectoryRow = null;
                resetQuickDirectory();
            }
        );
    });

    quickDirectoryModal?.addEventListener(
        'cancel',
        () => {
            quickDirectoryRow = null;
            resetQuickDirectory();
        }
    );

    quickDirectorySave?.addEventListener(
        'click',
        async () => {
            if (!quickDirectoryRow) {
                return;
            }

            const title =
                quickDirectoryTitle
                    ?.value.trim()
                || '';

            const pointTitle =
                quickDirectoryPointTitle
                    ?.value.trim()
                || '';

            if (
                quickDirectoryOrganizationReference === ''
                && title === ''
            ) {
                showQuickDirectoryMessage(
                    'نام رسمی سازمان را وارد کنید.'
                );

                quickDirectoryTitle
                    ?.focus();

                return;
            }

            if (pointTitle === '') {
                showQuickDirectoryMessage(
                    'عنوان مقصد مکاتباتی را وارد کنید.'
                );

                quickDirectoryPointTitle
                    ?.focus();

                return;
            }

            const csrfToken =
                form.querySelector(
                    '[name="_token"]'
                )?.value || '';

            if (csrfToken === '') {
                showQuickDirectoryMessage(
                    'توکن امنیتی فرم در دسترس نیست. صفحه را تازه‌سازی کنید.'
                );

                return;
            }

            const body =
                new URLSearchParams();

            body.set(
                '_token',
                csrfToken
            );

            body.set(
                'organization_reference',
                quickDirectoryOrganizationReference
            );

            body.set(
                'title_fa',
                title
            );

            body.set(
                'short_title',
                quickDirectoryShortTitle
                    ?.value.trim()
                || ''
            );

            body.set(
                'contact_point_title',
                pointTitle
            );

            body.set(
                'contact_person_name',
                quickDirectoryContactPerson
                    ?.value.trim()
                || ''
            );

            quickDirectorySave.disabled =
                true;

            quickDirectorySave.textContent =
                'در حال ثبت...';

            showQuickDirectoryMessage('');

            try {
                const response =
                    await fetch(
                        '/admin/automation/external-organizations/quick-create',
                        {
                            method: 'POST',

                            credentials:
                                'same-origin',

                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'Accept':
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            body:
                                body.toString(),
                        }
                    );

                const contentType =
                    response.headers.get(
                        'content-type'
                    ) || '';

                if (
                    !contentType.includes(
                        'application/json'
                    )
                ) {
                    throw new Error(
                        'پاسخ معتبر از سامانه دریافت نشد. ممکن است نشست شما منقضی شده باشد.'
                    );
                }

                const payload =
                    await response.json();

                if (
                    payload?.organization
                    ?.public_reference
                ) {
                    upsertDirectoryOrganization(
                        quickDirectoryRow,
                        payload.organization
                    );
                }

                if (
                    payload?.partial === true
                    && payload?.organization
                        ?.public_reference
                ) {
                    quickDirectoryOrganizationReference =
                        payload.organization
                            .public_reference;

                    if (quickDirectoryTitle) {
                        quickDirectoryTitle.value =
                            payload.organization
                                .title || title;

                        quickDirectoryTitle.disabled =
                            true;
                    }

                    if (quickDirectoryShortTitle) {
                        quickDirectoryShortTitle.value =
                            payload.organization
                                .short_title || '';

                        quickDirectoryShortTitle.disabled =
                            true;
                    }
                }

                if (
                    !response.ok
                    || payload?.ok !== true
                ) {
                    const error =
                        Object.values(
                            payload?.errors || {}
                        )[0]
                        || 'ثبت اطلاعات انجام نشد.';

                    showQuickDirectoryMessage(
                        payload?.partial === true
                            ? (
                                'سازمان ثبت شد، اما مقصد ثبت نشد. '
                                + error
                                + ' دوباره «ثبت مقصد» را بزنید.'
                            )
                            : error
                    );

                    if (
                        payload?.partial === true
                    ) {
                        quickDirectorySave.textContent =
                            'ثبت مقصد';
                    }

                    return;
                }

                const organizationReference =
                    payload.organization
                        ?.public_reference
                    || '';

                upsertDirectoryPoint(
                    quickDirectoryRow,
                    organizationReference,
                    payload.contact_point
                );

                syncExternalDirectory(
                    quickDirectoryRow
                );

                quickDirectoryModal?.close();

                quickDirectoryRow = null;

                resetQuickDirectory();

            } catch (error) {
                showQuickDirectoryMessage(
                    error?.message
                    || 'خطا در ارتباط با سامانه.'
                );

            } finally {
                quickDirectorySave.disabled =
                    false;

                if (
                    quickDirectoryOrganizationReference
                    === ''
                ) {
                    quickDirectorySave.textContent =
                        'ثبت و انتخاب';
                }
            }
        }
    );

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
        syncExternalDirectory(row);

        row.querySelector(
            '[data-party-internal] select, [data-party-external] input, [data-party-external] select'
        )?.focus();

        syncAddButton();
    });
    form.querySelectorAll('[data-remove-recipient]').forEach((button) => button.addEventListener('click', () => {
        const row = button.closest('[data-recipient-row]');
        if (!row) return;
        row.querySelectorAll('input').forEach((field) => field.value = '');
        row.querySelectorAll('select').forEach((field) => field.value = '');
        row.hidden = true;
        syncExternalDirectory(row);
        syncAddButton();
    }));
    syncAddButton();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
