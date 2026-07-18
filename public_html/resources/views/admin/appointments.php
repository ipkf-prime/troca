<?php

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$list = $list ?? ['items' => [], 'ok' => false, 'q' => ''];
$options = $options ?? ['persons' => [], 'positions' => []];
$message = $_SESSION['admin_flash_message'] ?? null;
$error = $_SESSION['admin_flash_error'] ?? null;
unset($_SESSION['admin_flash_message'], $_SESSION['admin_flash_error']);

$kindLabels = [
    'permanent' => 'دائم',
    'temporary' => 'موقت',
    'acting' => 'سرپرستی',
    'delegated' => 'تفویضی',
];
$statusLabels = [
    'active' => 'فعال',
    'inactive' => 'غیرفعال',
    'revoked' => 'لغوشده',
    'expired' => 'پایان‌یافته',
];
$statusClass = static fn (string $status): string => match ($status) {
    'active' => 'active',
    'revoked' => 'danger',
    'expired' => 'warning',
    default => 'inactive',
};

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/modules/organization">ساختار سازمانی</a><span>/</span>
    <span>انتصاب‌ها</span>
</nav>

<section class="admin-module-hub admin-module-hub--teal admin-users-heading">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('id-badge') ?></div>
    <div><h2>پست و انتصاب</h2><p>اتصال اشخاص به جایگاه‌های سازمانی با حفظ کامل سوابق</p></div>
    <a class="admin-module-hub__back" href="/admin/organization-chart">مشاهده چارت</a>
</section>

<?php if ($message): ?><div class="admin-alert admin-alert--success" role="status"><?= admin_h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert admin-alert--danger" role="alert"><?= admin_h($error) ?></div><?php endif; ?>

<section class="admin-section">
    <div class="admin-section__header"><div><h3>ثبت انتصاب جدید</h3><p class="admin-muted">شخص، پست و بازه اعتبار را مشخص کنید.</p></div></div>
    <form method="post" action="/admin/appointments" class="admin-form-grid admin-appointment-form">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
        <label><span>شخص</span><select name="person_reference" required><option value="">انتخاب شخص</option><?php foreach ($options['persons'] as $item): ?><option value="<?= admin_h($item['public_reference']) ?>"><?= admin_h($item['title']) ?></option><?php endforeach; ?></select></label>
        <label><span>پست سازمانی</span><select name="position_reference" required><option value="">انتخاب پست</option><?php foreach ($options['positions'] as $item): ?><option value="<?= admin_h($item['public_reference']) ?>"><?= admin_h($item['display_path'] ?? ($item['organization_title'] . ' ← ' . ($item['unit_title'] ?: 'ستاد') . ' ← ' . $item['title'])) ?></option><?php endforeach; ?></select></label>
        <label><span>نوع انتصاب</span><select name="appointment_kind"><?php foreach ($kindLabels as $key => $label): ?><option value="<?= admin_h($key) ?>"><?= admin_h($label) ?></option><?php endforeach; ?></select></label>
        <label><span>تاریخ شروع</span><div class="admin-persian-date" data-persian-datepicker><input type="text" name="valid_from" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="۱۴۰۵/۰۴/۲۷"><button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ"><?= \App\Support\AdminIcon::html('calendar') ?></button></div></label>
        <label><span>تاریخ پایان</span><div class="admin-persian-date" data-persian-datepicker><input type="text" name="valid_to" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="۱۴۰۵/۱۲/۲۹"><button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ"><?= \App\Support\AdminIcon::html('calendar') ?></button></div></label>
        <label class="admin-check-field admin-appointment-form__primary"><input type="checkbox" name="is_primary" value="1"><span>جایگاه اصلی شخص</span></label>
        <label><span>شماره یا مرجع حکم</span><input name="appointment_reference" maxlength="150"></label>
        <label class="admin-form-grid__wide"><span>توضیحات</span><textarea name="description" rows="3" maxlength="2000"></textarea></label>
        <div class="admin-form-actions admin-form-grid__wide"><button class="admin-button" type="submit">ثبت انتصاب</button></div>
    </form>
</section>

<section class="admin-section">
    <div class="admin-section__header"><div><h3>سوابق انتصاب</h3><p class="admin-muted">فهرست انتصاب‌های ثبت‌شده و وضعیت فعلی آن‌ها</p></div></div>
    <form method="get" class="admin-users-search admin-appointment-search">
        <label for="appointment-search">جستجو</label>
        <div class="admin-users-search__row"><input id="appointment-search" name="q" value="<?= admin_h($list['q']) ?>" placeholder="نام شخص، سازمان، واحد یا پست"><button class="admin-button" type="submit">جستجو</button></div>
    </form>

    <?php if (!$list['ok']): ?>
        <div class="admin-alert admin-alert--danger">دریافت انتصاب‌ها ممکن نیست.</div>
    <?php elseif (!$list['items']): ?>
        <div class="admin-empty-state">انتصابی ثبت نشده است.</div>
    <?php else: ?>
        <div class="admin-record-table-wrap">
            <table class="admin-table admin-record-table">
                <thead><tr><th>شخص</th><th>سازمان/واحد</th><th>پست</th><th>نوع</th><th>بازه اعتبار</th><th>وضعیت</th></tr></thead>
                <tbody>
                <?php foreach ($list['items'] as $appointment): ?>
                    <tr>
                        <td data-label="شخص"><strong><?= admin_h($appointment['person_name']) ?></strong><?php if ($appointment['is_primary']): ?><small><span class="admin-status-badge admin-status-badge--active">جایگاه اصلی</span></small><?php endif; ?></td>
                        <td data-label="سازمان/واحد"><?= admin_h($appointment['organization_title']) ?><small><?= admin_h($appointment['unit_title'] ?: 'ستاد سازمان') ?></small></td>
                        <td data-label="پست"><?= admin_h($appointment['position_title']) ?></td>
                        <td data-label="نوع"><?= admin_h($kindLabels[$appointment['appointment_kind']] ?? $appointment['appointment_kind']) ?></td>
                        <td data-label="بازه اعتبار"><?= admin_h(($appointment['valid_from_fa'] ?: 'بدون تاریخ شروع') . ' تا ' . ($appointment['valid_to_fa'] ?: 'بدون تاریخ پایان')) ?></td>
                        <td data-label="وضعیت"><span class="admin-status-badge admin-status-badge--<?= admin_h($statusClass((string) $appointment['status'])) ?>"><?= admin_h($statusLabels[$appointment['status']] ?? $appointment['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
