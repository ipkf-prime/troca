<?php
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

$page = $page ?? [];
$form = $page['form'] ?? [];
$roles = $page['roles'] ?? [];
$statusOptions = $page['status_options'] ?? [];
$errors = $errors ?? [];
$isEdit = !empty($page['is_edit']);
$userId = (int) ($form['id'] ?? 0);
$formAction = $isEdit
    ? '/admin/users/' . $userId
    : '/admin/users';
$selectedRoleIds = array_map(
    'intval',
    is_array($form['role_ids'] ?? null)
        ? $form['role_ids']
        : []
);
$status = (string) ($status ?? '');

ob_start();
?>
<style>
.admin-user-form-grid {
    display: grid;
    gap: .9rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.admin-user-form-grid > label,
.admin-user-form-grid > fieldset {
    min-width: 0;
}

.admin-user-form-grid .admin-user-form-wide {
    grid-column: 1 / -1;
}

.admin-user-form-grid label > span,
.admin-user-form-roles > legend {
    display: block;
    font-size: .8rem;
    font-weight: 800;
    margin-bottom: .35rem;
}

.admin-user-form-roles {
    border: 1px solid var(--admin-border);
    border-radius: .9rem;
    margin: 0;
    padding: .8rem;
}

.admin-user-role-grid {
    display: grid;
    gap: .55rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.admin-user-role-option {
    align-items: flex-start;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .75rem;
    display: flex;
    gap: .55rem;
    padding: .65rem;
}

.admin-user-role-option input {
    flex: 0 0 auto;
    margin-top: .2rem;
}

.admin-user-role-option strong,
.admin-user-role-option small {
    display: block;
}

.admin-user-role-option small {
    color: var(--admin-text-muted);
    direction: ltr;
    font-size: .7rem;
    margin-top: .15rem;
}

.admin-user-switches {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
}

.admin-user-switch {
    align-items: center;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: .7rem;
    display: flex;
    gap: .45rem;
    padding: .55rem .7rem;
}

.admin-user-form-note {
    background: var(--admin-primary-soft);
    border: 1px solid color-mix(
        in srgb,
        var(--admin-primary) 25%,
        transparent
    );
    border-radius: .75rem;
    font-size: .78rem;
    line-height: 1.9;
    padding: .65rem .8rem;
}

@media (max-width: 900px) {
    .admin-user-role-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .admin-user-form-grid,
    .admin-user-role-grid {
        grid-template-columns: 1fr;
    }

    .admin-user-form-grid .admin-user-form-wide {
        grid-column: auto;
    }
}
</style>

<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/modules/users">مدیریت کاربران</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/users">کاربران</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'ویرایش کاربر' : 'ایجاد کاربر' ?></span>
</nav>

<section class="admin-module-hub admin-module-hub--blue">
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html('users') ?>
    </div>
    <div>
        <h2><?= $isEdit ? 'ویرایش کاربر' : 'ایجاد کاربر جدید' ?></h2>
        <p>
            ثبت مشخصات هویتی، اطلاعات ورود، وضعیت حساب و نقش‌های سراسری
        </p>
    </div>
    <a class="admin-module-hub__back" href="/admin/users">
        بازگشت به کاربران
    </a>
</section>

<?php if ($status === 'saved'): ?>
    <section class="admin-section">
        <div class="admin-alert admin-alert--success">
            تغییرات کاربر با موفقیت ذخیره شد.
        </div>
    </section>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <section class="admin-section">
        <div class="admin-alert admin-alert--danger" role="alert">
            <strong>ذخیره کاربر انجام نشد.</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= admin_h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<section class="admin-section">
    <form method="post" action="<?= admin_h($formAction) ?>">
        <input
            type="hidden"
            name="_token"
            value="<?= admin_h(
                (new \IPKF\Security\Csrf())->token()
            ) ?>"
        >

        <div class="admin-user-form-grid">
            <label>
                <span>نام</span>
                <input
                    name="first_name"
                    value="<?= admin_h($form['first_name'] ?? '') ?>"
                    maxlength="100"
                    autocomplete="given-name"
                    required
                    autofocus
                >
            </label>

            <label>
                <span>نام خانوادگی</span>
                <input
                    name="last_name"
                    value="<?= admin_h($form['last_name'] ?? '') ?>"
                    maxlength="100"
                    autocomplete="family-name"
                    required
                >
            </label>

            <label>
                <span>نام کاربری</span>
                <input
                    name="username"
                    value="<?= admin_h($form['username'] ?? '') ?>"
                    maxlength="32"
                    pattern="[a-z][a-z0-9_]{1,30}[a-z0-9]"
                    autocomplete="username"
                    dir="ltr"
                    required
                >
                <small class="admin-muted">
                    حروف انگلیسی، عدد و زیرخط؛ با حرف شروع شود.
                </small>
            </label>

            <label>
                <span>وضعیت حساب</span>
                <select name="status" required>
                    <?php foreach ($statusOptions as $code => $label): ?>
                        <option
                            value="<?= admin_h($code) ?>"
                            <?= (string) ($form['status'] ?? 'active')
                                === (string) $code
                                ? ' selected'
                                : '' ?>
                        >
                            <?= admin_h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>ایمیل</span>
                <input
                    type="email"
                    name="email"
                    value="<?= admin_h($form['email'] ?? '') ?>"
                    maxlength="190"
                    autocomplete="email"
                    dir="ltr"
                >
            </label>

            <label>
                <span>شماره موبایل</span>
                <input
                    name="mobile"
                    value="<?= admin_h($form['mobile'] ?? '') ?>"
                    maxlength="15"
                    inputmode="tel"
                    autocomplete="tel"
                    placeholder="09123456789"
                    dir="ltr"
                >
            </label>

            <div class="admin-user-switches admin-user-form-wide">
                <label class="admin-user-switch">
                    <input type="hidden" name="email_verified" value="0">
                    <input
                        type="checkbox"
                        name="email_verified"
                        value="1"
                        <?= !empty($form['email_verified'])
                            ? ' checked'
                            : '' ?>
                    >
                    <span>ایمیل تأییدشده است</span>
                </label>

                <label class="admin-user-switch">
                    <input type="hidden" name="mobile_verified" value="0">
                    <input
                        type="checkbox"
                        name="mobile_verified"
                        value="1"
                        <?= !empty($form['mobile_verified'])
                            ? ' checked'
                            : '' ?>
                    >
                    <span>موبایل تأییدشده است</span>
                </label>
            </div>

            <label>
                <span>
                    <?= $isEdit
                        ? 'رمز عبور جدید'
                        : 'رمز عبور اولیه' ?>
                </span>
                <input
                    type="password"
                    name="password"
                    minlength="10"
                    maxlength="200"
                    autocomplete="new-password"
                    <?= $isEdit ? '' : ' required' ?>
                >
                <?php if ($isEdit): ?>
                    <small class="admin-muted">
                        برای حفظ رمز فعلی، این قسمت را خالی بگذارید.
                    </small>
                <?php endif; ?>
            </label>

            <label>
                <span>تکرار رمز عبور</span>
                <input
                    type="password"
                    name="password_confirmation"
                    minlength="10"
                    maxlength="200"
                    autocomplete="new-password"
                    <?= $isEdit ? '' : ' required' ?>
                >
            </label>

            <fieldset class="admin-user-form-roles admin-user-form-wide">
                <legend>نقش‌های سراسری</legend>

                <div class="admin-user-form-note">
                    نقش پایه «کاربر» همیشه فعال می‌ماند.
                    تغییرات این بخش فقط نقش‌های سراسری را مدیریت می‌کند
                    و به انتصاب‌های استانی، سازمانی یا محدوده‌دار آسیبی نمی‌زند.
                </div>

                <div class="admin-user-role-grid" style="margin-top:.7rem">
                    <?php foreach ($roles as $role): ?>
                        <?php
                        $roleId = (int) ($role['id'] ?? 0);
                        $roleCode = (string) ($role['code'] ?? '');
                        $isBaseRole = $roleCode === 'user';
                        ?>
                        <label class="admin-user-role-option">
                            <input
                                type="checkbox"
                                name="role_ids[]"
                                value="<?= $roleId ?>"
                                <?= in_array(
                                    $roleId,
                                    $selectedRoleIds,
                                    true
                                ) || $isBaseRole
                                    ? ' checked'
                                    : '' ?>
                                <?= $isBaseRole ? ' disabled' : '' ?>
                            >
                            <?php if ($isBaseRole): ?>
                                <input
                                    type="hidden"
                                    name="role_ids[]"
                                    value="<?= $roleId ?>"
                                >
                            <?php endif; ?>
                            <span>
                                <strong><?= admin_h(
                                    $role['title'] ?? ''
                                ) ?></strong>
                                <small><?= admin_h($roleCode) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>

        <div class="admin-form-actions" style="margin-top:1rem">
            <button class="admin-button" type="submit">
                <?= $isEdit
                    ? 'ذخیره تغییرات'
                    : 'ایجاد کاربر' ?>
            </button>
            <a class="admin-button admin-button--soft" href="/admin/users">
                انصراف
            </a>
            <?php if ($isEdit): ?>
                <a
                    class="admin-button admin-button--soft"
                    href="<?= admin_h('/admin/users/' . $userId) ?>"
                >
                    مشاهده جزئیات
                </a>
            <?php endif; ?>
        </div>
    </form>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
