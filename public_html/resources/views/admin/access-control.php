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
    'communications' => 'پیام‌ها و اعلان‌ها',
    'messages' => 'پیام‌رسان داخلی',
    'automation' => 'اتوماسیون اداری',
    'work' => 'مدیریت کار',
    'core' => 'هسته سامانه',
];

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

<section class="acl-shell">
    <header class="acl-hero">
        <div>
            <span>مدیریت متمرکز RBAC</span>
            <h2>سطوح و نقش‌های دسترسی</h2>
            <p>
                دسترسی منوها، زیرمنوها، صفحات، تب‌ها و عملیات
                را برای نقش‌ها و کاربران تعیین کنید.
            </p>
        </div>
        <div class="acl-counts">
            <article>
                <span>نقش‌ها</span>
                <strong><?= admin_h(
                    \App\Support\AdminFormat::digits(count($roles))
                ) ?></strong>
            </article>
            <article>
                <span>گروه‌های مجوز</span>
                <strong><?= admin_h(
                    \App\Support\AdminFormat::digits(count($groups))
                ) ?></strong>
            </article>
        </div>
    </header>

    <nav class="acl-tabs">
        <?php foreach ([
            'roles' => 'نقش‌ها و مجوزها',
            'users' => 'دسترسی اختصاصی کاربران',
            'audit' => 'تاریخچه تغییرات',
        ] as $code => $title): ?>
            <a
                href="/admin/access-control?tab=<?= admin_h($code) ?>"
                class="<?= $tab === $code ? 'is-active' : '' ?>"
            >
                <?= admin_h($title) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'roles'): ?>
        <section class="acl-panel">
            <header class="acl-panel__header">
                <div>
                    <h3>مجوزهای نقش‌ها</h3>
                    <p>
                        هر نقش را باز کنید و دسترسی تمام ماژول‌ها
                        و عملیات ثبت‌شده را ذخیره کنید.
                    </p>
                </div>
                <input
                    type="search"
                    placeholder="جستجو در مجوزها..."
                    data-acl-search
                >
            </header>

            <?php foreach ($roles as $role): ?>
                <?php
                $protected = ($role['code'] ?? '') === 'super_admin';
                ?>
                <details class="acl-role" <?= $protected ? 'open' : '' ?>>
                    <summary>
                        <span>
                            <strong><?= admin_h($role['title']) ?></strong>
                            <small dir="ltr"><?= admin_h($role['code']) ?></small>
                        </span>
                        <em>
                            <?= $protected
                                ? 'دسترسی کامل ثابت'
                                : 'ویرایش مجوزها' ?>
                        </em>
                    </summary>

                    <form
                        method="post"
                        action="/admin/access-control/roles"
                        data-acl-role-form
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= admin_h($csrf) ?>"
                        >
                        <input
                            type="hidden"
                            name="role_id"
                            value="<?= (int) $role['id'] ?>"
                        >

                        <?php foreach ($groups as $module => $moduleGroups): ?>
                            <section class="acl-module" data-acl-module>
                                <header>
                                    <div>
                                        <h4><?= admin_h(
                                            $moduleTitles[$module] ?? $module
                                        ) ?></h4>
                                        <small dir="ltr"><?= admin_h($module) ?></small>
                                    </div>
                                    <?php if (!$protected): ?>
                                        <div>
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

                                <?php foreach (
                                    $moduleGroups as $groupTitle => $items
                                ): ?>
                                    <div class="acl-group">
                                        <h5><?= admin_h($groupTitle) ?></h5>
                                        <div class="acl-grid">
                                            <?php foreach ($items as $permission): ?>
                                                <?php
                                                $code = (string) $permission['code'];
                                                $checked = isset(
                                                    $roleMap[(int) $role['id']][$code]
                                                );
                                                ?>
                                                <label
                                                    class="acl-item"
                                                    data-acl-item
                                                    data-search="<?= admin_h(
                                                        mb_strtolower(
                                                            implode(' ', [
                                                                $permission['title'] ?? '',
                                                                $code,
                                                                $permission['description'] ?? '',
                                                                $groupTitle,
                                                                $module,
                                                            ]),
                                                            'UTF-8'
                                                        )
                                                    ) ?>"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="<?= admin_h($code) ?>"
                                                        <?= $checked ? 'checked' : '' ?>
                                                        <?= $protected ? 'disabled' : '' ?>
                                                    >
                                                    <span>
                                                        <strong><?= admin_h(
                                                            $permission['title']
                                                        ) ?></strong>
                                                        <small dir="ltr"><?= admin_h($code) ?></small>
                                                        <?php if (
                                                            !empty($permission['is_sensitive'])
                                                        ): ?>
                                                            <em>حساس</em>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>

                        <div class="acl-save">
                            <label>
                                <span>دلیل تغییر مجوزها</span>
                                <input
                                    name="reason"
                                    maxlength="500"
                                    placeholder="برای ثبت در تاریخچه"
                                    <?= $protected ? 'readonly' : '' ?>
                                >
                            </label>
                            <button
                                class="admin-button"
                                type="submit"
                                <?= $protected ? 'disabled' : '' ?>
                            >
                                <?= $protected
                                    ? 'دسترسی کامل ثابت'
                                    : 'ذخیره مجوزهای نقش' ?>
                            </button>
                        </div>
                    </form>
                </details>
            <?php endforeach; ?>
        </section>

    <?php elseif ($tab === 'users'): ?>
        <section class="acl-panel">
            <header class="acl-panel__header">
                <div>
                    <h3>جستجوی اشخاص و کاربران</h3>
                    <p>
                        نام، نام کاربری، کد ملی، موبایل، نقش
                        یا سازمان را جستجو کنید.
                    </p>
                </div>
            </header>

            <form method="get" action="/admin/access-control" class="acl-user-search">
                <input type="hidden" name="tab" value="users">
                <input
                    type="search"
                    name="q"
                    value="<?= admin_h($page['query'] ?? '') ?>"
                    placeholder="نام، کد ملی، موبایل، نقش یا سازمان"
                >
                <button class="admin-button" type="submit">جستجو</button>
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
            </div>
        </section>

        <?php if ($selectedUser !== null): ?>
            <section class="acl-panel">
                <header class="acl-panel__header">
                    <div>
                        <span>کاربر انتخاب‌شده</span>
                        <h3><?= admin_h($selectedUser['title']) ?></h3>
                    </div>
                </header>

                <form method="get" action="/admin/access-control" class="acl-assignment">
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
                            <option value="0">همه نقش‌های فعال کاربر</option>
                            <?php foreach ($assignments as $assignment): ?>
                                <option
                                    value="<?= (int) $assignment['id'] ?>"
                                    <?= (int) $assignment['id'] === $assignmentId
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= admin_h(
                                        $assignment['role_title']
                                        . ' — '
                                        . $assignment['scope_type']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>

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
                            <h4>سیاست ارسال اعلان</h4>
                            <p>
                                تعیین کنید کاربر فرم را نبیند،
                                با تأیید ارسال کند یا مستقیم ارسال داشته باشد.
                            </p>
                        </header>

                        <div>
                            <?php foreach ([
                                'none' => [
                                    'عدم دسترسی',
                                    'منو و فرم ارسال نمایش داده نمی‌شود.',
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
                                <label>
                                    <input
                                        type="radio"
                                        name="notification_policy"
                                        value="<?= admin_h($code) ?>"
                                        <?= $code === (
                                            $page['notification_policy'] ?? 'none'
                                        ) ? 'checked' : '' ?>
                                    >
                                    <span>
                                        <strong><?= admin_h($definition[0]) ?></strong>
                                        <small><?= admin_h($definition[1]) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="acl-capabilities">
                        <?php
                        $capabilities = [
                            'can_search_recipients' => [
                                'notifications.recipients.search',
                                'جستجوی اشخاص و گیرندگان',
                            ],
                            'can_view_recipient_details' => [
                                'notifications.recipients.details',
                                'مشاهده نقش، سازمان و شهر گیرندگان',
                            ],
                            'can_use_manual_targets' => [
                                'notifications.manual_targets.use',
                                'ورود مقصد دستی',
                            ],
                        ];
                        ?>
                        <?php foreach ($capabilities as $name => $definition): ?>
                            <?php
                            $permissionCode = $definition[0];
                            $effective = isset($overrides[$permissionCode])
                                ? $overrides[$permissionCode] === 'allow'
                                : isset($inherited[$permissionCode]);
                            ?>
                            <label>
                                <input
                                    type="checkbox"
                                    name="<?= admin_h($name) ?>"
                                    value="1"
                                    <?= $effective ? 'checked' : '' ?>
                                >
                                <span>
                                    <strong><?= admin_h($definition[1]) ?></strong>
                                    <small dir="ltr"><?= admin_h(
                                        $permissionCode
                                    ) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </section>

                    <div class="acl-save">
                        <label>
                            <span>دلیل تغییر دسترسی</span>
                            <input
                                name="reason"
                                maxlength="500"
                                required
                                placeholder="مثلاً: مجوز ارسال مستقیم طبق ابلاغ مدیر"
                            >
                        </label>
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
                    <h3>تاریخچه تغییرات دسترسی</h3>
                    <p>آخرین تغییرات نقش‌ها و سیاست کاربران.</p>
                </div>
            </header>

            <div class="admin-table-wrap">
                <table class="admin-table">
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
                                <td dir="ltr"><?= admin_h($row['created_at']) ?></td>
                                <td><?= admin_h($row['actor_title']) ?></td>
                                <td>
                                    <?= admin_h($row['target_type']) ?>
                                    #<?= admin_h(
                                        \App\Support\AdminFormat::digits(
                                            $row['target_id']
                                        )
                                    ) ?>
                                </td>
                                <td><code><?= admin_h(
                                    $row['change_type']
                                ) ?></code></td>
                                <td><?= admin_h($row['reason'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</section>

<style>
.acl-shell{display:grid;gap:.8rem}
.acl-hero,.acl-panel{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:14px;padding:.85rem}
.acl-hero{align-items:center;background:linear-gradient(135deg,var(--admin-surface),var(--admin-primary-soft));display:flex;justify-content:space-between;gap:1rem}
.acl-hero h2,.acl-hero p,.acl-panel h3,.acl-panel p{margin:0}
.acl-hero>div:first-child{display:grid;gap:.18rem}
.acl-hero span{color:var(--admin-primary);font-size:.7rem;font-weight:800}
.acl-hero p,.acl-panel p{color:var(--admin-text-muted);font-size:.72rem}
.acl-counts{display:grid;grid-template-columns:repeat(2,minmax(95px,1fr));gap:.4rem}
.acl-counts article{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:9px;display:grid;padding:.45rem}
.acl-tabs{display:flex;flex-wrap:wrap;gap:.4rem}
.acl-tabs a{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:999px;color:var(--admin-text);font-size:.74rem;padding:.42rem .68rem;text-decoration:none}
.acl-tabs a.is-active{background:var(--admin-primary-soft);border-color:var(--admin-primary);color:var(--admin-primary);font-weight:800}
.acl-panel{display:grid;gap:.65rem}
.acl-panel__header,.acl-module>header,.acl-policy>header{align-items:center;display:flex;justify-content:space-between;gap:.6rem}
.acl-role{border:1px solid var(--admin-border);border-radius:11px;overflow:hidden}
.acl-role>summary{align-items:center;background:var(--admin-surface-muted);cursor:pointer;display:flex;justify-content:space-between;padding:.58rem}
.acl-role>summary span{display:grid;gap:.05rem}
.acl-role>summary small,.acl-role>summary em{color:var(--admin-text-muted);font-size:.62rem;font-style:normal}
.acl-role form{display:grid;gap:.55rem;padding:.6rem}
.acl-module{border:1px solid var(--admin-border);border-radius:10px;display:grid;gap:.4rem;padding:.5rem}
.acl-module>header{background:var(--admin-surface-muted);border-radius:8px;padding:.4rem}
.acl-module h4,.acl-group h5,.acl-policy h4{margin:0}
.acl-module header button{background:transparent;border:0;color:var(--admin-primary);cursor:pointer;font:inherit;font-size:.64rem}
.acl-group{display:grid;gap:.3rem}
.acl-group h5{font-size:.7rem}
.acl-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.32rem}
.acl-item{align-items:flex-start;border:1px solid var(--admin-border);border-radius:8px;display:flex;gap:.38rem;padding:.42rem}
.acl-item[hidden]{display:none!important}
.acl-item span{display:grid;gap:.05rem;min-width:0}
.acl-item strong{font-size:.69rem}
.acl-item small{color:var(--admin-text-muted);font-size:.57rem;overflow:hidden;text-overflow:ellipsis}
.acl-item em{color:#9b3434;font-size:.56rem;font-style:normal}
.acl-save{align-items:end;background:var(--admin-surface-muted);border:1px solid var(--admin-border);border-radius:9px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.5rem;padding:.5rem}
.acl-save label,.acl-assignment label{display:grid;gap:.22rem}
.acl-save label span,.acl-assignment label span{font-size:.68rem;font-weight:800}
.acl-user-search{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.4rem}
.acl-users{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.35rem}
.acl-users a{border:1px solid var(--admin-border);border-radius:9px;color:var(--admin-text);display:grid;gap:.08rem;padding:.48rem;text-decoration:none;min-width:0}
.acl-users a.is-active{background:var(--admin-primary-soft);border-color:var(--admin-primary)}
.acl-users span,.acl-users small{color:var(--admin-text-muted);font-size:.61rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.acl-assignment{max-width:520px}
.acl-user-policy{display:grid;gap:.55rem}
.acl-policy{background:var(--admin-surface-muted);border:1px solid var(--admin-border);border-radius:10px;display:grid;gap:.5rem;padding:.55rem}
.acl-policy>div{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.35rem}
.acl-policy label,.acl-capabilities label{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:8px;display:flex;gap:.35rem;padding:.45rem}
.acl-policy label span,.acl-capabilities label span{display:grid;gap:.05rem}
.acl-policy strong,.acl-capabilities strong{font-size:.68rem}
.acl-policy small,.acl-capabilities small{color:var(--admin-text-muted);font-size:.58rem}
.acl-capabilities{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.35rem}
@media(max-width:1050px){.acl-users{grid-template-columns:repeat(2,minmax(0,1fr))}.acl-policy>div{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.acl-hero{align-items:stretch;flex-direction:column}.acl-grid,.acl-users,.acl-policy>div,.acl-capabilities,.acl-counts{grid-template-columns:1fr}.acl-panel__header{align-items:stretch;flex-direction:column}.acl-save{grid-template-columns:1fr}}
</style>

<script>
(() => {
    const search = document.querySelector('[data-acl-search]');
    const modules = Array.from(
        document.querySelectorAll('[data-acl-module]')
    );

    search?.addEventListener('input', () => {
        const needle = (search.value || '')
            .trim()
            .toLocaleLowerCase('fa');

        modules.forEach((module) => {
            let visible = 0;

            module.querySelectorAll('[data-acl-item]')
                .forEach((item) => {
                    item.hidden = !(
                        needle === ''
                        || item.dataset.search.includes(needle)
                    );

                    if (!item.hidden) {
                        visible++;
                    }
                });

            module.hidden = needle !== '' && visible === 0;
        });
    });

    document.querySelectorAll('[data-acl-role-form]')
        .forEach((form) => {
            form.querySelectorAll('[data-acl-module]')
                .forEach((module) => {
                    module.querySelector('[data-acl-select]')
                        ?.addEventListener('click', () => {
                            module.querySelectorAll(
                                'input[type="checkbox"]:not(:disabled)'
                            ).forEach((checkbox) => {
                                checkbox.checked = true;
                            });
                        });

                    module.querySelector('[data-acl-clear]')
                        ?.addEventListener('click', () => {
                            module.querySelectorAll(
                                'input[type="checkbox"]:not(:disabled)'
                            ).forEach((checkbox) => {
                                checkbox.checked = false;
                            });
                        });
                });
        });
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
