<?php

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$workspace = $workspace ?? [
    'ok' => false,
    'organizations' => [],
    'units' => [],
    'organization_positions' => [],
    'users' => [],
    'persons' => [],
    'records' => [
        'organizations' => [],
        'units' => [],
        'positions' => [],
        'identities' => [],
    ],
];
$records = $workspace['records'] ?? [];
$message = $_SESSION['admin_flash_message'] ?? null;
$error = $_SESSION['admin_flash_error'] ?? null;
$activeTab = $_SESSION['admin_organization_setup_tab'] ?? 'organization';
unset($_SESSION['admin_flash_message'], $_SESSION['admin_flash_error'], $_SESSION['admin_organization_setup_tab']);

$allowedTabs = ['organization', 'unit', 'position', 'identity', 'summary'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'organization';
}

$token = admin_h((new \IPKF\Security\Csrf())->token());
$digits = static fn (mixed $value): string => \App\Support\AdminFormat::digits($value);
$statusBadge = static function (string $status, string $activeLabel = 'فعال'): string {
    $isActive = in_array($status, ['active', '1'], true);
    $class = $isActive ? 'active' : 'inactive';
    $label = $isActive ? $activeLabel : 'غیرفعال';

    return '<span class="admin-status-badge admin-status-badge--' . $class . '">' . admin_h($label) . '</span>';
};

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/modules/organization">ساختار سازمانی</a><span>/</span>
    <span>راه‌اندازی ساختار</span>
</nav>

<section class="admin-module-hub admin-module-hub--teal admin-users-heading">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('organization') ?></div>
    <div>
        <h2>راه‌اندازی ساختار سازمانی</h2>
        <p>اطلاعات پایه را مرحله‌به‌مرحله ثبت کنید؛ نتیجه هر مرحله در همان بخش نمایش داده می‌شود.</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/organization-chart">مشاهده چارت</a>
</section>

<?php if ($message): ?>
    <div class="admin-alert admin-alert--success" role="status"><?= admin_h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="admin-alert admin-alert--danger" role="alert"><?= admin_h($error) ?></div>
<?php endif; ?>

<?php if (!$workspace['ok']): ?>
    <div class="admin-alert admin-alert--danger">دریافت اطلاعات پایه سازمانی ممکن نیست.</div>
<?php else: ?>
    <section class="admin-section admin-tab-workspace" data-active-tab="<?= admin_h($activeTab) ?>">
        <div class="admin-setup-tabs" role="tablist" aria-label="بخش‌های راه‌اندازی ساختار سازمانی">
            <button type="button" class="admin-setup-tab" data-tab="organization" role="tab">سازمان</button>
            <button type="button" class="admin-setup-tab" data-tab="unit" role="tab">واحد سازمانی</button>
            <button type="button" class="admin-setup-tab" data-tab="position" role="tab">پست سازمانی</button>
            <button type="button" class="admin-setup-tab" data-tab="identity" role="tab">اتصال کاربر به شخص</button>
            <button type="button" class="admin-setup-tab" data-tab="summary" role="tab">خلاصه و اقدامات</button>
        </div>

        <div class="admin-setup-panel" data-panel="organization" role="tabpanel">
            <div class="admin-panel-heading">
                <div><h3>ثبت سازمان</h3><p>سازمان اصلی یا زیرمجموعه را تعریف کنید.</p></div>
            </div>
            <form method="post" action="/admin/organization-setup" class="admin-form-grid admin-setup-form">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="create_organization">
                <label><span>عنوان فارسی</span><input name="title_fa" required maxlength="255"></label>
                <label><span>عنوان انگلیسی</span><input name="title_en" dir="ltr" maxlength="255"></label>
                <label><span>عنوان کوتاه</span><input name="short_title" maxlength="150"></label>
                <label><span>سازمان بالادست</span><select name="parent_reference"><option value="">بدون بالادست</option><?php foreach ($workspace['organizations'] as $organization): ?><option value="<?= admin_h($organization['public_reference']) ?>"><?= admin_h($organization['display_path'] ?? $organization['title']) ?></option><?php endforeach; ?></select></label>
                <label><span>ترتیب نمایش</span><input type="number" name="sort_order" min="0" value="0"></label>
                <div class="admin-form-actions admin-form-grid__wide"><button class="admin-button" type="submit">ثبت سازمان</button></div>
            </form>

            <div class="admin-records-block">
                <div class="admin-records-block__head"><div><h4>اطلاعات ثبت‌شده</h4><p>آخرین سازمان‌های ثبت‌شده</p></div><span><?= admin_h($digits(count($records['organizations'] ?? []))) ?> مورد</span></div>
                <?php if (($records['organizations'] ?? []) === []): ?>
                    <div class="admin-empty-state admin-empty-state--compact">هنوز سازمانی ثبت نشده است.</div>
                <?php else: ?>
                    <div class="admin-record-table-wrap"><table class="admin-table admin-record-table"><thead><tr><th>عنوان</th><th>عنوان کوتاه</th><th>سازمان بالادست</th><th>ترتیب</th><th>وضعیت</th></tr></thead><tbody>
                    <?php foreach ($records['organizations'] as $row): ?><tr>
                        <td data-label="عنوان"><strong><?= admin_h($row['title']) ?></strong><?php if (!empty($row['title_en'])): ?><small dir="ltr"><?= admin_h($row['title_en']) ?></small><?php endif; ?></td>
                        <td data-label="عنوان کوتاه"><?= admin_h($row['short_title'] ?: '—') ?></td>
                        <td data-label="سازمان بالادست"><?= admin_h($row['parent_title'] ?: 'بدون بالادست') ?></td>
                        <td data-label="ترتیب"><?= admin_h($digits($row['sort_order'] ?? 0)) ?></td>
                        <td data-label="وضعیت"><?= $statusBadge((string) ($row['is_active'] ?? '0')) ?></td>
                    </tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-setup-panel" data-panel="unit" role="tabpanel">
            <div class="admin-panel-heading">
                <div><h3>ثبت واحد سازمانی</h3><p>واحد را در سازمان و زیر واحد بالادست مناسب قرار دهید.</p></div>
            </div>
            <form method="post" action="/admin/organization-setup" class="admin-form-grid admin-setup-form">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="create_unit">
                <label><span>سازمان</span><select name="organization_reference" required><option value="">انتخاب سازمان</option><?php foreach ($workspace['organizations'] as $organization): ?><option value="<?= admin_h($organization['public_reference']) ?>"><?= admin_h($organization['display_path'] ?? $organization['title']) ?></option><?php endforeach; ?></select></label>
                <label><span>واحد بالادست</span><select name="parent_reference"><option value="">بدون بالادست</option><?php foreach ($workspace['units'] as $unit): ?><option value="<?= admin_h($unit['public_reference']) ?>"><?= admin_h($unit['display_path'] ?? $unit['title']) ?></option><?php endforeach; ?></select></label>
                <label><span>عنوان فارسی</span><input name="title_fa" required maxlength="255"></label>
                <label><span>عنوان انگلیسی</span><input name="title_en" dir="ltr" maxlength="255"></label>
                <label><span>کد واحد</span><input name="code" dir="ltr" maxlength="100"></label>
                <label><span>ترتیب نمایش</span><input type="number" name="sort_order" min="0" value="0"></label>
                <label class="admin-form-grid__wide"><span>توضیحات</span><textarea name="description" rows="3" maxlength="2000"></textarea></label>
                <div class="admin-form-actions admin-form-grid__wide"><button class="admin-button" type="submit">ثبت واحد</button></div>
            </form>

            <div class="admin-records-block">
                <div class="admin-records-block__head"><div><h4>اطلاعات ثبت‌شده</h4><p>آخرین واحدهای سازمانی ثبت‌شده</p></div><span><?= admin_h($digits(count($records['units'] ?? []))) ?> مورد</span></div>
                <?php if (($records['units'] ?? []) === []): ?>
                    <div class="admin-empty-state admin-empty-state--compact">هنوز واحد سازمانی ثبت نشده است.</div>
                <?php else: ?>
                    <div class="admin-record-table-wrap"><table class="admin-table admin-record-table"><thead><tr><th>واحد</th><th>سازمان</th><th>واحد بالادست</th><th>کد</th><th>وضعیت</th></tr></thead><tbody>
                    <?php foreach ($records['units'] as $row): ?><tr>
                        <td data-label="واحد"><strong><?= admin_h($row['title']) ?></strong><?php if (!empty($row['title_en'])): ?><small dir="ltr"><?= admin_h($row['title_en']) ?></small><?php endif; ?></td>
                        <td data-label="سازمان"><?= admin_h($row['organization_title']) ?></td>
                        <td data-label="واحد بالادست"><?= admin_h($row['parent_title'] ?: 'بدون بالادست') ?></td>
                        <td data-label="کد"><span dir="ltr"><?= admin_h($row['code'] ?: '—') ?></span></td>
                        <td data-label="وضعیت"><?= $statusBadge((string) ($row['status'] ?? '')) ?></td>
                    </tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-setup-panel" data-panel="position" role="tabpanel">
            <div class="admin-panel-heading">
                <div><h3>تعریف پست سازمانی</h3><p>پست را در ستاد سازمان یا یک واحد مشخص تعریف کنید.</p></div>
            </div>
            <form method="post" action="/admin/organization-setup" class="admin-form-grid admin-setup-form">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="create_position">
                <label><span>سازمان</span><select name="organization_reference" required><option value="">انتخاب سازمان</option><?php foreach ($workspace['organizations'] as $organization): ?><option value="<?= admin_h($organization['public_reference']) ?>"><?= admin_h($organization['display_path'] ?? $organization['title']) ?></option><?php endforeach; ?></select></label>
                <label><span>واحد</span><select name="unit_reference"><option value="">ستاد سازمان</option><?php foreach ($workspace['units'] as $unit): ?><option value="<?= admin_h($unit['public_reference']) ?>"><?= admin_h($unit['display_path'] ?? $unit['title']) ?></option><?php endforeach; ?></select></label>
                <label><span>عنوان فارسی</span><input name="title_fa" required maxlength="255"></label>
                <label><span>عنوان انگلیسی</span><input name="title_en" dir="ltr" maxlength="255"></label>
                <label><span>کد پست</span><input name="code" dir="ltr" maxlength="100"></label>
                <label><span>ظرفیت تصدی</span><input type="number" name="headcount_limit" min="1" value="1"></label>
                <label><span>ترتیب نمایش</span><input type="number" name="sort_order" min="0" value="0"></label>
                <label class="admin-inline-check"><input type="checkbox" name="is_head" value="1"><span>مسئول واحد</span></label>
                <div class="admin-form-actions admin-form-grid__wide"><button class="admin-button" type="submit">تعریف پست</button></div>
            </form>

            <div class="admin-records-block">
                <div class="admin-records-block__head"><div><h4>اطلاعات ثبت‌شده</h4><p>آخرین پست‌های سازمانی تعریف‌شده</p></div><span><?= admin_h($digits(count($records['positions'] ?? []))) ?> مورد</span></div>
                <?php if (($records['positions'] ?? []) === []): ?>
                    <div class="admin-empty-state admin-empty-state--compact">هنوز پست سازمانی تعریف نشده است.</div>
                <?php else: ?>
                    <div class="admin-record-table-wrap"><table class="admin-table admin-record-table"><thead><tr><th>پست</th><th>سازمان/واحد</th><th>کد</th><th>ظرفیت</th><th>نوع</th><th>وضعیت</th></tr></thead><tbody>
                    <?php foreach ($records['positions'] as $row): ?><tr>
                        <td data-label="پست"><strong><?= admin_h($row['title']) ?></strong><?php if (!empty($row['title_en'])): ?><small dir="ltr"><?= admin_h($row['title_en']) ?></small><?php endif; ?></td>
                        <td data-label="سازمان/واحد"><?= admin_h($row['organization_title']) ?><small><?= admin_h($row['unit_title'] ?: 'ستاد سازمان') ?></small></td>
                        <td data-label="کد"><span dir="ltr"><?= admin_h($row['code'] ?: '—') ?></span></td>
                        <td data-label="ظرفیت"><?= admin_h($digits($row['headcount_limit'] ?? 1)) ?></td>
                        <td data-label="نوع"><?= !empty($row['is_head']) ? '<span class="admin-status-badge admin-status-badge--info">مسئول واحد</span>' : 'عادی' ?></td>
                        <td data-label="وضعیت"><?= $statusBadge((string) ($row['status'] ?? '')) ?></td>
                    </tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-setup-panel" data-panel="identity" role="tabpanel">
            <div class="admin-panel-heading">
                <div><h3>اتصال کاربر به شخص</h3><p>هویت ورود را به رکورد شخص حقیقی متصل کنید.</p></div>
            </div>
            <form method="post" action="/admin/organization-setup" class="admin-form-grid admin-setup-form">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="link_user_person">
                <label><span>حساب کاربری</span><select name="user_id" required><option value="">انتخاب کاربر</option><?php foreach ($workspace['users'] as $user): ?><option value="<?= admin_h($user['id']) ?>"><?= admin_h(($user['label'] ?? 'حساب کاربری') . ($user['person_name'] ? ' ← ' . $user['person_name'] : '')) ?></option><?php endforeach; ?></select></label>
                <label><span>شخص</span><select name="person_reference" required><option value="">انتخاب شخص</option><?php foreach ($workspace['persons'] as $person): ?><option value="<?= admin_h($person['public_reference']) ?>"><?= admin_h($person['title'] . ($person['national_code'] ? ' — ' . $person['national_code'] : '')) ?></option><?php endforeach; ?></select></label>
                <div class="admin-form-actions admin-form-grid__wide"><button class="admin-button" type="submit">ثبت اتصال</button></div>
            </form>
            <div class="admin-alert admin-alert--warning admin-alert--compact">هر شخص فقط به یک حساب کاربری متصل می‌شود. اتصال مجدد یک کاربر، رابطه قبلی همان کاربر را جایگزین می‌کند.</div>

            <div class="admin-records-block">
                <div class="admin-records-block__head"><div><h4>اطلاعات ثبت‌شده</h4><p>آخرین اتصال‌های حساب کاربری و شخص</p></div><span><?= admin_h($digits(count($records['identities'] ?? []))) ?> مورد</span></div>
                <?php if (($records['identities'] ?? []) === []): ?>
                    <div class="admin-empty-state admin-empty-state--compact">هنوز حساب کاربری به شخص متصل نشده است.</div>
                <?php else: ?>
                    <div class="admin-record-table-wrap"><table class="admin-table admin-record-table"><thead><tr><th>حساب کاربری</th><th>شخص</th><th>وضعیت</th></tr></thead><tbody>
                    <?php foreach ($records['identities'] as $row): ?><tr>
                        <td data-label="حساب کاربری"><strong><?= admin_h($row['user_label']) ?></strong></td>
                        <td data-label="شخص"><?= admin_h($row['person_name']) ?></td>
                        <td data-label="وضعیت"><?= $statusBadge((string) ($row['status'] ?? ''), 'متصل') ?></td>
                    </tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-setup-panel" data-panel="summary" role="tabpanel">
            <div class="admin-panel-heading">
                <div><h3>خلاصه و اقدامات بعدی</h3><p>وضعیت داده‌های پایه را بررسی و سپس انتصاب‌ها را ثبت کنید.</p></div>
            </div>
            <div class="admin-setup-summary">
                <span><b><?= admin_h($digits(count($workspace['organizations']))) ?></b>سازمان</span>
                <span><b><?= admin_h($digits(count($workspace['units']))) ?></b>واحد</span>
                <span><b><?= admin_h($digits(count($workspace['organization_positions']))) ?></b>پست سازمانی</span>
                <span><b><?= admin_h($digits(count(array_filter($workspace['users'], static fn ($user) => !empty($user['person_id']))))) ?></b>کاربر متصل</span>
            </div>
            <div class="admin-actions-row">
                <a class="admin-button" href="/admin/appointments">ادامه: ثبت انتصاب</a>
                <a class="admin-button admin-button--soft admin-button--compact" href="/admin/organization-chart">مشاهده چارت</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<script>
(function () {
    const root = document.querySelector('.admin-tab-workspace');
    if (!root) return;

    const buttons = [...root.querySelectorAll('[data-tab]')];
    const panels = [...root.querySelectorAll('[data-panel]')];

    function activate(name) {
        buttons.forEach((button) => {
            const active = button.dataset.tab === name;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.panel === name));
        try { history.replaceState(null, '', '#' + name); } catch (error) {}
    }

    const hash = location.hash.replace('#', '');
    const start = buttons.some((button) => button.dataset.tab === hash)
        ? hash
        : (root.dataset.activeTab || 'organization');

    buttons.forEach((button) => button.addEventListener('click', () => activate(button.dataset.tab)));
    activate(start);
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
