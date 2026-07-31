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
?>
<style>
.work-settings-layout{display:grid;grid-template-columns:260px minmax(0,1fr);gap:16px}
.work-settings-nav{display:grid;gap:8px;align-content:start}
.work-settings-nav a{border:1px solid var(--admin-border);border-radius:12px;color:inherit;display:block;padding:11px 12px;text-decoration:none}
.work-settings-nav a.is-active{background:var(--admin-primary-soft);border-color:var(--admin-primary);color:var(--admin-primary-hover)}
.work-settings-form{display:grid;grid-template-columns:1.1fr .9fr 100px 96px 90px auto;gap:8px;align-items:end}
.work-settings-status-form{grid-template-columns:1.1fr .8fr 110px 96px 90px 90px auto}
.work-settings-code{direction:ltr;text-align:left}
.work-settings-color{min-width:68px;padding:4px}
.work-settings-row{border-bottom:1px solid var(--admin-border);padding:13px 0}
.work-settings-row:last-child{border-bottom:0}
.work-settings-row__meta{align-items:center;display:flex;gap:8px;margin-bottom:8px}
.work-settings-create{background:var(--admin-surface-muted);border:1px dashed var(--admin-border);border-radius:14px;margin-top:16px;padding:14px}
@media(max-width:1050px){
  .work-settings-layout{grid-template-columns:1fr}
  .work-settings-nav{grid-template-columns:repeat(2,minmax(0,1fr))}
  .work-settings-form,.work-settings-status-form{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:640px){
  .work-settings-nav{grid-template-columns:1fr}
  .work-settings-form,.work-settings-status-form{grid-template-columns:1fr}
}
</style>

<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <span>تنظیمات</span>
</nav>

<section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('sliders') ?></div>
    <div>
        <h2>تنظیمات مدیریت کار</h2>
        <p>تعاریف، وضعیت‌ها و گزینه‌های قابل استفاده در فرم‌های Work</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/work">بازگشت به داشبورد</a>
</section>

<?php if (isset($_GET['saved'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">تنظیمات با موفقیت ذخیره شد.</div></section>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--danger"><?= admin_h((string) $_GET['error']) ?></div></section>
<?php endif; ?>

<div class="work-settings-layout">
    <aside class="admin-section work-settings-nav">
        <?php foreach ($groups as $row): ?>
            <?php $code = (string) ($row['code'] ?? ''); ?>
            <a
                class="<?= $groupCode === $code ? 'is-active' : '' ?>"
                href="<?= admin_h('/admin/work/settings?group=' . rawurlencode($code)) ?>"
            >
                <strong><?= admin_h($row['title'] ?? $code) ?></strong>
                <small class="admin-muted">
                    <?= admin_h(\App\Support\AdminFormat::digits((int) ($row['item_count'] ?? 0))) ?> گزینه
                </small>
            </a>
        <?php endforeach; ?>
    </aside>

    <main class="admin-section">
        <div class="admin-section__header">
            <div>
                <h2><?= admin_h($group['title'] ?? '') ?></h2>
                <p class="admin-muted"><?= admin_h($group['description'] ?? '') ?></p>
            </div>
            <span class="admin-pill">
                <?= admin_h(match ($managementMode) {
                    'structural' => 'ساختاری',
                    'fixed' => 'کدهای ثابت',
                    default => 'قابل توسعه',
                }) ?>
            </span>
        </div>

        <?php if ($items === []): ?>
            <p class="admin-empty-state">گزینه‌ای در این گروه ثبت نشده است.</p>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="work-settings-row">
                    <div class="work-settings-row__meta">
                        <code class="work-settings-code"><?= admin_h($item['code'] ?? '') ?></code>
                        <?php if (!empty($item['is_system'])): ?><span class="admin-pill">سیستمی</span><?php endif; ?>
                        <?php if (!empty($item['is_locked'])): ?><span class="admin-pill">قفل ساختاری</span><?php endif; ?>
                        <?php if ((int) ($item['usage_count'] ?? 0) > 0): ?>
                            <small class="admin-muted">
                                استفاده‌شده در <?= admin_h(\App\Support\AdminFormat::digits((int) $item['usage_count'])) ?> رکورد
                            </small>
                        <?php endif; ?>
                    </div>

                    <?php if ($isStatusGroup): ?>
                        <form
                            method="post"
                            action="<?= admin_h('/admin/work/settings/statuses/' . (int) $item['id']) ?>"
                            class="work-settings-form work-settings-status-form"
                        >
                            <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                            <label>
                                <span>عنوان</span>
                                <input name="title" value="<?= admin_h($item['title'] ?? '') ?>" maxlength="190" required>
                            </label>
                            <label>
                                <span>دسته</span>
                                <select name="category">
                                    <?php foreach ($categoryOptions as $code => $title): ?>
                                        <option value="<?= admin_h($code) ?>"<?= (string) ($item['category'] ?? '') === $code ? ' selected' : '' ?>>
                                            <?= admin_h($title) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>رنگ</span>
                                <input class="work-settings-color" type="color" name="color" value="<?= admin_h(($item['color'] ?? '') ?: '#64748b') ?>">
                            </label>
                            <label>
                                <span>ترتیب</span>
                                <input type="number" name="sort_order" value="<?= admin_h($item['sort_order'] ?? 0) ?>">
                            </label>
                            <label>
                                <input type="hidden" name="is_closed" value="0">
                                <input type="checkbox" name="is_closed" value="1"<?= !empty($item['is_closed']) ? ' checked' : '' ?>>
                                بسته
                            </label>
                            <label>
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1"<?= !empty($item['is_active']) ? ' checked' : '' ?>>
                                فعال
                            </label>
                            <button class="admin-button admin-button--compact" type="submit">ذخیره</button>
                        </form>
                    <?php else: ?>
                        <form
                            method="post"
                            action="<?= admin_h('/admin/work/settings/reference/' . rawurlencode($groupCode) . '/' . (int) $item['id']) ?>"
                            class="work-settings-form"
                        >
                            <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                            <label>
                                <span>عنوان فارسی</span>
                                <input name="title_fa" value="<?= admin_h($item['title_fa'] ?? '') ?>" maxlength="190" required>
                            </label>
                            <label>
                                <span>عنوان انگلیسی</span>
                                <input name="title_en" value="<?= admin_h($item['title_en'] ?? '') ?>" maxlength="190" dir="ltr">
                            </label>
                            <label>
                                <span>رنگ</span>
                                <input class="work-settings-color" type="color" name="color" value="<?= admin_h(($item['color'] ?? '') ?: '#64748b') ?>">
                            </label>
                            <label>
                                <span>ترتیب</span>
                                <input type="number" name="sort_order" value="<?= admin_h($item['sort_order'] ?? 0) ?>">
                            </label>
                            <label>
                                <input type="hidden" name="is_active" value="0">
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    <?= !empty($item['is_active']) ? ' checked' : '' ?>
                                    <?= $managementMode === 'structural' ? ' disabled' : '' ?>
                                >
                                فعال
                            </label>
                            <?php if ($managementMode === 'structural'): ?>
                                <input type="hidden" name="is_active" value="1">
                            <?php endif; ?>
                            <button class="admin-button admin-button--compact" type="submit">ذخیره</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($canCreate): ?>
            <section class="work-settings-create">
                <h3>افزودن گزینه جدید</h3>

                <?php if ($isStatusGroup): ?>
                    <form method="post" action="/admin/work/settings/statuses" class="work-settings-form work-settings-status-form">
                        <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                        <label>
                            <span>کد فنی</span>
                            <input class="work-settings-code" name="code" maxlength="80" pattern="[a-z][a-z0-9_]{1,78}" required placeholder="waiting_customer">
                        </label>
                        <label>
                            <span>عنوان</span>
                            <input name="title" maxlength="190" required>
                        </label>
                        <label>
                            <span>دسته</span>
                            <select name="category">
                                <?php foreach ($categoryOptions as $code => $title): ?>
                                    <option value="<?= admin_h($code) ?>"><?= admin_h($title) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>رنگ</span>
                            <input class="work-settings-color" type="color" name="color" value="#64748b">
                        </label>
                        <label>
                            <span>ترتیب</span>
                            <input type="number" name="sort_order" value="100">
                        </label>
                        <label>
                            <input type="hidden" name="is_closed" value="0">
                            <input type="checkbox" name="is_closed" value="1"> بسته
                        </label>
                        <input type="hidden" name="is_active" value="1">
                        <button class="admin-button" type="submit">افزودن</button>
                    </form>
                <?php else: ?>
                    <form
                        method="post"
                        action="<?= admin_h('/admin/work/settings/reference/' . rawurlencode($groupCode)) ?>"
                        class="work-settings-form"
                    >
                        <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                        <label>
                            <span>کد فنی</span>
                            <input class="work-settings-code" name="code" maxlength="80" pattern="[a-z][a-z0-9_]{1,78}" required>
                        </label>
                        <label>
                            <span>عنوان فارسی</span>
                            <input name="title_fa" maxlength="190" required>
                        </label>
                        <label>
                            <span>عنوان انگلیسی</span>
                            <input name="title_en" maxlength="190" dir="ltr">
                        </label>
                        <label>
                            <span>رنگ</span>
                            <input class="work-settings-color" type="color" name="color" value="#64748b">
                        </label>
                        <label>
                            <span>ترتیب</span>
                            <input type="number" name="sort_order" value="100">
                        </label>
                        <input type="hidden" name="is_active" value="1">
                        <button class="admin-button" type="submit">افزودن</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
