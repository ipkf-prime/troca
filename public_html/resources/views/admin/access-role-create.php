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

$roleAreas = is_array($page['role_areas'] ?? null)
    ? $page['role_areas']
    : [];

$roleKinds = is_array($page['role_kinds'] ?? null)
    ? $page['role_kinds']
    : [];

$permissions = is_array($page['permissions'] ?? null)
    ? $page['permissions']
    : [];

$scopeTypes = is_array($page['scope_types'] ?? null)
    ? $page['scope_types']
    : [];

$identityFields = is_array(
    $page['identity_fields'] ?? null
)
    ? $page['identity_fields']
    : [];

$status = trim((string) ($status ?? ''));

$messages = [
    'role_created' =>
        ['ok', 'نقش جدید با موفقیت ایجاد شد.'],

    'invalid_csrf' =>
        ['error', 'نشست فرم معتبر نیست.'],

    'access_role_code_invalid' =>
        [
            'error',
            'کد نقش باید با custom_ شروع شود و فقط شامل حروف انگلیسی کوچک، عدد و زیرخط باشد.',
        ],

    'access_role_code_duplicate' =>
        ['error', 'این کد نقش قبلاً ثبت شده است.'],

    'access_role_title_invalid' =>
        ['error', 'عنوان نقش معتبر نیست.'],

    'access_role_classification_invalid' =>
        ['error', 'نوع یا حوزه پایه نقش معتبر نیست.'],

    'access_role_permission_required' =>
        ['error', 'حداقل یک مجوز انتخاب کنید.'],

    'access_role_scope_required' =>
        ['error', 'حداقل یک نوع حوزه دسترسی انتخاب کنید.'],

    'access_reason_required' =>
        ['error', 'ثبت دلیل ایجاد نقش الزامی است.'],
];

$permissionGroups = [];

foreach ($permissions as $permission) {
    $module = trim(
        (string) ($permission['module'] ?? 'core')
    );

    if ($module === '') {
        $module = 'core';
    }

    $permissionGroups[$module][] = $permission;
}

ksort($permissionGroups);

$moduleLabels = [
    'access' => 'سطوح و نقش‌های دسترسی',
    'account' => 'حساب کاربری',
    'admin' => 'مدیریت سامانه',
    'auth' => 'احراز هویت',
    'automation' => 'اتوماسیون اداری',
    'communications' => 'پیام‌ها و اعلان‌ها',
    'core' => 'هسته سامانه',
    'messages' => 'پیام‌رسان داخلی',
    'organizations' => 'سازمان و ساختار',
    'system' => 'زیرساخت سامانه',
    'ticketing' => 'تیکتینگ',
    'users' => 'کاربران',
    'work' => 'مدیریت کار',
];

$csrf = (new \IPKF\Security\Csrf())->token();

ob_start();
?>
<?php
$roleMode = (string) ($page['mode'] ?? 'create');
$roleEditing = $roleMode === 'edit';
$roleSelected = is_array($page['selected_role'] ?? null)
    ? $page['selected_role']
    : [];
$roleRows = is_array($page['roles'] ?? null) ? $page['roles'] : [];
$selectedPermissions = array_fill_keys(
    is_array($page['selected_permissions'] ?? null)
        ? $page['selected_permissions']
        : [],
    true
);
$selectedScopes = array_fill_keys(
    is_array($page['selected_scope_types'] ?? null)
        ? $page['selected_scope_types']
        : [],
    true
);
$selectedIdentity = array_fill_keys(
    is_array($page['selected_identity_fields'] ?? null)
        ? $page['selected_identity_fields']
        : [],
    true
);
$metadataLocked = !empty($page['metadata_locked']);
$roleNotice = trim((string) ($role_notice_code ?? ''));
$roleNotices = [
    'role_created' => ['ok', 'نقش جدید با موفقیت ایجاد شد.'],
    'role_updated' => ['ok', 'تنظیمات نقش با موفقیت ذخیره شد.'],
    'invalid_csrf' => ['error', 'اعتبار نشست فرم پایان یافته است.'],
    'access_role_code_invalid' => ['error', 'قالب کد نقش معتبر نیست.'],
    'access_role_code_duplicate' => ['error', 'این کد نقش قبلاً ثبت شده است.'],
    'access_role_title_invalid' => ['error', 'عنوان نقش معتبر نیست.'],
    'access_role_classification_invalid' => ['error', 'نوع یا حوزه پایه نقش معتبر نیست.'],
    'access_role_permission_required' => ['error', 'حداقل یک مجوز انتخاب کنید.'],
    'access_role_scope_required' => ['error', 'حداقل یک حوزه مجاز انتخاب کنید.'],
    'access_reason_required' => ['error', 'ثبت دلیل تغییر الزامی است.'],
    'access_permission_ceiling_exceeded' => ['error', 'اعطای مجوز بالاتر از سطح مدیر مجاز نیست.'],
    'access_role_edit_forbidden' => ['error', 'ویرایش این نقش برای شما مجاز نیست.'],
    'access_super_admin_permission_required' => ['error', 'مجوزهای حفاظتی مدیر کل سامانه قابل حذف نیست.'],
];
$rolePermissionGroups = [];

foreach ($permissions as $permissionItem) {
    $groupName = trim((string) ($permissionItem['display_group'] ?? ''));

    if ($groupName === '') {
        $groupName = 'سایر مجوزها';
    }

    $rolePermissionGroups[$groupName][] = $permissionItem;
}

ksort($rolePermissionGroups, SORT_NATURAL);
$roleFormAction = $roleEditing
    ? '/admin/access-control/roles/update'
    : '/admin/access-control/roles/create';
$roleHeading = $roleEditing ? 'ویرایش نقش دسترسی' : 'ایجاد نقش دسترسی';
?>

<style>
.role-governance{display:grid;gap:1rem}.role-governance select,.role-governance select option,.role-governance select optgroup{font-family:"Vazirmatn","Tahoma","Segoe UI",sans-serif!important}.role-toolbar{display:flex;flex-direction:column;gap:.6rem;align-items:stretch}.role-toolbar .admin-form-actions{display:flex;flex-wrap:wrap;gap:.6rem;justify-content:flex-start;direction:rtl;width:100%}.role-layout{display:grid;grid-template-columns:minmax(220px,280px) minmax(0,1fr);gap:1rem}.role-list{display:grid;gap:.45rem;align-content:start;max-height:72vh;overflow:auto}.role-list a{display:grid;gap:.15rem;padding:.75rem;border:1px solid #dce8e1;border-radius:12px;color:inherit;text-decoration:none;background:#fff}.role-list a.is-active{border-color:#2e9863;background:#eef8f2}.role-list small,.role-help{color:#708399}.role-card{border:1px solid #dce8e1;border-radius:14px;padding:1rem;background:#fff}.role-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}.role-grid label,.role-block{display:grid;gap:.35rem}.role-checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem}.role-check{display:flex!important;align-items:flex-start;gap:.45rem;padding:.55rem;border:1px solid #e0e9e4;border-radius:10px;background:#fbfdfc}.role-check input{width:16px;height:16px;margin-top:.15rem;flex:0 0 auto}.role-check.is-locked{opacity:.62}.role-permissions{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:.6rem;align-items:start}.role-permissions details{border:1px solid #dce8e1;border-radius:12px;background:#fbfdfc}.role-permissions summary{cursor:pointer;padding:.75rem;font-weight:700}.role-permissions .role-checks{padding:0 .75rem .75rem}.role-permissions details[open]{grid-column:1/-1}.role-permissions details[open] .role-checks{grid-template-columns:repeat(3,minmax(0,1fr))}.role-section-title{margin:1.1rem 0 .55rem}.role-lock{padding:.65rem .8rem;border-radius:10px;background:#fff7e7;color:#876319}.role-actions{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-top:1rem}.role-actions textarea{min-height:70px;flex:1 1 320px}.role-badge{display:inline-flex;padding:.12rem .45rem;border-radius:999px;background:#edf6f1;color:#24784e;font-size:.78rem}@media(max-width:1000px){.role-layout{grid-template-columns:1fr}.role-list{grid-template-columns:repeat(2,minmax(0,1fr));max-height:none}.role-grid,.role-checks{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:650px){.role-list,.role-grid,.role-checks{grid-template-columns:1fr}}
</style>

<?php if (isset($roleNotices[$roleNotice])): ?>
    <?php [$roleNoticeKind, $roleNoticeText] = $roleNotices[$roleNotice]; ?>
    <div class="<?= $roleNoticeKind === 'ok'
        ? 'admin-alert admin-alert--success'
        : 'admin-alert admin-alert--danger' ?>">
        <?= admin_h($roleNoticeText) ?>
    </div>
<?php endif; ?>

<section class="admin-section role-governance" data-role-governance>
    <div class="role-toolbar">
        <div>
            <h2><?= admin_h($roleHeading) ?></h2>
            <p class="role-help">
                مجوز مشخص می‌کند نقش چه کاری انجام دهد؛ حوزه مشخص می‌کند
                عملیات روی کدام داده‌ها مجاز باشد.
            </p>
        </div>
        <div class="admin-form-actions" data-access-page="<?= $roleEditing ? 'edit-role' : 'create-role' ?>">
            <a class="admin-button admin-button--soft" href="/admin/access-control">بازگشت</a>
            <a class="<?= $roleEditing ? 'admin-button' : 'admin-button admin-button--soft' ?>" href="/admin/access-control/roles">مدیریت و ویرایش نقش‌ها</a>
            <a class="<?= $roleEditing ? 'admin-button admin-button--soft' : 'admin-button' ?>" href="/admin/access-control/roles/create">ایجاد نقش جدید</a>
            <a class="admin-button admin-button--soft" href="/admin/access-control/scopes">حوزه و محدودیت انتساب‌ها</a>
        </div>
    </div>

    <div class="<?= $roleEditing ? 'role-layout' : '' ?>">
        <?php if ($roleEditing): ?>
            <aside class="role-card role-list" aria-label="نقش‌های قابل ویرایش">
                <?php foreach ($roleRows as $roleRow): ?>
                    <?php $roleRowId = (int) ($roleRow['id'] ?? 0); ?>
                    <a
                        class="<?= $roleRowId === (int) ($roleSelected['id'] ?? 0) ? 'is-active' : '' ?>"
                        href="/admin/access-control/roles?role_id=<?= $roleRowId ?>"
                    >
                        <strong><?= admin_h($roleRow['title'] ?? '') ?></strong>
                        <small>
                            <?= (int) ($roleRow['permission_count'] ?? 0) ?> مجوز ·
                            <?= (int) ($roleRow['scope_policy_count'] ?? 0) ?> حوزه ·
                            <?= (int) ($roleRow['active_assignment_count'] ?? 0) ?> انتساب فعال
                        </small>
                    </a>
                <?php endforeach; ?>
            </aside>
        <?php endif; ?>

        <form method="post" action="<?= admin_h($roleFormAction) ?>" class="role-card admin-form">
            <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
            <?php if ($roleEditing): ?>
                <input type="hidden" name="role_id" value="<?= (int) ($roleSelected['id'] ?? 0) ?>">
            <?php endif; ?>

            <?php if ($metadataLocked): ?>
                <div class="role-lock">
                    مشخصات پایه این نقش سیستمی قفل است؛ مجوزها، حوزه‌ها و
                    اطلاعات لازم آن همچنان قابل مدیریت است.
                </div>
            <?php endif; ?>

            <h3 class="role-section-title">مشخصات نقش</h3>
            <div class="role-grid">
                <label>
                    <span>عنوان نقش</span>
                    <input type="text" name="title" required minlength="3" maxlength="120"
                        value="<?= admin_h($roleSelected['title'] ?? '') ?>"
                        <?= $metadataLocked ? 'readonly' : '' ?>>
                </label>
                <label>
                    <span>کد نقش</span>
                    <input type="text" name="code" required maxlength="67"
                        value="<?= admin_h($roleSelected['code'] ?? 'custom_') ?>"
                        <?= $roleEditing ? 'readonly' : '' ?> dir="ltr">
                </label>
                <label>
                    <span>اولویت</span>
                    <input type="number" name="priority" min="<?= $roleEditing ? 1 : 10 ?>" max="999"
                        value="<?= (int) ($roleSelected['priority'] ?? 100) ?>"
                        <?= $metadataLocked ? 'readonly' : '' ?>>
                </label>
                <label>
                    <span>حوزه پایه</span>
                    <select name="role_area_code" <?= $metadataLocked ? 'disabled' : '' ?> required>
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($roleAreas as $areaItem): ?>
                            <?php $areaCode = (string) ($areaItem['code'] ?? ''); ?>
                            <option value="<?= admin_h($areaCode) ?>" <?= $areaCode === (string) ($roleSelected['role_area_code'] ?? '') ? 'selected' : '' ?>>
                                <?= admin_h($areaItem['title'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($metadataLocked): ?><input type="hidden" name="role_area_code" value="<?= admin_h($roleSelected['role_area_code'] ?? '') ?>"><?php endif; ?>
                </label>
                <label>
                    <span>نوع نقش</span>
                    <select name="role_kind_code" <?= $metadataLocked ? 'disabled' : '' ?> required>
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($roleKinds as $kindItem): ?>
                            <?php $kindCode = (string) ($kindItem['code'] ?? ''); ?>
                            <option value="<?= admin_h($kindCode) ?>" <?= $kindCode === (string) ($roleSelected['role_kind_code'] ?? '') ? 'selected' : '' ?>>
                                <?= admin_h($kindItem['title'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($metadataLocked): ?><input type="hidden" name="role_kind_code" value="<?= admin_h($roleSelected['role_kind_code'] ?? '') ?>"><?php endif; ?>
                </label>
                <div class="role-block">
                    <span>قابلیت‌های پایه</span>
                    <label class="role-check"><input type="checkbox" name="can_manage_other_users" value="1" <?= !empty($roleSelected['can_manage_other_users']) ? 'checked' : '' ?> <?= $metadataLocked ? 'disabled' : '' ?>><span>مدیریت کاربران پایین‌تر</span></label>
                    <label class="role-check"><input type="checkbox" name="requires_center" value="1" <?= !empty($roleSelected['requires_center']) ? 'checked' : '' ?> <?= $metadataLocked ? 'disabled' : '' ?>><span>نیازمند مرکز یا واحد اجرایی</span></label>
                    <?php if ($metadataLocked && !empty($roleSelected['can_manage_other_users'])): ?><input type="hidden" name="can_manage_other_users" value="1"><?php endif; ?>
                    <?php if ($metadataLocked && !empty($roleSelected['requires_center'])): ?><input type="hidden" name="requires_center" value="1"><?php endif; ?>
                </div>
            </div>

            <h3 class="role-section-title">حوزه‌های مجاز نقش</h3>
            <div class="role-checks">
                <?php foreach ($scopeTypes as $scopeItem): ?>
                    <?php $scopeCode = (string) ($scopeItem['code'] ?? ''); ?>
                    <label class="role-check">
                        <input type="checkbox" name="scope_types[]" value="<?= admin_h($scopeCode) ?>" <?= isset($selectedScopes[$scopeCode]) ? 'checked' : '' ?>>
                        <span><strong><?= admin_h($scopeItem['title'] ?? '') ?></strong></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <h3 class="role-section-title">اطلاعات لازم برای فعال‌شدن نقش</h3>
            <div class="role-checks">
                <?php foreach ($identityFields as $identityCode => $identityName): ?>
                    <label class="role-check">
                        <input type="checkbox" name="identity_fields[]" value="<?= admin_h($identityCode) ?>" <?= isset($selectedIdentity[$identityCode]) ? 'checked' : '' ?>>
                        <span><?= admin_h($identityName) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <h3 class="role-section-title">مجوزهای نقش</h3>
            <div class="role-permissions">
                <?php foreach ($rolePermissionGroups as $groupName => $groupItems): ?>
                    <?php
                    $groupSelected = 0;
                    foreach ($groupItems as $groupItem) {
                        if (isset($selectedPermissions[(string) ($groupItem['code'] ?? '')])) {
                            $groupSelected++;
                        }
                    }
                    ?>
                    <details>
                        <summary><?= admin_h($groupName) ?> <span class="role-badge"><?= $groupSelected ?> از <?= count($groupItems) ?></span></summary>
                        <div class="role-checks">
                            <?php foreach ($groupItems as $permissionItem): ?>
                                <?php
                                $permissionCode = (string) ($permissionItem['code'] ?? '');
                                $permissionSelected = isset($selectedPermissions[$permissionCode]);
                                $permissionManageable = !empty($permissionItem['manageable']);
                                ?>
                                <label class="role-check <?= $permissionManageable ? '' : 'is-locked' ?>">
                                    <input type="checkbox" name="permissions[]" value="<?= admin_h($permissionCode) ?>" <?= $permissionSelected ? 'checked' : '' ?> <?= $permissionManageable ? '' : 'disabled' ?>>
                                    <span><?= admin_h($permissionItem['title'] ?? 'مجوز') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div class="role-actions">
                <textarea name="reason" required minlength="3" maxlength="500" placeholder="دلیل <?= $roleEditing ? 'تغییر' : 'ایجاد' ?> نقش برای ثبت در تاریخچه"></textarea>
                <button class="admin-button" type="submit"><?= $roleEditing ? 'ذخیره تغییرات نقش' : 'ایجاد نقش' ?></button>
            </div>
        </form>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
