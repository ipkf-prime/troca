<?php

declare(strict_types=1);

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$page = is_array($page ?? null) ? $page : [];

$assignments = is_array($page['assignments'] ?? null)
    ? $page['assignments']
    : [];

$selected = is_array(
    $page['selected_assignment'] ?? null
)
    ? $page['selected_assignment']
    : null;

$scopeTypes = is_array($page['scope_types'] ?? null)
    ? $page['scope_types']
    : [];

$constraintTypes = is_array(
    $page['constraint_types'] ?? null
)
    ? $page['constraint_types']
    : [];

$scopes = is_array(
    $page['assignment_scopes'] ?? null
)
    ? $page['assignment_scopes']
    : [];

$constraints = is_array(
    $page['assignment_constraints'] ?? null
)
    ? $page['assignment_constraints']
    : [];

$scopeOptions = is_array(
    $page['scope_options'] ?? null
)
    ? $page['scope_options']
    : [];

$status = trim((string) ($status ?? ''));

$messages = [
    'scope_saved' =>
        [
            'ok',
            'حوزه‌ها و محدودیت‌های انتساب نقش ذخیره شد.',
        ],

    'invalid_csrf' =>
        ['error', 'نشست فرم معتبر نیست.'],

    'access_assignment_not_found' =>
        ['error', 'انتساب نقش پیدا نشد.'],

    'access_scope_at_least_one_required' =>
        ['error', 'حداقل یک حوزه دسترسی ثبت کنید.'],

    'access_scope_type_not_allowed' =>
        [
            'error',
            'یکی از حوزه‌های انتخاب‌شده برای این نقش مجاز نیست.',
        ],

    'access_scope_reference_invalid' =>
        ['error', 'مرجع یکی از حوزه‌ها معتبر نیست.'],

    'access_constraint_type_invalid' =>
        ['error', 'نوع محدودیت معتبر نیست.'],

    'access_constraint_value_required' =>
        ['error', 'برای محدودیت انتخاب‌شده مقدار لازم است.'],

    'access_reason_required' =>
        ['error', 'ثبت دلیل تغییر الزامی است.'],
];

$csrf = (new \IPKF\Security\Csrf())->token();

if ($scopes === [] && $selected !== null) {
    $legacyType = trim(
        (string) ($selected['scope_type'] ?? '')
    );

    $legacyReference = trim(
        (string) ($selected['scope_reference'] ?? '')
    );

    if ($legacyType === '') {
        $legacyType = 'global';
    }

    if ($legacyReference === '') {
        $legacyReference = '*';
    }

    $scopes[] = [
        'scope_type_code' => $legacyType,
        'scope_reference' => $legacyReference,
        'effect_code' => 'allow',
        'include_descendants' => 0,
    ];
}

if ($scopes === []) {
    $scopes[] = [
        'scope_type_code' => '',
        'scope_reference' => '',
        'effect_code' => 'allow',
        'include_descendants' => 0,
    ];
}

if ($constraints === []) {
    $constraints[] = [
        'constraint_type_code' => '',
        'operator_code' => 'eq',
        'value_text' => '',
        'effect_code' => 'allow',
    ];
}

ob_start();
?>
<?php
$scopeNotice = trim((string) ($scope_notice_code ?? ''));
$scopeNotices = [
    'scope_saved' => ['ok', 'حوزه‌ها و محدودیت‌های انتساب ذخیره شد.'],
    'invalid_csrf' => ['error', 'اعتبار نشست فرم پایان یافته است.'],
    'access_assignment_not_found' => ['error', 'انتساب نقش پیدا نشد.'],
    'access_scope_at_least_one_required' => ['error', 'حداقل یک حوزه دسترسی ثبت کنید.'],
    'access_scope_type_not_allowed' => ['error', 'حوزه انتخاب‌شده برای این نقش مجاز نیست.'],
    'access_scope_reference_invalid' => ['error', 'مرجع یکی از حوزه‌ها معتبر نیست.'],
    'access_constraint_type_invalid' => ['error', 'نوع محدودیت معتبر نیست.'],
    'access_constraint_value_required' => ['error', 'برای محدودیت انتخاب‌شده مقدار لازم است.'],
    'access_reason_required' => ['error', 'ثبت دلیل تغییر الزامی است.'],
];
$scopeOptionsJson = json_encode(
    $scopeOptions,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?: '{}';
?>

<style>
.scope-governance{display:grid;gap:1rem}.scope-governance select,.scope-governance select option,.scope-governance select optgroup{font-family:"Vazirmatn","Tahoma","Segoe UI",sans-serif!important}.scope-toolbar{display:flex;flex-direction:column;gap:.6rem;align-items:stretch}.scope-toolbar .admin-form-actions{display:flex;flex-wrap:wrap;gap:.6rem;justify-content:flex-start;direction:rtl;width:100%}.scope-layout{display:grid;grid-template-columns:minmax(240px,310px) minmax(0,1fr);gap:1rem}.scope-side,.scope-main{border:1px solid #dce8e1;border-radius:14px;background:#fff;padding:1rem}.scope-assignment{display:grid;gap:.35rem;padding:.7rem;border-radius:10px;background:#eef8f2;color:#245c41}.scope-row,.constraint-row{display:grid;grid-template-columns:1.1fr 1.5fr .75fr auto;gap:.55rem;align-items:end;padding:.65rem;border:1px solid #e0e9e4;border-radius:11px;margin-bottom:.55rem}.constraint-row{grid-template-columns:1.1fr 1fr 1.4fr .8fr auto}.scope-row label,.constraint-row label{display:grid;gap:.3rem}.scope-remove{height:42px}.scope-section-title{margin:1rem 0 .35rem}.scope-actions{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap}.scope-actions textarea{min-height:70px;flex:1 1 320px}.scope-note{padding:.75rem;border-radius:10px;background:#fff7e7;color:#795b1c}.scope-empty{color:#708399}.scope-hidden{display:none!important}@media(max-width:980px){.scope-layout{grid-template-columns:1fr}.scope-row,.constraint-row{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.scope-row,.constraint-row{grid-template-columns:1fr}}
</style>

<?php if (isset($scopeNotices[$scopeNotice])): ?>
    <?php [$scopeNoticeKind, $scopeNoticeText] = $scopeNotices[$scopeNotice]; ?>
    <div class="<?= $scopeNoticeKind === 'ok' ? 'admin-alert admin-alert--success' : 'admin-alert admin-alert--danger' ?>">
        <?= admin_h($scopeNoticeText) ?>
    </div>
<?php endif; ?>

<section class="admin-section scope-governance" data-access-scope-editor data-scope-options='<?= admin_h($scopeOptionsJson) ?>'>
    <div class="scope-toolbar">
        <div>
            <h2>حوزه و محدودیت انتساب نقش</h2>
            <p class="admin-muted">هر انتساب می‌تواند حوزه‌ای متفاوت از همان نقش داشته باشد.</p>
        </div>
        <div class="admin-form-actions" data-access-page="assignment-scope">
            <a class="admin-button admin-button--soft" href="/admin/access-control">بازگشت</a>
            <a class="admin-button admin-button--soft" href="/admin/access-control/roles">مدیریت و ویرایش نقش‌ها</a>
            <a class="admin-button admin-button--soft" href="/admin/access-control/roles/create">ایجاد نقش جدید</a>
            <a class="admin-button" href="/admin/access-control/scopes">حوزه و محدودیت انتساب‌ها</a>
        </div>
    </div>

    <div class="scope-layout">
        <aside class="scope-side">
            <label>
                <span>انتساب نقش</span>
                <select data-assignment-select>
                    <?php foreach ($assignments as $assignmentItem): ?>
                        <?php $assignmentId = (int) ($assignmentItem['id'] ?? 0); ?>
                        <option value="<?= $assignmentId ?>" <?= $selected !== null && $assignmentId === (int) ($selected['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= admin_h($assignmentItem['user_title'] ?? '') ?> — <?= admin_h($assignmentItem['role_title'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <?php if ($selected !== null): ?>
                <div class="scope-assignment">
                    <strong><?= admin_h($selected['user_title'] ?? '') ?></strong>
                    <span><?= admin_h($selected['role_title'] ?? '') ?></span>
                    <small>شناسه انتساب: <?= (int) ($selected['id'] ?? 0) ?></small>
                </div>
            <?php else: ?>
                <p class="scope-empty">انتساب فعالی برای مدیریت وجود ندارد.</p>
            <?php endif; ?>
        </aside>

        <div class="scope-main">
            <?php if ($selected !== null): ?>
                <form method="post" action="/admin/access-control/scopes" data-scope-form>
                    <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                    <input type="hidden" name="role_assignment_id" value="<?= (int) ($selected['id'] ?? 0) ?>">

                    <h3 class="scope-section-title">حوزه‌های دسترسی</h3>
                    <p class="admin-muted">حوزه مجاز دامنه دسترسی را تعیین می‌کند و حوزه ممنوع بر آن اولویت دارد.</p>
                    <div data-scope-rows>
                        <?php foreach ($scopes as $scopeIndex => $scopeItem): ?>
                            <div class="scope-row" data-scope-row>
                                <label><span>نوع حوزه</span><select name="scopes[<?= (int) $scopeIndex ?>][type]" data-scope-type><option value="">انتخاب کنید</option><?php foreach ($scopeTypes as $scopeTypeItem): ?><?php $scopeTypeCode = (string) ($scopeTypeItem['code'] ?? ''); ?><option value="<?= admin_h($scopeTypeCode) ?>" <?= $scopeTypeCode === (string) ($scopeItem['scope_type_code'] ?? '') ? 'selected' : '' ?>><?= admin_h($scopeTypeItem['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                                <label data-reference-wrap><span>مرجع حوزه</span><select data-reference-select></select><input type="text" maxlength="190" data-reference-input placeholder="شناسه یا کد مرجع"><input type="hidden" data-reference-hidden value="<?= admin_h($scopeItem['scope_reference'] ?? '') ?>"></label>
                                <label><span>اثر</span><select name="scopes[<?= (int) $scopeIndex ?>][effect]"><option value="allow" <?= ($scopeItem['effect_code'] ?? 'allow') === 'allow' ? 'selected' : '' ?>>مجاز</option><option value="deny" <?= ($scopeItem['effect_code'] ?? '') === 'deny' ? 'selected' : '' ?>>ممنوع</option></select></label>
                                <button type="button" class="admin-button admin-button--soft scope-remove" data-remove-row>حذف</button>
                                <label class="admin-check"><input type="checkbox" name="scopes[<?= (int) $scopeIndex ?>][include_descendants]" value="1" <?= !empty($scopeItem['include_descendants']) ? 'checked' : '' ?>><span>شامل زیرمجموعه‌ها</span></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="admin-button admin-button--soft" data-add-scope>افزودن حوزه</button>

                    <h3 class="scope-section-title">محدودیت‌های جزئی</h3>
                    <p class="admin-muted">برای محدودکردن پروژه، خدمت، وضعیت، اولویت یا سایر ویژگی‌های کنترل‌شده استفاده کنید.</p>
                    <div data-constraint-rows>
                        <?php foreach ($constraints as $constraintIndex => $constraintItem): ?>
                            <div class="constraint-row" data-constraint-row>
                                <label><span>نوع محدودیت</span><select name="constraints[<?= (int) $constraintIndex ?>][type]" data-constraint-type><option value="">بدون محدودیت</option><?php foreach ($constraintTypes as $constraintTypeItem): ?><?php $constraintTypeCode = (string) ($constraintTypeItem['code'] ?? ''); ?><option value="<?= admin_h($constraintTypeCode) ?>" <?= $constraintTypeCode === (string) ($constraintItem['constraint_type_code'] ?? '') ? 'selected' : '' ?>><?= admin_h($constraintTypeItem['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                                <label data-constraint-dependent><span>عملگر</span><select name="constraints[<?= (int) $constraintIndex ?>][operator]"><option value="eq" <?= ($constraintItem['operator_code'] ?? 'eq') === 'eq' ? 'selected' : '' ?>>برابر</option><option value="neq" <?= ($constraintItem['operator_code'] ?? '') === 'neq' ? 'selected' : '' ?>>نابرابر</option><option value="in" <?= ($constraintItem['operator_code'] ?? '') === 'in' ? 'selected' : '' ?>>یکی از مقادیر</option><option value="not_in" <?= ($constraintItem['operator_code'] ?? '') === 'not_in' ? 'selected' : '' ?>>هیچ‌کدام از مقادیر</option></select></label>
                                <label data-constraint-dependent><span>مقدار</span><input type="text" name="constraints[<?= (int) $constraintIndex ?>][value]" maxlength="500" value="<?= admin_h($constraintItem['value_text'] ?? '') ?>" placeholder="مقدار یا چند مقدار با ویرگول"></label>
                                <label data-constraint-dependent><span>اثر</span><select name="constraints[<?= (int) $constraintIndex ?>][effect]"><option value="allow" <?= ($constraintItem['effect_code'] ?? 'allow') === 'allow' ? 'selected' : '' ?>>مجاز</option><option value="deny" <?= ($constraintItem['effect_code'] ?? '') === 'deny' ? 'selected' : '' ?>>ممنوع</option></select></label>
                                <button type="button" class="admin-button admin-button--soft scope-remove" data-remove-row>حذف</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="admin-button admin-button--soft" data-add-constraint>افزودن محدودیت</button>

                    <p class="scope-note">اعمال خودکار این حوزه‌ها روی داده‌های هر ماژول، پس از اتصال همان ماژول به کنترل دسترسی زمینه‌محور فعال می‌شود.</p>
                    <div class="scope-actions">
                        <textarea name="reason" required minlength="3" maxlength="500" placeholder="دلیل تغییر برای ثبت در تاریخچه"></textarea>
                        <button class="admin-button" type="submit">ذخیره حوزه و محدودیت‌ها</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<template data-scope-template>
    <div class="scope-row" data-scope-row>
        <label><span>نوع حوزه</span><select data-name="scopes[__INDEX__][type]" data-scope-type><option value="">انتخاب کنید</option><?php foreach ($scopeTypes as $scopeTypeItem): ?><option value="<?= admin_h($scopeTypeItem['code'] ?? '') ?>"><?= admin_h($scopeTypeItem['title'] ?? '') ?></option><?php endforeach; ?></select></label>
        <label data-reference-wrap><span>مرجع حوزه</span><select data-reference-select></select><input type="text" maxlength="190" data-reference-input placeholder="شناسه یا کد مرجع"><input type="hidden" data-reference-hidden value=""></label>
        <label><span>اثر</span><select data-name="scopes[__INDEX__][effect]"><option value="allow">مجاز</option><option value="deny">ممنوع</option></select></label>
        <button type="button" class="admin-button admin-button--soft scope-remove" data-remove-row>حذف</button>
        <label class="admin-check"><input type="checkbox" data-name="scopes[__INDEX__][include_descendants]" value="1"><span>شامل زیرمجموعه‌ها</span></label>
    </div>
</template>

<template data-constraint-template>
    <div class="constraint-row" data-constraint-row>
        <label><span>نوع محدودیت</span><select data-name="constraints[__INDEX__][type]" data-constraint-type><option value="">بدون محدودیت</option><?php foreach ($constraintTypes as $constraintTypeItem): ?><option value="<?= admin_h($constraintTypeItem['code'] ?? '') ?>"><?= admin_h($constraintTypeItem['title'] ?? '') ?></option><?php endforeach; ?></select></label>
        <label data-constraint-dependent><span>عملگر</span><select data-name="constraints[__INDEX__][operator]"><option value="eq">برابر</option><option value="neq">نابرابر</option><option value="in">یکی از مقادیر</option><option value="not_in">هیچ‌کدام از مقادیر</option></select></label>
        <label data-constraint-dependent><span>مقدار</span><input type="text" data-name="constraints[__INDEX__][value]" maxlength="500"></label>
        <label data-constraint-dependent><span>اثر</span><select data-name="constraints[__INDEX__][effect]"><option value="allow">مجاز</option><option value="deny">ممنوع</option></select></label>
        <button type="button" class="admin-button admin-button--soft scope-remove" data-remove-row>حذف</button>
    </div>
</template>

<script>
(() => {
    const root = document.querySelector('[data-access-scope-editor]');
    if (!root) return;
    const options = JSON.parse(root.dataset.scopeOptions || '{}');
    const automatic = new Set(['global', 'own', 'assigned']);
    const bindReference = (row) => {
        const type = row.querySelector('[data-scope-type]');
        const wrap = row.querySelector('[data-reference-wrap]');
        const select = row.querySelector('[data-reference-select]');
        const input = row.querySelector('[data-reference-input]');
        const hidden = row.querySelector('[data-reference-hidden]');
        if (!type || !wrap || !select || !input || !hidden) return;
        const refresh = () => {
            const code = type.value;
            const current = hidden.value || select.value || input.value || '';
            const rows = Array.isArray(options[code]) ? options[code] : [];
            select.innerHTML = '';
            if (automatic.has(code)) {
                wrap.classList.add('scope-hidden');
                select.removeAttribute('name'); input.removeAttribute('name');
                hidden.name = type.name.replace('[type]', '[reference]');
                hidden.value = '*';
                return;
            }
            wrap.classList.remove('scope-hidden'); hidden.removeAttribute('name');
            if (rows.length > 0) {
                select.name = type.name.replace('[type]', '[reference]');
                input.removeAttribute('name'); input.classList.add('scope-hidden');
                select.classList.remove('scope-hidden');
                rows.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = String(item.option_value ?? '');
                    option.textContent = String(item.option_title ?? option.value);
                    if (option.value === current) option.selected = true;
                    select.appendChild(option);
                });
                if (current && !rows.some((item) => String(item.option_value ?? '') === current)) {
                    const option = document.createElement('option');
                    option.value = current; option.textContent = current; option.selected = true;
                    select.appendChild(option);
                }
            } else {
                input.name = type.name.replace('[type]', '[reference]');
                input.value = current;
                select.removeAttribute('name'); select.classList.add('scope-hidden');
                input.classList.remove('scope-hidden');
            }
        };
        type.addEventListener('change', () => {
            hidden.value = '';
            select.value = '';
            input.value = '';
            refresh();
        });
        refresh();
    };
    const bindConstraint = (row) => {
        const type = row.querySelector('[data-constraint-type]');
        if (!type) return;
        const refresh = () => row.querySelectorAll('[data-constraint-dependent]').forEach((element) => element.classList.toggle('scope-hidden', !type.value));
        type.addEventListener('change', refresh); refresh();
    };
    const normalizeTemplateNames = (node, index) => node.querySelectorAll('[data-name]').forEach((item) => { item.name = item.dataset.name.replace('__INDEX__', String(index)); item.removeAttribute('data-name'); });
    root.querySelectorAll('[data-scope-row]').forEach(bindReference);
    root.querySelectorAll('[data-constraint-row]').forEach(bindConstraint);
    root.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-row]');
        if (remove) { const row = remove.closest('[data-scope-row],[data-constraint-row]'); if (row) row.remove(); return; }
        if (event.target.closest('[data-add-scope]')) {
            const holder = root.querySelector('[data-scope-rows]'); const template = document.querySelector('[data-scope-template]');
            const node = template.content.firstElementChild.cloneNode(true); normalizeTemplateNames(node, holder.children.length); holder.appendChild(node); bindReference(node);
        }
        if (event.target.closest('[data-add-constraint]')) {
            const holder = root.querySelector('[data-constraint-rows]'); const template = document.querySelector('[data-constraint-template]');
            const node = template.content.firstElementChild.cloneNode(true); normalizeTemplateNames(node, holder.children.length); holder.appendChild(node); bindConstraint(node);
        }
    });
    const assignment = root.querySelector('[data-assignment-select]');
    if (assignment) assignment.addEventListener('change', () => { window.location.href = '/admin/access-control/scopes?assignment_id=' + encodeURIComponent(assignment.value); });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
