<?php

$section = (string) ($page['section'] ?? '');
$sections = is_array($page['sections'] ?? null)
    ? $page['sections']
    : [];
$types = $page['provider_types'] ?? [];
$instances = $page['provider_instances'] ?? [];
$defaults = $page['provider_defaults'] ?? [];
$rules = $page['routing_rules'] ?? [];
$channels = $page['channels'] ?? [];
$preferences = $page['preferences'] ?? [];
$deliveries = $page['deliveries'] ?? [];
$status = (string) ($status ?? '');
$enabled = [];

foreach ($preferences as $preference) {
    if (!empty($preference['is_enabled'])) {
        $enabled[] = (string) $preference['channel_code'];
    }
}

ob_start();
require BASE_PATH
    . '/resources/views/admin/partials/communication-style.php';
?>
<section class="communication-settings-shell">
    <div class="communication-tabs" aria-label="بخش‌های تنظیمات پیام و اعلان">
        <?php foreach ($sections as $key => $label): ?>
            <a
                class="<?= $section === $key
                    ? 'is-active'
                    : '' ?>"
                href="<?= admin_h(
                    '/admin/communications/settings?section='
                    . rawurlencode((string) $key)
                ) ?>"
                <?= $section === $key
                    ? 'aria-current="page"'
                    : '' ?>
            >
                <?= admin_h($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($status === 'saved'): ?>
        <div class="admin-alert admin-alert--success">
            روش‌های دریافت اعلان ذخیره شد.
        </div>
    <?php endif; ?>

    <section class="communication-panel">
        <header class="communication-panel__head">
            <div>
                <h2><?= admin_h(
                    $sections[$section]
                    ?? 'تنظیمات پیام و اعلان'
                ) ?></h2>
                <p class="communication-muted">
                    تنظیمات این بخش بر اساس سطح دسترسی فعال شما نمایش داده می‌شود.
                </p>
            </div>
        </header>

        <?php if ($section === 'providers'): ?>
            <p class="communication-muted">
                انواع سرویس‌دهنده و Schema تنظیمات از دیتابیس خوانده می‌شوند.
                ثبت حساب و Secret در مرحله بعد تکمیل می‌شود.
            </p>
            <div class="communication-table-wrap">
                <table class="communication-table">
                    <thead>
                        <tr>
                            <th>نوع</th>
                            <th>کانال</th>
                            <th>Driver</th>
                            <th>مانده اعتبار</th>
                            <th>حساب ثبت‌شده</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($types as $type): ?>
                        <tr>
                            <td><?= admin_h($type['title']) ?></td>
                            <td><?= admin_h($type['channel_code']) ?></td>
                            <td dir="ltr"><?= admin_h($type['driver_code']) ?></td>
                            <td><?= !empty($type['supports_balance'])
                                ? 'دارد'
                                : 'ندارد' ?></td>
                            <td><?= admin_h(
                                \App\Support\AdminFormat::digits(
                                    count(array_filter(
                                        $instances,
                                        fn (array $instance): bool =>
                                            (int) $instance['provider_type_id']
                                            === (int) $type['id']
                                    ))
                                )
                            ) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($section === 'defaults'): ?>
            <?php if ($defaults === []): ?>
                <p class="admin-empty-state">
                    هنوز سرویس پیش‌فرضی ثبت نشده است.
                </p>
            <?php else: ?>
                <div class="communication-table-wrap">
                    <table class="communication-table">
                        <thead>
                            <tr>
                                <th>کانال</th>
                                <th>سرویس‌دهنده</th>
                                <th>اولویت</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($defaults as $item): ?>
                            <tr>
                                <td><?= admin_h($item['channel_code']) ?></td>
                                <td><?= admin_h($item['provider_title']) ?></td>
                                <td><?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $item['priority']
                                    )
                                ) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php elseif ($section === 'routing'): ?>
            <div class="communication-table-wrap">
                <table class="communication-table">
                    <thead>
                        <tr>
                            <th>رویداد</th>
                            <th>کانال</th>
                            <th>سرویس‌دهنده</th>
                            <th>اجباری</th>
                            <th>فعال</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rules as $rule): ?>
                        <tr>
                            <td><?= admin_h(
                                $rule['event_title']
                                ?? $rule['event_type']
                            ) ?></td>
                            <td><?= admin_h($rule['channel_code']) ?></td>
                            <td><?= admin_h(
                                $rule['provider_title']
                                ?? 'انتخاب خودکار'
                            ) ?></td>
                            <td><?= !empty($rule['is_mandatory'])
                                ? 'بله'
                                : 'خیر' ?></td>
                            <td><?= !empty($rule['is_enabled'])
                                ? 'فعال'
                                : 'غیرفعال' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($section === 'preferences'): ?>
            <div class="communication-preference-intro">
                <div>
                    <strong>روش‌های دریافت اعلان</strong>
                    <p>
                        کانال‌های دلخواه خود را فعال کنید. رویدادهای اجباری
                        سامانه مستقل از انتخاب شخصی ارسال می‌شوند.
                    </p>
                </div>
                <span class="communication-badge">
                    <?= admin_h(
                        \App\Support\AdminFormat::digits(
                            count($enabled)
                        )
                    ) ?>
                    کانال فعال
                </span>
            </div>

            <form
                class="communication-preference-form"
                method="post"
                action="/admin/communications/settings/preferences"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h(
                        (new \IPKF\Security\Csrf())->token()
                    ) ?>"
                >

                <div class="communication-preference-grid">
                    <?php foreach ($channels as $channel): ?>
                        <?php
                        $code = (string) $channel['code'];
                        $checked = in_array($code, $enabled, true)
                            || (
                                $preferences === []
                                && !empty($channel['is_internal'])
                            );
                        ?>
                        <label class="communication-preference-card">
                            <span class="communication-preference-card__main">
                                <strong><?= admin_h($channel['title']) ?></strong>
                                <small>
                                    <?= !empty($channel['is_internal'])
                                        ? 'نمایش اعلان در داخل سامانه و کارتابل شما'
                                        : 'دریافت اعلان از طریق این کانال پس از فعال‌شدن سرویس‌دهنده' ?>
                                </small>
                                <span class="communication-preference-card__meta" dir="ltr">
                                    <?= admin_h($channel['driver_code']) ?>
                                </span>
                            </span>
                            <span class="communication-switch">
                                <input
                                    type="checkbox"
                                    name="channels[]"
                                    value="<?= admin_h($code) ?>"
                                    <?= $checked ? 'checked' : '' ?>
                                >
                                <span aria-hidden="true"></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="communication-actions">
                    <button class="admin-button" type="submit">
                        ذخیره روش‌های دریافت
                    </button>
                </div>
            </form>

        <?php elseif ($section === 'reports'): ?>
            <?php if ($deliveries === []): ?>
                <p class="admin-empty-state">
                    هنوز گزارشی برای ارسال یا تحویل ثبت نشده است.
                </p>
            <?php else: ?>
                <div class="communication-table-wrap">
                    <table class="communication-table">
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>کاربر</th>
                                <th>کانال</th>
                                <th>وضعیت</th>
                                <th>تلاش</th>
                                <th>تحویل</th>
                                <th>خطا</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($deliveries as $item): ?>
                            <tr>
                                <td><?= admin_h($item['title']) ?></td>
                                <td><?= admin_h(
                                    $item['user_id']
                                    ?? $item['user_reference']
                                ) ?></td>
                                <td><?= admin_h($item['channel_code']) ?></td>
                                <td><?= admin_h($item['status_code']) ?></td>
                                <td><?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $item['attempt_count']
                                    )
                                ) ?></td>
                                <td dir="ltr"><?= admin_h(
                                    $item['delivered_at']
                                    ?? $item['sent_at']
                                    ?? '—'
                                ) ?></td>
                                <td><?= admin_h($item['last_error'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
