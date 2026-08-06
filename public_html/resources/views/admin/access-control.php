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

$page = is_array($page ?? null) ? $page : [];
$status = trim((string) ($status ?? ''));

if (preg_match('/^\d{3}$/', $status) === 1) {
    $status = '';
}
$tab = (string) ($page['tab'] ?? 'roles');
$roles = is_array($page['roles'] ?? null)
    ? $page['roles']
    : [];
$groups = is_array($page['groups'] ?? null)
    ? $page['groups']
    : [];
$roleMap = is_array($page['role_map'] ?? null)
    ? $page['role_map']
    : [];
$users = is_array($page['users'] ?? null)
    ? $page['users']
    : [];
$selectedUser = is_array($page['selected_user'] ?? null)
    ? $page['selected_user']
    : null;
$assignments = is_array($page['assignments'] ?? null)
    ? $page['assignments']
    : [];
$assignmentId = (int) ($page['assignment_id'] ?? 0);
$overrides = is_array($page['overrides'] ?? null)
    ? $page['overrides']
    : [];
$inherited = is_array($page['inherited'] ?? null)
    ? $page['inherited']
    : [];
$audit = is_array($page['audit'] ?? null)
    ? $page['audit']
    : [];
$csrf = (new \IPKF\Security\Csrf())->token();

$moduleTitles = [
    'access' => 'سطوح و نقش‌های دسترسی',
    'admin' => 'مدیریت سامانه',
    'account' => 'حساب کاربری',
    'auth' => 'احراز هویت',
    'communications' => 'پیام‌ها و اعلان‌ها',
    'messages' => 'پیام‌رسان داخلی',
    'notifications' => 'اعلان‌ها',
    'automation' => 'اتوماسیون اداری',
    'work' => 'مدیریت کار',
    'core' => 'هسته سامانه',
    'users' => 'کاربران',
    'system' => 'زیرساخت سامانه',
    'support' => 'پشتیبانی',
    'organizations' => 'سازمان‌ها',
    'organization' => 'ساختار سازمانی',
];

$groupTitles = [
    'audit' => 'ممیزی و تاریخچه',
    'item' => 'اقلام کار',
    'project' => 'پروژه‌ها',
    'settings' => 'تنظیمات',
    'roles' => 'نقش‌ها',
    'users' => 'کاربران',
    'permissions' => 'مجوزها',
    'dashboard' => 'داشبورد',
    'providers' => 'سرویس‌دهندگان',
    'routing' => 'مسیریابی',
    'preferences' => 'ترجیحات',
    'send' => 'ارسال',
    'reports' => 'گزارش‌ها',
    'messages' => 'پیام‌ها',
    'general' => 'عمومی',
    'profile' => 'پروفایل',
    'security' => 'امنیت حساب',
    'password' => 'امنیت حساب',
    'theme' => 'ظاهر پنل',
    'access' => 'دسترسی',
    'pages' => 'صفحات پنل',
    'navigation' => 'منوها',
    'routes' => 'مسیرها',
    'login_tokens' => 'ورود و نشست',
    'org_units' => 'واحدهای سازمانی',
    'positions' => 'سمت‌ها',
    'user_org_assignments' => 'انتساب کاربران',
    'organizations' => 'سازمان‌ها',
    'diagnostics' => 'پایش و عیب‌یابی',
    'installer' => 'نصب و راه‌اندازی',
    'notification_send' => 'ارسال اعلان',
    'notification_recipients' => 'گیرندگان اعلان',
    'notification_manual_targets' => 'مقصدهای دستی',
    'notification_approval' => 'تأیید اعلان',
];

$groupLabel = static fn ($value): string =>
    $groupTitles[mb_strtolower(trim((string) $value), 'UTF-8')]
        ?? (string) $value;

$scopeTitles = [
    'global' => 'سراسری',
    'organization' => 'سازمان',
    'org_unit' => 'واحد سازمانی',
    'province' => 'استان',
    'county' => 'شهرستان',
    'city' => 'شهر',
    'own' => 'فقط خود کاربر',
];

$scopeLabel = static fn ($value): string =>
    $scopeTitles[mb_strtolower(trim((string) $value), 'UTF-8')]
        ?? (string) $value;

$auditTargetTitles = [
    'role' => 'نقش',
    'user' => 'کاربر',
    'role_assignment' => 'انتساب نقش',
];

$auditTargetLabel = static fn ($value): string =>
    $auditTargetTitles[mb_strtolower(trim((string) $value), 'UTF-8')]
        ?? (string) $value;

$auditChangeTitles = [
    'role_permissions_updated' => 'تغییر مجوزهای نقش',
    'user_policy_updated' => 'تغییر سیاست دسترسی کاربر',
    'user_permission_overrides_updated' =>
        'تغییر استثناهای دسترسی کاربر',
];

$auditChangeLabel = static fn ($value): string =>
    $auditChangeTitles[mb_strtolower(trim((string) $value), 'UTF-8')]
        ?? (string) $value;

$notices = [
    'role_permissions_saved' =>
        ['ok', 'مجوزهای نقش ذخیره شد.'],
    'user_policy_saved' =>
        ['ok', 'سیاست دسترسی کاربر ذخیره شد.'],
    'invalid_csrf' =>
        ['error', 'نشست فرم معتبر نیست.'],
    'access_reason_required' =>
        ['error', 'ثبت دلیل تغییر الزامی است.'],
    'access_role_protected' =>
        ['error', 'نقش مدیر کل محافظت شده است.'],
];

$moduleKeys = array_values(array_keys($groups));
$firstModule = (string) ($moduleKeys[0] ?? '');
$modulePermissionCodes = [];
$totalPermissions = 0;

foreach ($groups as $module => $moduleGroups) {
    $modulePermissionCodes[$module] = [];

    foreach ($moduleGroups as $items) {
        foreach ($items as $permission) {
            $code = (string) ($permission['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $modulePermissionCodes[$module][] = $code;
            $totalPermissions++;
        }
    }
}

$defaultRoleId = 0;

foreach ($roles as $role) {
    if (($role['code'] ?? '') !== 'super_admin') {
        $defaultRoleId = (int) ($role['id'] ?? 0);
        break;
    }
}

if ($defaultRoleId < 1 && $roles !== []) {
    $defaultRoleId = (int) ($roles[0]['id'] ?? 0);
}

$digits = static fn ($value): string =>
    \App\Support\AdminFormat::digits($value);

ob_start();
?>

<?php if (isset($notices[$status])): ?>
    <?php [$type, $message] = $notices[$status]; ?>
    <div class="<?= $type === 'ok'
        ? 'admin-notice'
        : 'admin-alert' ?>">
        <?= admin_h($message) ?>
    </div>
<?php elseif ($status !== ''): ?>
    <div class="admin-alert">
        عملیات انجام نشد:
        <code><?= admin_h($status) ?></code>
    </div>
<?php endif; ?>

<section class="acl-shell" data-acl-shell>
    <header class="acl-hero">
        <div class="acl-hero__copy">
            <span>مرکز کنترل دسترسی</span>
            <h2>سطوح و نقش‌های دسترسی</h2>
            <p>
                مجوزهای نقش و استثناهای کاربران را بدون
                نمایش هم‌زمان همه بخش‌های سامانه مدیریت کنید.
            </p>
        </div>

        <div class="acl-hero__metrics" aria-label="خلاصه دسترسی">
            <article>
                <span>نقش‌ها</span>
                <strong><?= admin_h(
                    $digits(count($roles))
                ) ?></strong>
            </article>
            <article>
                <span>مجوزها</span>
                <strong><?= admin_h(
                    $digits($totalPermissions)
                ) ?></strong>
            </article>
        </div>
    </header>

    <nav class="acl-tabs" aria-label="بخش‌های مدیریت دسترسی">
        <?php foreach ([
            'roles' => 'نقش‌ها و مجوزها',
            'users' => 'دسترسی اختصاصی کاربران',
            'audit' => 'تاریخچه تغییرات',
        ] as $code => $tabTitle): ?>
            <a
                href="/admin/access-control?tab=<?= admin_h($code) ?>"
                class="<?= $tab === $code ? 'is-active' : '' ?>"
                <?= $tab === $code ? 'aria-current="page"' : '' ?>
            >
                <?= admin_h($tabTitle) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'roles'): ?>
        <section class="acl-workbench" data-acl-workbench>
            <aside class="acl-role-rail">
                <header>
                    <div>
                        <h3>انتخاب نقش</h3>
                        <p>فقط نقش انتخاب‌شده نمایش داده می‌شود.</p>
                    </div>
                </header>

                <label class="acl-mobile-picker">
                    <span>نقش</span>
                    <select data-acl-role-picker>
                        <?php foreach ($roles as $role): ?>
                            <option
                                value="<?= (int) $role['id'] ?>"
                                <?= (int) $role['id'] === $defaultRoleId
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= admin_h($role['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="acl-role-search">
                    <span class="sr-only">جستجو در نقش‌ها</span>
                    <input
                        type="search"
                        placeholder="جستجو در نقش‌ها..."
                        data-acl-role-search
                    >
                </label>

                <div class="acl-role-list" data-acl-role-list>
                    <?php foreach ($roles as $role): ?>
                        <?php
                        $roleId = (int) ($role['id'] ?? 0);
                        $protected =
                            ($role['code'] ?? '') === 'super_admin';
                        $selectedCount = count(
                            $roleMap[$roleId] ?? []
                        );
                        ?>
                        <button
                            type="button"
                            class="<?= $roleId === $defaultRoleId
                                ? 'is-active'
                                : '' ?>"
                            data-acl-role-button
                            data-role-id="<?= $roleId ?>"
                            data-search="<?= admin_h(
                                mb_strtolower(
                                    implode(' ', [
                                        $role['title'] ?? '',
                                        $role['code'] ?? '',
                                    ]),
                                    'UTF-8'
                                )
                            ) ?>"
                            aria-pressed="<?= $roleId === $defaultRoleId
                                ? 'true'
                                : 'false' ?>"
                        >
                            <span>
                                <strong><?= admin_h(
                                    $role['title']
                                ) ?></strong>
                                <small class="acl-tech" dir="ltr"><?= admin_h(
                                    $role['code']
                                ) ?></small>
                            </span>
                            <em>
                                <?= admin_h(
                                    $digits($selectedCount)
                                ) ?>
                                مجوز
                            </em>
                            <?php if ($protected): ?>
                                <b>ثابت</b>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </aside>

            <main class="acl-role-stage">
                <?php foreach ($roles as $role): ?>
                    <?php
                    $roleId = (int) ($role['id'] ?? 0);
                    $protected =
                        ($role['code'] ?? '') === 'super_admin';
                    $roleSelectedCount = count(
                        $roleMap[$roleId] ?? []
                    );
                    ?>
                    <form
                        method="post"
                        action="/admin/access-control/roles"
                        class="acl-role-panel"
                        data-acl-role-panel
                        data-role-id="<?= $roleId ?>"
                        <?= $roleId === $defaultRoleId
                            ? ''
                            : 'hidden' ?>
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= admin_h($csrf) ?>"
                        >
                        <input
                            type="hidden"
                            name="role_id"
                            value="<?= $roleId ?>"
                        >

                        <header class="acl-role-header">
                            <div class="acl-role-header__identity">
                                <span>نقش انتخاب‌شده</span>
                                <h3><?= admin_h($role['title']) ?></h3>
                                <p>
                                    <code class="acl-tech" dir="ltr"><?= admin_h(
                                        $role['code']
                                    ) ?></code>
                                    <?php if ($protected): ?>
                                        <b>دسترسی کامل ثابت</b>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="acl-role-header__summary">
                                <article>
                                    <span>فعال</span>
                                    <strong data-acl-role-selected>
                                        <?= admin_h(
                                            $digits($roleSelectedCount)
                                        ) ?>
                                    </strong>
                                </article>
                                <article>
                                    <span>کل</span>
                                    <strong>
                                        <?= admin_h(
                                            $digits($totalPermissions)
                                        ) ?>
                                    </strong>
                                </article>
                            </div>
                        </header>

                        <section class="acl-module-toolbar">
                            <div class="acl-module-toolbar__heading">
                                <div>
                                    <h4>ماژول</h4>
                                    <p>
                                        یک ماژول را برای مدیریت مجوزهایش
                                        انتخاب کنید.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="acl-quiet-button"
                                    data-acl-tech-toggle
                                    aria-pressed="false"
                                >
                                    جزئیات فنی
                                </button>
                            </div>

                            <label class="acl-module-picker">
                                <span>ماژول انتخاب‌شده</span>
                                <select data-acl-module-picker>
                                    <?php foreach ($groups as $module => $_): ?>
                                        <option
                                            value="<?= admin_h($module) ?>"
                                            <?= $module === $firstModule
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= admin_h(
                                                $moduleTitles[$module]
                                                    ?? $module
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <div
                                class="acl-module-tabs"
                                role="tablist"
                                aria-label="ماژول‌های سامانه"
                            >
                                <?php foreach (
                                    $groups as $module => $_
                                ): ?>
                                    <?php
                                    $moduleCodes =
                                        $modulePermissionCodes[$module]
                                            ?? [];
                                    $moduleSelected = count(
                                        array_intersect_key(
                                            array_fill_keys(
                                                $moduleCodes,
                                                true
                                            ),
                                            $roleMap[$roleId] ?? []
                                        )
                                    );
                                    ?>
                                    <button
                                        type="button"
                                        role="tab"
                                        class="<?= $module === $firstModule
                                            ? 'is-active'
                                            : '' ?>"
                                        data-acl-module-button
                                        data-module="<?= admin_h($module) ?>"
                                        aria-selected="<?= $module === $firstModule
                                            ? 'true'
                                            : 'false' ?>"
                                    >
                                        <span><?= admin_h(
                                            $moduleTitles[$module]
                                                ?? $module
                                        ) ?></span>
                                        <em>
                                            <b data-acl-module-selected>
                                                <?= admin_h(
                                                    $digits($moduleSelected)
                                                ) ?>
                                            </b>
                                            /
                                            <?= admin_h(
                                                $digits(count($moduleCodes))
                                            ) ?>
                                        </em>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <label class="acl-permission-search">
                                <span class="sr-only">
                                    جستجو در ماژول انتخاب‌شده
                                </span>
                                <input
                                    type="search"
                                    placeholder="جستجو در ماژول انتخاب‌شده..."
                                    data-acl-search
                                >
                            </label>
                        </section>

                        <?php foreach (
                            $groups as $module => $moduleGroups
                        ): ?>
                            <?php
                            $moduleCodes =
                                $modulePermissionCodes[$module] ?? [];
                            $moduleSelected = count(
                                array_intersect_key(
                                    array_fill_keys(
                                        $moduleCodes,
                                        true
                                    ),
                                    $roleMap[$roleId] ?? []
                                )
                            );
                            ?>
                            <section
                                class="acl-module-panel"
                                data-acl-module-panel
                                data-module="<?= admin_h($module) ?>"
                                <?= $module === $firstModule
                                    ? ''
                                    : 'hidden' ?>
                            >
                                <header class="acl-module-panel__header">
                                    <div>
                                        <span>ماژول</span>
                                        <h4><?= admin_h(
                                            $moduleTitles[$module]
                                                ?? $module
                                        ) ?></h4>
                                        <p>
                                            <b data-acl-panel-selected>
                                                <?= admin_h(
                                                    $digits($moduleSelected)
                                                ) ?>
                                            </b>
                                            مجوز از
                                            <?= admin_h(
                                                $digits(count($moduleCodes))
                                            ) ?>
                                            فعال است.
                                        </p>
                                    </div>

                                    <?php if (!$protected): ?>
                                        <div class="acl-module-actions">
                                            <button
                                                type="button"
                                                data-acl-select
                                            >
                                                انتخاب همه
                                            </button>
                                            <button
                                                type="button"
                                                data-acl-clear
                                            >
                                                پاک‌کردن
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </header>

                                <div class="acl-permission-groups">
                                    <?php $groupIndex = 0; ?>
                                    <?php foreach (
                                        $moduleGroups
                                        as $groupTitle => $items
                                    ): ?>
                                        <?php
                                        $groupIndex++;
                                        $groupCodes = array_values(
                                            array_filter(
                                                array_map(
                                                    static fn (
                                                        array $permission
                                                    ): string => (string) (
                                                        $permission['code']
                                                            ?? ''
                                                    ),
                                                    $items
                                                )
                                            )
                                        );
                                        $groupSelected = count(
                                            array_intersect_key(
                                                array_fill_keys(
                                                    $groupCodes,
                                                    true
                                                ),
                                                $roleMap[$roleId] ?? []
                                            )
                                        );
                                        ?>
                                        <details
                                            class="acl-permission-group"
                                            data-acl-group
                                            <?= $groupIndex === 1
                                                ? 'open'
                                                : '' ?>
                                        >
                                            <summary>
                                                <span>
                                                    <strong><?= admin_h(
                                                        $groupLabel($groupTitle)
                                                    ) ?></strong>
                                                    <small>
                                                        <b data-acl-group-selected>
                                                            <?= admin_h(
                                                                $digits(
                                                                    $groupSelected
                                                                )
                                                            ) ?>
                                                        </b>
                                                        از
                                                        <?= admin_h(
                                                            $digits(
                                                                count($groupCodes)
                                                            )
                                                        ) ?>
                                                    </small>
                                                </span>
                                            </summary>

                                            <div class="acl-permission-grid">
                                                <?php foreach (
                                                    $items as $permission
                                                ): ?>
                                                    <?php
                                                    $code = (string) (
                                                        $permission['code']
                                                            ?? ''
                                                    );
                                                    $checked = isset(
                                                        $roleMap[$roleId][$code]
                                                    );
                                                    ?>
                                                    <label
                                                        class="acl-permission-row"
                                                        data-acl-item
                                                        data-search="<?= admin_h(
                                                            mb_strtolower(
                                                                implode(' ', [
                                                                    $permission['title']
                                                                        ?? '',
                                                                    $code,
                                                                    $permission['description']
                                                                        ?? '',
                                                                    $groupTitle,
                                                                    $module,
                                                                ]),
                                                                'UTF-8'
                                                            )
                                                        ) ?>"
                                                    >
                                                        <input
                                                            class="acl-native"
                                                            type="checkbox"
                                                            name="permissions[]"
                                                            value="<?= admin_h(
                                                                $code
                                                            ) ?>"
                                                            <?= $checked
                                                                ? 'checked'
                                                                : '' ?>
                                                            <?= $protected
                                                                ? 'disabled'
                                                                : '' ?>
                                                        >
                                                        <span
                                                            class="acl-checkbox"
                                                            aria-hidden="true"
                                                        ></span>
                                                        <span class="acl-permission-copy">
                                                            <strong><?= admin_h(
                                                                $permission['title']
                                                                    ?? ''
                                                            ) ?></strong>
                                                            <?php if (
                                                                trim((string) (
                                                                    $permission[
                                                                        'description'
                                                                    ] ?? ''
                                                                )) !== ''
                                                            ): ?>
                                                                <small><?= admin_h(
                                                                    $permission[
                                                                        'description'
                                                                    ]
                                                                ) ?></small>
                                                            <?php endif; ?>
                                                            <code
                                                                class="acl-tech"
                                                                dir="ltr"
                                                            ><?= admin_h(
                                                                $code
                                                            ) ?></code>
                                                        </span>
                                                        <?php if (
                                                            !empty(
                                                                $permission[
                                                                    'is_sensitive'
                                                                ]
                                                            )
                                                        ): ?>
                                                            <em>حساس</em>
                                                        <?php endif; ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>

                        <?php if ($protected): ?>
                            <div class="acl-protected-note">
                                <span aria-hidden="true">✓</span>
                                <div>
                                    <strong>دسترسی کامل و ثابت</strong>
                                    <small>
                                        نقش مدیر کل محافظت‌شده است و
                                        مجوزهای آن قابل کاهش نیست.
                                    </small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="acl-savebar">
                                <div>
                                    <span
                                        class="acl-dirty"
                                        data-acl-dirty
                                        hidden
                                    >
                                        تغییر ذخیره‌نشده
                                    </span>
                                    <label>
                                        <span>دلیل تغییر مجوزها</span>
                                        <input
                                            name="reason"
                                            maxlength="500"
                                            placeholder="برای ثبت در تاریخچه"
                                        >
                                    </label>
                                </div>

                                <button
                                    class="admin-button"
                                    type="submit"
                                >
                                    ذخیره مجوزهای نقش
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endforeach; ?>
            </main>
        </section>

    <?php elseif ($tab === 'users'): ?>
        <section class="acl-panel acl-user-search-panel">
            <header class="acl-panel__header">
                <div>
                    <span>کاربران</span>
                    <h3>جستجوی شخص یا حساب کاربری</h3>
                    <p>
                        نام، نام کاربری، کد ملی، موبایل، نقش
                        یا سازمان را جستجو کنید.
                    </p>
                </div>
            </header>

            <form
                method="get"
                action="/admin/access-control"
                class="acl-user-search"
            >
                <input type="hidden" name="tab" value="users">
                <input
                    type="search"
                    name="q"
                    value="<?= admin_h($page['query'] ?? '') ?>"
                    placeholder="نام، کد ملی، موبایل، نقش یا سازمان"
                >
                <button class="admin-button" type="submit">
                    جستجو
                </button>
            </form>

            <div class="acl-users">
                <?php foreach ($users as $user): ?>
                    <a
                        href="/admin/access-control?tab=users&user_id=<?= (int) $user['id'] ?>&q=<?= rawurlencode(
                            (string) ($page['query'] ?? '')
                        ) ?>"
                        class="<?= (int) $user['id']
                            === (int) ($page['selected_user_id'] ?? 0)
                                ? 'is-active'
                                : '' ?>"
                    >
                        <strong><?= admin_h($user['title']) ?></strong>
                        <span><?= admin_h(
                            implode(' • ', array_filter([
                                $user['organization_title'] ?? '',
                                $user['role_titles'] ?? '',
                            ]))
                        ) ?></span>
                        <small dir="ltr"><?= admin_h(
                            implode(' | ', array_filter([
                                $user['username'] ?? '',
                                $user['mobile'] ?? '',
                                $user['national_code'] ?? '',
                            ]))
                        ) ?></small>
                    </a>
                <?php endforeach; ?>

                <?php if ($users === []): ?>
                    <div class="acl-empty">
                        نتیجه‌ای برای نمایش وجود ندارد.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($selectedUser !== null): ?>
            <section class="acl-panel">
                <header class="acl-user-header">
                    <div>
                        <span>کاربر انتخاب‌شده</span>
                        <h3><?= admin_h($selectedUser['title']) ?></h3>
                        <p><?= admin_h(
                            implode(' • ', array_filter([
                                $selectedUser['organization_title'] ?? '',
                                $selectedUser['role_titles'] ?? '',
                            ]))
                        ) ?></p>
                    </div>

                    <form
                        method="get"
                        action="/admin/access-control"
                        class="acl-assignment"
                    >
                        <input type="hidden" name="tab" value="users">
                        <input
                            type="hidden"
                            name="user_id"
                            value="<?= (int) $selectedUser['id'] ?>"
                        >
                        <label>
                            <span>محدوده اعمال استثنا</span>
                            <select
                                name="assignment_id"
                                onchange="this.form.submit()"
                            >
                                <option value="0">
                                    همه نقش‌های فعال کاربر
                                </option>
                                <?php foreach (
                                    $assignments as $assignment
                                ): ?>
                                    <option
                                        value="<?= (int) $assignment['id'] ?>"
                                        <?= (int) $assignment['id']
                                            === $assignmentId
                                                ? 'selected'
                                                : '' ?>
                                    >
                                        <?= admin_h(
                                            $assignment['role_title']
                                            . ' — '
                                            . $scopeLabel($assignment['scope_type'])
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </form>
                </header>

                <form
                    method="post"
                    action="/admin/access-control/users"
                    class="acl-user-policy"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h($csrf) ?>"
                    >
                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= (int) $selectedUser['id'] ?>"
                    >
                    <input
                        type="hidden"
                        name="role_assignment_id"
                        value="<?= $assignmentId ?>"
                    >

                    <section class="acl-policy">
                        <header>
                            <div>
                                <span>اعلان‌ها</span>
                                <h4>سیاست ارسال اعلان</h4>
                                <p>
                                    وضعیت مشاهده فرم و نحوه ارسال
                                    این کاربر را مشخص کنید.
                                </p>
                            </div>
                        </header>

                        <div class="acl-policy-grid">
                            <?php foreach ([
                                'none' => [
                                    'عدم دسترسی',
                                    'فرم ارسال برای کاربر نمایش داده نمی‌شود.',
                                ],
                                'approval' => [
                                    'ارسال با تأیید',
                                    'ارسال پس از تأیید مدیر انجام می‌شود.',
                                ],
                                'direct' => [
                                    'ارسال مستقیم',
                                    'اعلان بدون تأیید ارسال می‌شود.',
                                ],
                                'inherit' => [
                                    'ارث‌بری از نقش',
                                    'سیاست نقش فعال حفظ می‌شود.',
                                ],
                            ] as $code => $definition): ?>
                                <label class="acl-choice">
                                    <input
                                        class="acl-native"
                                        type="radio"
                                        name="notification_policy"
                                        value="<?= admin_h($code) ?>"
                                        <?= $code === (
                                            $page['notification_policy']
                                                ?? 'none'
                                        ) ? 'checked' : '' ?>
                                    >
                                    <span
                                        class="acl-radio"
                                        aria-hidden="true"
                                    ></span>
                                    <span class="acl-choice__copy">
                                        <strong><?= admin_h(
                                            $definition[0]
                                        ) ?></strong>
                                        <small><?= admin_h(
                                            $definition[1]
                                        ) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="acl-capability-panel">
                        <header>
                            <div>
                                <span>قابلیت‌های گیرنده</span>
                                <h4>اختیارات تکمیلی فرم ارسال</h4>
                                <p>
                                    هر اختیار مستقل از سیاست ارسال
                                    قابل کنترل است.
                                </p>
                            </div>
                        </header>

                        <div class="acl-capabilities">
                            <?php
                            $capabilities = [
                                'can_search_recipients' => [
                                    'notifications.recipients.search',
                                    'جستجوی اشخاص و گیرندگان',
                                    'انتخاب کاربران سامانه به‌عنوان گیرنده.',
                                ],
                                'can_view_recipient_details' => [
                                    'notifications.recipients.details',
                                    'مشاهده مشخصات گیرندگان',
                                    'نمایش نقش، سازمان و شهر گیرنده.',
                                ],
                                'can_use_manual_targets' => [
                                    'notifications.manual_targets.use',
                                    'ورود مقصد دستی',
                                    'ثبت مستقیم ایمیل، موبایل یا شناسه مقصد.',
                                ],
                            ];
                            ?>

                            <?php foreach (
                                $capabilities as $name => $definition
                            ): ?>
                                <?php
                                $permissionCode = $definition[0];
                                $effective = isset(
                                    $overrides[$permissionCode]
                                )
                                    ? $overrides[$permissionCode]
                                        === 'allow'
                                    : isset(
                                        $inherited[$permissionCode]
                                    );
                                ?>
                                <label class="acl-capability">
                                    <input
                                        class="acl-native"
                                        type="checkbox"
                                        name="<?= admin_h($name) ?>"
                                        value="1"
                                        <?= $effective
                                            ? 'checked'
                                            : '' ?>
                                    >
                                    <span
                                        class="acl-checkbox"
                                        aria-hidden="true"
                                    ></span>
                                    <span>
                                        <strong><?= admin_h(
                                            $definition[1]
                                        ) ?></strong>
                                        <small><?= admin_h(
                                            $definition[2]
                                        ) ?></small>
                                        <code
                                            class="acl-tech"
                                            dir="ltr"
                                        ><?= admin_h(
                                            $permissionCode
                                        ) ?></code>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <div class="acl-savebar">
                        <div>
                            <label>
                                <span>دلیل تغییر دسترسی</span>
                                <input
                                    name="reason"
                                    maxlength="500"
                                    required
                                    placeholder="مثلاً: طبق ابلاغ مدیر واحد"
                                >
                            </label>
                        </div>
                        <button class="admin-button" type="submit">
                            ذخیره سیاست دسترسی کاربر
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

    <?php elseif ($tab === 'audit'): ?>
        <section class="acl-panel">
            <header class="acl-panel__header">
                <div>
                    <span>ممیزی</span>
                    <h3>تاریخچه تغییرات دسترسی</h3>
                    <p>آخرین تغییرات نقش‌ها و سیاست کاربران.</p>
                </div>
            </header>

            <div class="acl-audit-wrap">
                <table class="admin-table acl-audit-table">
                    <thead>
                        <tr>
                            <th>زمان</th>
                            <th>مدیر</th>
                            <th>هدف</th>
                            <th>نوع تغییر</th>
                            <th>دلیل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit as $row): ?>
                            <tr>
                                <td
                                    dir="ltr"
                                    data-label="زمان"
                                ><?= admin_h(
                                    $row['created_at']
                                ) ?></td>
                                <td data-label="مدیر"><?= admin_h(
                                    $row['actor_title']
                                ) ?></td>
                                <td data-label="هدف">
                                    <?= admin_h(
                                        $auditTargetLabel($row['target_type'])
                                    ) ?>
                                    #<?= admin_h(
                                        $digits($row['target_id'])
                                    ) ?>
                                </td>
                                <td data-label="نوع تغییر">
                                    <code><?= admin_h(
                                        $auditChangeLabel($row['change_type'])
                                    ) ?></code>
                                </td>
                                <td data-label="دلیل"><?= admin_h(
                                    $row['reason'] ?? '—'
                                ) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($audit === []): ?>
                            <tr>
                                <td colspan="5">
                                    سابقه‌ای برای نمایش وجود ندارد.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</section>

<style>
.acl-shell,
.acl-shell * {
    box-sizing: border-box;
}

.acl-shell {
    display: grid;
    gap: .75rem;
    min-width: 0;
}

.acl-shell [hidden] {
    display: none !important;
}

.acl-hero,
.acl-panel,
.acl-role-rail,
.acl-role-panel {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 14px;
}

.acl-hero {
    align-items: center;
    background: linear-gradient(
        135deg,
        var(--admin-surface),
        var(--admin-primary-soft)
    );
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    padding: .85rem 1rem;
}

.acl-hero__copy {
    display: grid;
    gap: .16rem;
    min-width: 0;
}

.acl-hero__copy > span,
.acl-panel__header > div > span,
.acl-user-header > div > span,
.acl-policy header span,
.acl-capability-panel header span,
.acl-module-panel__header > div > span,
.acl-role-header__identity > span {
    color: var(--admin-primary);
    font-size: .68rem;
    font-weight: 800;
}

.acl-hero h2,
.acl-hero p,
.acl-panel h3,
.acl-panel p,
.acl-role-header h3,
.acl-role-header p,
.acl-module-toolbar h4,
.acl-module-toolbar p,
.acl-module-panel h4,
.acl-module-panel p,
.acl-policy h4,
.acl-policy p,
.acl-capability-panel h4,
.acl-capability-panel p,
.acl-user-header h3,
.acl-user-header p {
    margin: 0;
}

.acl-hero h2 {
    font-size: 1.05rem;
}

.acl-hero p,
.acl-panel p,
.acl-role-header p,
.acl-module-toolbar p,
.acl-module-panel p,
.acl-policy p,
.acl-capability-panel p,
.acl-user-header p {
    color: var(--admin-text-muted);
    font-size: .72rem;
    line-height: 1.7;
}

.acl-hero__metrics {
    display: grid;
    flex: 0 0 auto;
    gap: .4rem;
    grid-template-columns: repeat(2, minmax(88px, 1fr));
}

.acl-hero__metrics article,
.acl-role-header__summary article {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    display: grid;
    gap: .02rem;
    padding: .42rem .52rem;
}

.acl-hero__metrics span,
.acl-role-header__summary span {
    color: var(--admin-text-muted);
    font-size: .62rem;
}

.acl-hero__metrics strong,
.acl-role-header__summary strong {
    font-size: .9rem;
}

.acl-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: .38rem;
}

.acl-tabs a {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    color: var(--admin-text);
    font-size: .73rem;
    padding: .42rem .7rem;
    text-decoration: none;
}

.acl-tabs a.is-active {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
    color: var(--admin-primary);
    font-weight: 800;
}

.acl-workbench {
    align-items: start;
    display: grid;
    gap: .75rem;
    grid-template-columns: minmax(210px, 245px) minmax(0, 1fr);
    min-width: 0;
}

.acl-role-rail {
    display: grid;
    gap: .55rem;
    padding: .65rem;
    position: sticky;
    top: .65rem;
}

.acl-role-rail header h3 {
    font-size: .82rem;
    margin: 0;
}

.acl-role-rail header p {
    color: var(--admin-text-muted);
    font-size: .66rem;
    margin: .12rem 0 0;
}

.acl-role-search {
    display: block;
}

.acl-role-search input {
    width: 100%;
}

.acl-role-list {
    display: grid;
    gap: .3rem;
    max-height: 58vh;
    overflow-y: auto;
    padding-inline-end: .1rem;
}

.acl-role-list button {
    align-items: center;
    background: transparent;
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    color: var(--admin-text);
    cursor: pointer;
    display: grid;
    font: inherit;
    gap: .12rem .35rem;
    grid-template-columns: minmax(0, 1fr) auto;
    padding: .46rem .5rem;
    text-align: start;
}

.acl-role-list button:hover,
.acl-role-list button.is-active {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
}

.acl-role-list button > span {
    display: grid;
    gap: .02rem;
    min-width: 0;
}

.acl-role-list strong {
    font-size: .71rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.acl-role-list small {
    color: var(--admin-text-muted);
    font-size: .56rem;
    overflow: hidden;
    text-overflow: ellipsis;
}

.acl-role-list em {
    color: var(--admin-text-muted);
    font-size: .59rem;
    font-style: normal;
    white-space: nowrap;
}

.acl-role-list b {
    color: #8a5b15;
    font-size: .55rem;
    font-weight: 700;
    grid-column: 1 / -1;
}

.acl-mobile-picker,
.acl-module-picker {
    display: none;
}

.acl-role-stage {
    min-width: 0;
}

.acl-role-panel {
    display: grid;
    gap: .65rem;
    min-width: 0;
    padding: .7rem;
}

.acl-role-header,
.acl-user-header,
.acl-module-panel__header,
.acl-panel__header,
.acl-policy > header,
.acl-capability-panel > header {
    align-items: center;
    display: flex;
    gap: .7rem;
    justify-content: space-between;
}

.acl-role-header__identity {
    display: grid;
    gap: .08rem;
    min-width: 0;
}

.acl-role-header__identity h3 {
    font-size: .95rem;
}

.acl-role-header__identity p {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}

.acl-role-header__identity code {
    font-size: .58rem;
}

.acl-role-header__identity b {
    background: #fff3d6;
    border-radius: 999px;
    color: #805510;
    font-size: .58rem;
    padding: .18rem .4rem;
}

.acl-role-header__summary {
    display: grid;
    flex: 0 0 auto;
    gap: .35rem;
    grid-template-columns: repeat(2, minmax(72px, 1fr));
}

.acl-module-toolbar {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 11px;
    display: grid;
    gap: .5rem;
    padding: .55rem;
}

.acl-module-toolbar__heading {
    align-items: center;
    display: flex;
    gap: .5rem;
    justify-content: space-between;
}

.acl-module-toolbar__heading > div {
    display: grid;
    gap: .05rem;
}

.acl-module-toolbar h4 {
    font-size: .78rem;
}

.acl-module-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: .3rem;
}

.acl-module-tabs button {
    align-items: center;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    color: var(--admin-text);
    cursor: pointer;
    display: flex;
    font: inherit;
    gap: .35rem;
    justify-content: space-between;
    min-width: 115px;
    padding: .38rem .45rem;
}

.acl-module-tabs button:hover,
.acl-module-tabs button.is-active {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
    color: var(--admin-primary);
}

.acl-module-tabs span {
    font-size: .65rem;
    font-weight: 700;
}

.acl-module-tabs em {
    color: var(--admin-text-muted);
    font-size: .56rem;
    font-style: normal;
}

.acl-quiet-button,
.acl-module-actions button {
    background: transparent;
    border: 1px solid var(--admin-border);
    border-radius: 7px;
    color: var(--admin-primary);
    cursor: pointer;
    font: inherit;
    font-size: .62rem;
    padding: .32rem .45rem;
}

.acl-quiet-button[aria-pressed="true"] {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
}

.acl-permission-search input {
    width: min(100%, 420px);
}

.acl-module-panel {
    border: 1px solid var(--admin-border);
    border-radius: 11px;
    display: grid;
    gap: .5rem;
    min-width: 0;
    padding: .55rem;
}

.acl-module-panel__header {
    background: var(--admin-surface-muted);
    border-radius: 8px;
    padding: .48rem .55rem;
}

.acl-module-panel__header > div {
    display: grid;
    gap: .04rem;
}

.acl-module-panel h4 {
    font-size: .8rem;
}

.acl-module-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .28rem;
}

.acl-permission-groups {
    display: grid;
    gap: .4rem;
}

.acl-permission-group {
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    overflow: hidden;
}

.acl-permission-group > summary {
    align-items: center;
    background: var(--admin-surface-muted);
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    list-style: none;
    min-height: 40px;
    padding: .42rem .52rem;
}

.acl-permission-group > summary::-webkit-details-marker {
    display: none;
}

.acl-permission-group > summary::after {
    border: solid currentColor;
    border-width: 0 1px 1px 0;
    content: "";
    height: 6px;
    margin-inline-start: .4rem;
    transform: rotate(45deg);
    transition: transform .16s ease;
    width: 6px;
}

.acl-permission-group[open] > summary::after {
    transform: rotate(225deg);
}

.acl-permission-group > summary span {
    align-items: center;
    display: flex;
    gap: .45rem;
    justify-content: space-between;
    min-width: 0;
    width: 100%;
}

.acl-permission-group > summary strong {
    font-size: .7rem;
}

.acl-permission-group > summary small {
    color: var(--admin-text-muted);
    font-size: .59rem;
    white-space: nowrap;
}

.acl-permission-grid {
    display: grid;
    gap: 0;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: .25rem .45rem .4rem;
}

.acl-permission-row,
.acl-capability,
.acl-choice {
    cursor: pointer;
    min-width: 0;
    position: relative;
}

.acl-permission-row {
    align-items: flex-start;
    border-bottom: 1px solid var(--admin-border);
    display: grid;
    gap: .4rem;
    grid-template-columns: 15px minmax(0, 1fr) auto;
    min-height: 46px;
    padding: .5rem .35rem;
}

.acl-permission-row:nth-last-child(-n + 2) {
    border-bottom-color: transparent;
}

.acl-permission-row:hover {
    background: var(--admin-surface-muted);
}

.acl-native {
    block-size: 1px;
    inline-size: 1px;
    opacity: 0;
    pointer-events: none;
    position: absolute;
}

.acl-checkbox,
.acl-radio {
    align-items: center;
    background: var(--admin-surface);
    border: 1px solid #aeb8b1;
    display: inline-flex;
    flex: 0 0 15px;
    height: 15px;
    justify-content: center;
    margin-top: 2px;
    transition:
        background-color .14s ease,
        border-color .14s ease,
        box-shadow .14s ease;
    width: 15px;
}

.acl-checkbox {
    border-radius: 4px;
}

.acl-radio {
    border-radius: 50%;
}

.acl-native:focus-visible + .acl-checkbox,
.acl-native:focus-visible + .acl-radio {
    box-shadow: 0 0 0 3px var(--admin-primary-soft);
    outline: 1px solid var(--admin-primary);
}

.acl-native:checked + .acl-checkbox,
.acl-native:checked + .acl-radio {
    background: var(--admin-primary);
    border-color: var(--admin-primary);
}

.acl-native:checked + .acl-checkbox::after {
    border: solid #fff;
    border-width: 0 1.6px 1.6px 0;
    content: "";
    height: 7px;
    transform: rotate(45deg) translate(-1px, -1px);
    width: 3px;
}

.acl-native:checked + .acl-radio::after {
    background: #fff;
    border-radius: 50%;
    content: "";
    height: 5px;
    width: 5px;
}

.acl-native:disabled + .acl-checkbox,
.acl-native:disabled + .acl-radio {
    background: #eef0ee;
    border-color: #c8ceca;
    opacity: .8;
}

.acl-native:disabled ~ * {
    cursor: not-allowed;
}

.acl-permission-copy,
.acl-capability > span:last-child,
.acl-choice__copy {
    display: grid;
    gap: .06rem;
    min-width: 0;
}

.acl-permission-copy strong,
.acl-capability strong,
.acl-choice strong {
    font-size: .68rem;
    line-height: 1.55;
}

.acl-permission-copy small,
.acl-capability small,
.acl-choice small {
    color: var(--admin-text-muted);
    font-size: .59rem;
    line-height: 1.55;
}

.acl-permission-row > em {
    background: #fff0ed;
    border-radius: 999px;
    color: #97452f;
    font-size: .54rem;
    font-style: normal;
    padding: .15rem .35rem;
    white-space: nowrap;
}

.acl-tech {
    color: var(--admin-text-muted);
    display: none;
    font-size: .54rem;
    overflow-wrap: anywhere;
}

.acl-shell.is-tech-visible .acl-tech {
    display: block;
}

.acl-dirty {
    color: #9a5f13;
    font-size: .62rem;
    font-weight: 800;
}

.acl-protected-note {
    align-items: center;
    background: var(--admin-primary-soft);
    border: 1px solid color-mix(
        in srgb,
        var(--admin-primary) 35%,
        var(--admin-border)
    );
    border-radius: 10px;
    display: grid;
    gap: .5rem;
    grid-template-columns: 24px minmax(0, 1fr);
    padding: .6rem;
}

.acl-protected-note > span {
    align-items: center;
    background: var(--admin-primary);
    border-radius: 50%;
    color: #fff;
    display: inline-flex;
    font-size: .68rem;
    height: 22px;
    justify-content: center;
    width: 22px;
}

.acl-protected-note > div {
    display: grid;
    gap: .04rem;
}

.acl-protected-note strong {
    font-size: .7rem;
}

.acl-protected-note small {
    color: var(--admin-text-muted);
    font-size: .61rem;
    line-height: 1.6;
}

.acl-savebar {
    align-items: end;
    background: color-mix(
        in srgb,
        var(--admin-surface) 92%,
        transparent
    );
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    bottom: .5rem;
    box-shadow: 0 8px 22px rgba(0, 0, 0, .07);
    display: grid;
    gap: .55rem;
    grid-template-columns: minmax(0, 1fr) auto;
    padding: .55rem;
    position: sticky;
    z-index: 8;
}

.acl-savebar > div,
.acl-savebar label,
.acl-assignment label,
.acl-mobile-picker,
.acl-module-picker {
    display: grid;
    gap: .22rem;
}

.acl-savebar label > span,
.acl-assignment label > span,
.acl-mobile-picker > span,
.acl-module-picker > span {
    font-size: .65rem;
    font-weight: 800;
}

.acl-panel {
    display: grid;
    gap: .65rem;
    min-width: 0;
    padding: .75rem;
}

.acl-panel__header > div,
.acl-user-header > div,
.acl-policy > header > div,
.acl-capability-panel > header > div {
    display: grid;
    gap: .05rem;
    min-width: 0;
}

.acl-panel h3,
.acl-user-header h3 {
    font-size: .9rem;
}

.acl-user-search {
    display: grid;
    gap: .4rem;
    grid-template-columns: minmax(0, 1fr) auto;
}

.acl-users {
    display: grid;
    gap: .35rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.acl-users a {
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    color: var(--admin-text);
    display: grid;
    gap: .08rem;
    min-width: 0;
    padding: .5rem;
    text-decoration: none;
}

.acl-users a:hover,
.acl-users a.is-active {
    background: var(--admin-primary-soft);
    border-color: var(--admin-primary);
}

.acl-users strong {
    font-size: .7rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.acl-users span,
.acl-users small {
    color: var(--admin-text-muted);
    font-size: .6rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.acl-empty {
    color: var(--admin-text-muted);
    font-size: .72rem;
    grid-column: 1 / -1;
    padding: 1rem;
    text-align: center;
}

.acl-assignment {
    flex: 0 1 460px;
    min-width: 260px;
}

.acl-user-policy {
    display: grid;
    gap: .55rem;
}

.acl-policy,
.acl-capability-panel {
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 11px;
    display: grid;
    gap: .5rem;
    padding: .58rem;
}

.acl-policy h4,
.acl-capability-panel h4 {
    font-size: .78rem;
}

.acl-policy-grid {
    display: grid;
    gap: .35rem;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.acl-choice {
    align-items: flex-start;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    display: grid;
    gap: .4rem;
    grid-template-columns: 15px minmax(0, 1fr);
    min-height: 66px;
    padding: .48rem;
}

.acl-choice:hover,
.acl-choice:has(.acl-native:checked) {
    border-color: var(--admin-primary);
}

.acl-capabilities {
    display: grid;
    gap: .35rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.acl-capability {
    align-items: flex-start;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    display: grid;
    gap: .4rem;
    grid-template-columns: 15px minmax(0, 1fr);
    min-height: 64px;
    padding: .48rem;
}

.acl-capability:hover,
.acl-capability:has(.acl-native:checked) {
    border-color: var(--admin-primary);
}

.acl-audit-wrap {
    min-width: 0;
    overflow-x: auto;
}

.acl-audit-table code {
    font-size: .58rem;
}

.sr-only {
    clip: rect(0, 0, 0, 0);
    clip-path: inset(50%);
    height: 1px;
    overflow: hidden;
    position: absolute;
    white-space: nowrap;
    width: 1px;
}

@media (max-width: 1180px) {
    .acl-workbench {
        grid-template-columns: 1fr;
    }

    .acl-role-rail {
        position: static;
    }

    .acl-role-list {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        max-height: none;
    }

    .acl-users {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .acl-policy-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 980px) {
    .acl-module-tabs {
        display: none;
    }

    .acl-module-picker {
        display: grid;
    }
}

@media (max-width: 760px) {
    .acl-hero,
    .acl-role-header,
    .acl-user-header,
    .acl-panel__header,
    .acl-module-panel__header,
    .acl-policy > header,
    .acl-capability-panel > header {
        align-items: stretch;
        flex-direction: column;
    }

    .acl-hero__metrics,
    .acl-role-header__summary {
        width: 100%;
    }

    .acl-role-list,
    .acl-role-search,
    .acl-module-tabs {
        display: none;
    }

    .acl-mobile-picker,
    .acl-module-picker {
        display: grid;
    }

    .acl-role-rail {
        padding: .55rem;
    }

    .acl-module-toolbar__heading {
        align-items: stretch;
        flex-direction: column;
    }

    .acl-permission-search input {
        width: 100%;
    }

    .acl-permission-grid {
        grid-template-columns: 1fr;
    }

    .acl-permission-row:nth-last-child(-n + 2) {
        border-bottom-color: var(--admin-border);
    }

    .acl-permission-row:last-child {
        border-bottom-color: transparent;
    }

    .acl-users,
    .acl-capabilities {
        grid-template-columns: 1fr;
    }

    .acl-assignment {
        flex-basis: auto;
        min-width: 0;
        width: 100%;
    }

    .acl-savebar {
        grid-template-columns: 1fr;
        position: static;
    }

    .acl-savebar .admin-button {
        width: 100%;
    }

    .acl-audit-wrap {
        overflow: visible;
    }

    .acl-audit-table,
    .acl-audit-table tbody,
    .acl-audit-table tr,
    .acl-audit-table td {
        display: block;
        width: 100%;
    }

    .acl-audit-table thead {
        display: none;
    }

    .acl-audit-table tbody {
        display: grid;
        gap: .45rem;
    }

    .acl-audit-table tr {
        background: var(--admin-surface);
        border: 1px solid var(--admin-border);
        border-radius: 9px;
        padding: .4rem;
    }

    .acl-audit-table td {
        align-items: start;
        border: 0;
        display: grid;
        font-size: .68rem;
        gap: .4rem;
        grid-template-columns: 78px minmax(0, 1fr);
        padding: .3rem;
        text-align: start;
    }

    .acl-audit-table td::before {
        color: var(--admin-text-muted);
        content: attr(data-label);
        font-size: .62rem;
        font-weight: 800;
    }
}

@media (max-width: 520px) {
    .acl-hero,
    .acl-panel,
    .acl-role-panel {
        border-radius: 11px;
        padding: .58rem;
    }

    .acl-hero__metrics,
    .acl-role-header__summary,
    .acl-policy-grid {
        grid-template-columns: 1fr;
    }

    .acl-tabs a {
        flex: 1 1 calc(50% - .4rem);
        text-align: center;
    }

    .acl-user-search {
        grid-template-columns: 1fr;
    }

    .acl-user-search .admin-button {
        width: 100%;
    }

    .acl-module-actions {
        width: 100%;
    }

    .acl-module-actions button {
        flex: 1 1 0;
    }
}
</style>

<script>
(() => {
    const shell = document.querySelector('[data-acl-shell]');
    const workbench = document.querySelector('[data-acl-workbench]');

    if (!shell) {
        return;
    }

    const toFa = new Intl.NumberFormat('fa-IR');

    const updatePanelCounts = (panel) => {
        if (!panel) {
            return;
        }

        const allChecked = panel.querySelectorAll(
            'input[name="permissions[]"]:checked'
        ).length;
        const roleCount = panel.querySelector(
            '[data-acl-role-selected]'
        );

        if (roleCount) {
            roleCount.textContent = toFa.format(allChecked);
        }

        panel.querySelectorAll('[data-acl-module-panel]')
            .forEach((modulePanel) => {
                const module = modulePanel.dataset.module;
                const checked = modulePanel.querySelectorAll(
                    'input[name="permissions[]"]:checked'
                ).length;
                const panelCount = modulePanel.querySelector(
                    '[data-acl-panel-selected]'
                );
                const tabCount = panel.querySelector(
                    `[data-acl-module-button][data-module="${CSS.escape(module)}"] `
                    + '[data-acl-module-selected]'
                );

                if (panelCount) {
                    panelCount.textContent = toFa.format(checked);
                }

                if (tabCount) {
                    tabCount.textContent = toFa.format(checked);
                }

                modulePanel.querySelectorAll('[data-acl-group]')
                    .forEach((group) => {
                        const groupCount = group.querySelector(
                            '[data-acl-group-selected]'
                        );

                        if (groupCount) {
                            groupCount.textContent = toFa.format(
                                group.querySelectorAll(
                                    'input[name="permissions[]"]:checked'
                                ).length
                            );
                        }
                    });
            });
    };

    const showModule = (panel, module) => {
        if (!panel || !module) {
            return;
        }

        panel.querySelectorAll('[data-acl-module-panel]')
            .forEach((item) => {
                item.hidden = item.dataset.module !== module;
            });

        panel.querySelectorAll('[data-acl-module-button]')
            .forEach((button) => {
                const active = button.dataset.module === module;
                button.classList.toggle('is-active', active);
                button.setAttribute(
                    'aria-selected',
                    active ? 'true' : 'false'
                );
            });

        const picker = panel.querySelector(
            '[data-acl-module-picker]'
        );

        if (picker && picker.value !== module) {
            picker.value = module;
        }

        const search = panel.querySelector('[data-acl-search]');

        if (search) {
            search.value = '';
        }

        panel.querySelectorAll('[data-acl-item]')
            .forEach((item) => {
                item.hidden = false;
            });

        panel.querySelectorAll('[data-acl-group]')
            .forEach((group, index) => {
                group.hidden = false;

                if (index === 0) {
                    group.open = true;
                }
            });

        try {
            sessionStorage.setItem(
                `acl-module-${panel.dataset.roleId}`,
                module
            );
        } catch (_) {
        }
    };

    const activateRole = (roleId) => {
        if (!workbench || !roleId) {
            return;
        }

        workbench.querySelectorAll('[data-acl-role-panel]')
            .forEach((panel) => {
                panel.hidden = panel.dataset.roleId !== roleId;
            });

        workbench.querySelectorAll('[data-acl-role-button]')
            .forEach((button) => {
                const active = button.dataset.roleId === roleId;
                button.classList.toggle('is-active', active);
                button.setAttribute(
                    'aria-pressed',
                    active ? 'true' : 'false'
                );
            });

        const picker = workbench.querySelector(
            '[data-acl-role-picker]'
        );

        if (picker && picker.value !== roleId) {
            picker.value = roleId;
        }

        const panel = workbench.querySelector(
            `[data-acl-role-panel][data-role-id="${CSS.escape(roleId)}"]`
        );

        if (panel) {
            let module = '';

            try {
                module = sessionStorage.getItem(
                    `acl-module-${roleId}`
                ) || '';
            } catch (_) {
            }

            if (
                module === ''
                || !panel.querySelector(
                    `[data-acl-module-panel][data-module="${CSS.escape(module)}"]`
                )
            ) {
                module = panel.querySelector(
                    '[data-acl-module-panel]'
                )?.dataset.module || '';
            }

            showModule(panel, module);
            updatePanelCounts(panel);
        }

        try {
            sessionStorage.setItem('acl-role', roleId);
        } catch (_) {
        }
    };

    if (workbench) {
        workbench.querySelector('[data-acl-role-search]')
            ?.addEventListener('input', (event) => {
                const needle = (event.currentTarget.value || '')
                    .trim()
                    .toLocaleLowerCase('fa');

                workbench.querySelectorAll('[data-acl-role-button]')
                    .forEach((button) => {
                        button.hidden = !(
                            needle === ''
                            || (button.dataset.search || '')
                                .includes(needle)
                        );
                    });
            });

        workbench.querySelectorAll('[data-acl-role-button]')
            .forEach((button) => {
                button.addEventListener('click', () => {
                    activateRole(button.dataset.roleId || '');
                });
            });

        workbench.querySelector('[data-acl-role-picker]')
            ?.addEventListener('change', (event) => {
                activateRole(event.currentTarget.value);
            });

        workbench.querySelectorAll('[data-acl-role-panel]')
            .forEach((panel) => {
                panel.querySelectorAll('[data-acl-module-button]')
                    .forEach((button) => {
                        button.addEventListener('click', () => {
                            showModule(
                                panel,
                                button.dataset.module || ''
                            );
                        });
                    });

                panel.querySelector('[data-acl-module-picker]')
                    ?.addEventListener('change', (event) => {
                        showModule(panel, event.currentTarget.value);
                    });

                panel.querySelectorAll('[data-acl-tech-toggle]')
                    .forEach((button) => {
                        button.addEventListener('click', () => {
                            const visible = !shell.classList.contains(
                                'is-tech-visible'
                            );
                            shell.classList.toggle(
                                'is-tech-visible',
                                visible
                            );
                            shell.querySelectorAll(
                                '[data-acl-tech-toggle]'
                            ).forEach((item) => {
                                item.setAttribute(
                                    'aria-pressed',
                                    visible ? 'true' : 'false'
                                );
                            });
                        });
                    });

                panel.querySelector('[data-acl-search]')
                    ?.addEventListener('input', (event) => {
                        const modulePanel = panel.querySelector(
                            '[data-acl-module-panel]:not([hidden])'
                        );

                        if (!modulePanel) {
                            return;
                        }

                        const needle = (
                            event.currentTarget.value || ''
                        ).trim().toLocaleLowerCase('fa');

                        modulePanel.querySelectorAll('[data-acl-group]')
                            .forEach((group) => {
                                let visible = 0;

                                group.querySelectorAll('[data-acl-item]')
                                    .forEach((item) => {
                                        item.hidden = !(
                                            needle === ''
                                            || (
                                                item.dataset.search || ''
                                            ).includes(needle)
                                        );

                                        if (!item.hidden) {
                                            visible++;
                                        }
                                    });

                                group.hidden =
                                    needle !== '' && visible === 0;

                                if (needle !== '' && visible > 0) {
                                    group.open = true;
                                }
                            });
                    });

                panel.querySelectorAll('[data-acl-module-panel]')
                    .forEach((modulePanel) => {
                        modulePanel.querySelector('[data-acl-select]')
                            ?.addEventListener('click', () => {
                                modulePanel.querySelectorAll(
                                    'input[name="permissions[]"]:not(:disabled)'
                                ).forEach((checkbox) => {
                                    checkbox.checked = true;
                                });

                                panel.querySelector('[data-acl-dirty]')
                                    ?.removeAttribute('hidden');
                                updatePanelCounts(panel);
                            });

                        modulePanel.querySelector('[data-acl-clear]')
                            ?.addEventListener('click', () => {
                                modulePanel.querySelectorAll(
                                    'input[name="permissions[]"]:not(:disabled)'
                                ).forEach((checkbox) => {
                                    checkbox.checked = false;
                                });

                                panel.querySelector('[data-acl-dirty]')
                                    ?.removeAttribute('hidden');
                                updatePanelCounts(panel);
                            });
                    });

                panel.querySelectorAll(
                    'input[name="permissions[]"]'
                ).forEach((checkbox) => {
                    checkbox.addEventListener('change', () => {
                        panel.querySelector('[data-acl-dirty]')
                            ?.removeAttribute('hidden');
                        updatePanelCounts(panel);
                    });
                });

                updatePanelCounts(panel);
            });

        let initialRole = String(
            workbench.querySelector(
                '[data-acl-role-panel]:not([hidden])'
            )?.dataset.roleId || ''
        );

        try {
            const storedRole =
                sessionStorage.getItem('acl-role') || '';

            if (
                storedRole !== ''
                && workbench.querySelector(
                    `[data-acl-role-panel][data-role-id="${CSS.escape(storedRole)}"]`
                )
            ) {
                initialRole = storedRole;
            }
        } catch (_) {
        }

        activateRole(initialRole);
    }
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
