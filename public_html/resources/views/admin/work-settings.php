<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$page = $page ?? [];
$groups = $page['groups'] ?? [];
$group = $page['selected_group'] ?? [];
$groupCode = (string) ($page['group_code'] ?? 'item_status');
$items = $page['items'] ?? [];
$categoryOptions = $page['category_options'] ?? [];
$csrf = (new \IPKF\Security\Csrf())->token();
$isStatusGroup = $groupCode === 'item_status';
$managementMode = (string) ($group['management_mode'] ?? 'dynamic');
$canCreate = !empty($group['can_create']);

ob_start();
require __DIR__ . '/work-ui-styles.php';
require __DIR__ . '/work-stage5-ui.php';
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <span>تنظیمات</span>
</nav>

<section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('sliders') ?></div>
    <div><h2>تنظیمات مدیریت کار</h2><p>تعاریف و گزینه‌های قابل استفاده در فرم‌های Work</p></div>
    <a class="admin-module-hub__back" href="/admin/work">بازگشت به داشبورد</a>
</section>

<?php if (isset($_GET['saved'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">تنظیمات با موفقیت ذخیره شد.</div></section>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--danger"><?= admin_h((string) $_GET['error']) ?></div></section>
<?php endif; ?>

<div class="work-settings-layout--minimal">
    <aside class="admin-section work-settings-nav--minimal">
        <?php foreach ($groups as $row): ?>
            <?php $code = (string) ($row['code'] ?? ''); ?>
            <a class="<?= $groupCode === $code ? 'is-active' : '' ?>" href="<?= admin_h('/admin/work/settings?group=' . rawurlencode($code)) ?>">
                <strong><?= admin_h($row['title'] ?? $code) ?></strong>
                <small class="admin-muted"><?= admin_h(\App\Support\AdminFormat::digits((int) ($row['item_count'] ?? 0))) ?></small>
            </a>
        <?php endforeach; ?>
    </aside>

    <main class="admin-section work-settings-main--minimal">
        <div class="admin-section__header">
            <div>
                <h2><?= admin_h($group['title'] ?? '') ?></h2>
                <p class="admin-muted"><?= admin_h($group['description'] ?? '') ?></p>
            </div>
            <span class="admin-pill"><?= admin_h(match ($managementMode) {
                'structural' => 'ساختاری',
                'fixed' => 'کدهای ثابت',
                default => 'قابل توسعه',
            }) ?></span>
        </div>

        <?php if ($items === []): ?>
            <p class="admin-empty-state">گزینه‌ای در این گروه ثبت نشده است.</p>
        <?php else: ?>
            <div class="work-settings-list">
                <?php foreach ($items as $item): ?>
                    <div class="work-settings-row--minimal">
                        <div class="work-settings-row__meta--minimal">
                            <code><?= admin_h($item['code'] ?? '') ?></code>
                            <?php if (!empty($item['is_system'])): ?><span class="admin-pill">سیستمی</span><?php endif; ?>
                            <?php if (!empty($item['is_locked'])): ?><span class="admin-pill">قفل ساختاری</span><?php endif; ?>
                            <?php if ((int) ($item['usage_count'] ?? 0) > 0): ?>
                                <small class="admin-muted">استفاده در <?= admin_h(\App\Support\AdminFormat::digits((int) $item['usage_count'])) ?> رکورد</small>
                            <?php endif; ?>
                        </div>

                        <?php if ($isStatusGroup): ?>
                            <form method="post" action="<?= admin_h('/admin/work/settings/statuses/' . (int) $item['id']) ?>" class="work-settings-form--minimal work-settings-form--status">
                                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                                <label><span>عنوان</span><input name="title" value="<?= admin_h($item['title'] ?? '') ?>" maxlength="190" required></label>
                                <label><span>دسته</span><select name="category">
                                    <?php foreach ($categoryOptions as $code => $title): ?>
                                        <option value="<?= admin_h($code) ?>"<?= (string) ($item['category'] ?? '') === $code ? ' selected' : '' ?>><?= admin_h($title) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>رنگ</span><input type="color" name="color" value="<?= admin_h(($item['color'] ?? '') ?: '#64748b') ?>"></label>
                                <label><span>ترتیب</span><input type="number" name="sort_order" value="<?= admin_h($item['sort_order'] ?? 0) ?>"></label>
                                <label class="work-settings-toggle"><span>بسته</span><input type="hidden" name="is_closed" value="0"><input type="checkbox" name="is_closed" value="1"<?= !empty($item['is_closed']) ? ' checked' : '' ?>></label>
                                <label class="work-settings-toggle"><span>فعال</span><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= !empty($item['is_active']) ? ' checked' : '' ?>></label>
                                <button class="admin-button admin-button--compact work-settings-save" type="submit">ذخیره</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= admin_h('/admin/work/settings/reference/' . rawurlencode($groupCode) . '/' . (int) $item['id']) ?>" class="work-settings-form--minimal">
                                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                                <label><span>عنوان فارسی</span><input name="title_fa" value="<?= admin_h($item['title_fa'] ?? '') ?>" maxlength="190" required></label>
                                <label><span>عنوان انگلیسی</span><input name="title_en" value="<?= admin_h($item['title_en'] ?? '') ?>" maxlength="190" dir="ltr"></label>
                                <label><span>رنگ</span><input type="color" name="color" value="<?= admin_h(($item['color'] ?? '') ?: '#64748b') ?>"></label>
                                <label><span>ترتیب</span><input type="number" name="sort_order" value="<?= admin_h($item['sort_order'] ?? 0) ?>"></label>
                                <label class="work-settings-toggle">
                                    <span>فعال</span>
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1"<?= !empty($item['is_active']) ? ' checked' : '' ?><?= $managementMode === 'structural' ? ' disabled' : '' ?>>
                                </label>
                                <?php if ($managementMode === 'structural'): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
                                <button class="admin-button admin-button--compact work-settings-save" type="submit">ذخیره</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($canCreate): ?>
            <details class="work-settings-create--minimal">
                <summary>افزودن گزینه جدید</summary>
                <?php if ($isStatusGroup): ?>
                    <form method="post" action="/admin/work/settings/statuses" class="work-settings-form--minimal work-settings-form--status">
                        <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                        <label><span>کد فنی</span><input name="code" maxlength="80" pattern="[a-z][a-z0-9_]{1,78}" required placeholder="waiting_customer" dir="ltr"></label>
                        <label><span>عنوان</span><input name="title" maxlength="190" required></label>
                        <label><span>دسته</span><select name="category"><?php foreach ($categoryOptions as $code => $title): ?><option value="<?= admin_h($code) ?>"><?= admin_h($title) ?></option><?php endforeach; ?></select></label>
                        <label><span>رنگ</span><input type="color" name="color" value="#64748b"></label>
                        <label><span>ترتیب</span><input type="number" name="sort_order" value="100"></label>
                        <label class="work-settings-toggle"><span>بسته</span><input type="hidden" name="is_closed" value="0"><input type="checkbox" name="is_closed" value="1"></label>
                        <input type="hidden" name="is_active" value="1">
                        <button class="admin-button admin-button--compact work-settings-save" type="submit">افزودن</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= admin_h('/admin/work/settings/reference/' . rawurlencode($groupCode)) ?>" class="work-settings-form--minimal">
                        <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                        <label><span>کد فنی</span><input name="code" maxlength="80" pattern="[a-z][a-z0-9_]{1,78}" required dir="ltr"></label>
                        <label><span>عنوان فارسی</span><input name="title_fa" maxlength="190" required></label>
                        <label><span>عنوان انگلیسی</span><input name="title_en" maxlength="190" dir="ltr"></label>
                        <label><span>رنگ</span><input type="color" name="color" value="#64748b"></label>
                        <label><span>ترتیب</span><input type="number" name="sort_order" value="100"></label>
                        <input type="hidden" name="is_active" value="1">
                        <button class="admin-button admin-button--compact work-settings-save" type="submit">افزودن</button>
                    </form>
                <?php endif; ?>
            </details>
        <?php endif; ?>
    </main>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
