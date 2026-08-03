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
$messageSettings = $page['message_settings'] ?? [];
$status = (string) ($status ?? '');
$enabled = [];
$channelLabels = [
    'in_app' => 'پیام‌رسان داخلی',
    'email' => 'ایمیل',
    'sms' => 'پیامک',
    'bale' => 'پیام‌رسان بله',
];
$deliveryStatusLabels = [
    'delivered' => 'تحویل‌شده',
    'sent' => 'ارسال‌شده',
    'failed' => 'ناموفق',
    'pending' => 'در انتظار',
    'queued' => 'در صف ارسال',
    'processing' => 'در حال ارسال',
    'cancelled' => 'لغوشده',
];

foreach ($preferences as $preference) {
    if (!empty($preference['is_enabled'])) {
        $enabled[] = (string) $preference['channel_code'];
    }
}

if ($preferences === []) {
    foreach ($channels as $channel) {
        if (!empty($channel['is_internal'])) {
            $enabled[] = (string) $channel['code'];
        }
    }
}

$enabled = array_values(array_unique($enabled));

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

        <?php if ($section === 'internal'): ?>
            <form class="communication-form" method="post" action="/admin/communications/settings/internal-messages">
                <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                <label><span>وضعیت پیام داخلی</span><input type="checkbox" name="enabled" value="1" <?= ($messageSettings['enabled'] ?? '1') === '1' ? 'checked' : '' ?>> فعال</label>
                <label><span>پیوست پیام</span><input type="checkbox" name="attachments_enabled" value="1" <?= ($messageSettings['attachments_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> فعال</label>
                <label><span>حداکثر تعداد فایل</span><input type="number" name="attachment_max_files" min="1" max="10" value="<?= admin_h($messageSettings['attachment_max_files'] ?? '3') ?>"></label>
                <label><span>حداکثر حجم هر فایل (MB)</span><input type="number" name="attachment_max_each_mb" min="1" max="50" value="<?= admin_h($messageSettings['attachment_max_each_mb'] ?? '10') ?>"></label>
                <label><span>حداکثر مجموع فایل‌ها (MB)</span><input type="number" name="attachment_max_total_mb" min="1" max="100" value="<?= admin_h($messageSettings['attachment_max_total_mb'] ?? '20') ?>"></label>
                <label class="communication-form__wide"><span>پسوندهای مجاز</span><input name="attachment_extensions" value="<?= admin_h($messageSettings['attachment_extensions'] ?? 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,txt') ?>"></label>
                <label><span>ممیزی نظارتی</span><input type="checkbox" name="monitor_reason_required" value="1" <?= ($messageSettings['monitor_reason_required'] ?? '1') === '1' ? 'checked' : '' ?>> ثبت دلیل اجباری باشد</label>
                <label><span>نگهداری لاگ (روز)</span><input type="number" name="audit_retention_days" min="365" max="3650" value="<?= admin_h($messageSettings['audit_retention_days'] ?? '3650') ?>"></label>
                <div class="communication-actions communication-form__wide"><button class="admin-button" type="submit">ذخیره تنظیمات پیام‌رسان</button><a class="admin-button admin-button--soft" href="/admin/messages/monitor">نظارت بر پیام‌ها</a></div>
            </form>
        <?php elseif ($section === 'providers'): ?>
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
                <span
                    class="communication-badge"
                    data-active-channel-count
                >
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

            <script>
            (() => {
                const form = document.querySelector(
                    '.communication-preference-form'
                );
                const badge = document.querySelector(
                    '[data-active-channel-count]'
                );

                if (!form || !badge) {
                    return;
                }

                const digits = new Intl.NumberFormat('fa-IR');
                const updateCount = () => {
                    const count = form.querySelectorAll(
                        'input[name="channels[]"]:checked'
                    ).length;
                    badge.textContent =
                        `${digits.format(count)} کانال فعال`;
                };

                form.addEventListener('change', (event) => {
                    if (event.target.matches(
                        'input[name="channels[]"]'
                    )) {
                        updateCount();
                    }
                });
                updateCount();
            })();
            </script>

        <?php elseif ($section === 'reports'): ?>
            <?php if ($deliveries === []): ?>
                <p class="admin-empty-state">
                    هنوز گزارشی برای ارسال یا تحویل ثبت نشده است.
                </p>
            <?php else: ?>
                <div class="communication-report-tools">
                    <input type="search" placeholder="جست‌وجو در گزارش…" data-delivery-filter>
                    <select data-delivery-status><option value="">همه وضعیت‌ها</option><option value="delivered">تحویل‌شده</option><option value="sent">ارسال‌شده</option><option value="failed">ناموفق</option><option value="pending">در انتظار</option></select>
                    <select data-delivery-sort><option value="date-desc">جدیدترین تحویل</option><option value="date-asc">قدیمی‌ترین تحویل</option><option value="title-asc">عنوان: صعودی</option><option value="title-desc">عنوان: نزولی</option><option value="status-asc">وضعیت: صعودی</option><option value="status-desc">وضعیت: نزولی</option></select>
                    <button class="admin-button" type="button" data-delivery-apply>اعمال</button>
                    <button class="admin-button admin-button--soft" type="button" data-delivery-clear>پاک‌کردن</button>
                </div>
                <div class="communication-table-wrap">
                    <table class="communication-table" data-delivery-table>
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
                            <?php
                            $channelCode = (string) ($item['channel_code'] ?? '');
                            $statusCode = (string) ($item['status_code'] ?? '');
                            $userValue = $item['user_title'] ?? $item['user_name'] ?? $item['user_id'] ?? $item['user_reference'] ?? '';
                            $deliveryDate = $item['delivered_at'] ?? $item['sent_at'] ?? '';
                            $displayDeliveryDate = \App\Support\AdminFormat::jalaliDateTime($deliveryDate) ?: '—';
                            $searchValue = implode(' ', [
                                (string) ($item['title'] ?? ''),
                                (string) $userValue,
                                \App\Support\AdminFormat::digits((string) $userValue),
                                $channelCode,
                                $channelLabels[$channelCode] ?? $channelCode,
                                $statusCode,
                                $deliveryStatusLabels[$statusCode] ?? $statusCode,
                                $displayDeliveryDate,
                                (string) ($item['last_error'] ?? ''),
                            ]);
                            ?>
                            <tr data-title="<?= admin_h(mb_strtolower((string) ($item['title'] ?? ''), 'UTF-8')) ?>" data-status="<?= admin_h(mb_strtolower($statusCode, 'UTF-8')) ?>" data-search="<?= admin_h(mb_strtolower($searchValue, 'UTF-8')) ?>" data-date="<?= admin_h((string) $deliveryDate) ?>">
                                <td><?= admin_h($item['title']) ?></td>
                                <td><?= admin_h(
                                    \App\Support\AdminFormat::digits($userValue)
                                ) ?></td>
                                <td><?= admin_h($channelLabels[$channelCode] ?? $channelCode) ?></td>
                                <td><?= admin_h($deliveryStatusLabels[$statusCode] ?? $statusCode) ?></td>
                                <td><?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $item['attempt_count']
                                    )
                                ) ?></td>
                                <td dir="ltr"><?= admin_h($displayDeliveryDate) ?></td>
                                <td><?= admin_h($item['last_error'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="communication-muted" data-delivery-empty hidden>گزارشی مطابق فیلتر پیدا نشد.</p>
                <script>
                (function () {
                    var table = document.querySelector('[data-delivery-table]');
                    if (!table) return;
                    var body = table.tBodies[0];
                    var rows = Array.from(body.rows);
                    var query = document.querySelector('[data-delivery-filter]');
                    var status = document.querySelector('[data-delivery-status]');
                    var sort = document.querySelector('[data-delivery-sort]');
                    var empty = document.querySelector('[data-delivery-empty]');
                    function apply() {
                        var needle = (query.value || '').trim().toLocaleLowerCase('fa');
                        var wanted = status.value;
                        var parts = sort.value.split('-');
                        var key = parts[0];
                        var direction = parts[1] === 'asc' ? 1 : -1;
                        rows.sort(function (a, b) { return (a.dataset[key] || '').localeCompare(b.dataset[key] || '', 'fa') * direction; }).forEach(function (row) {
                            row.hidden = !((!needle || row.dataset.search.includes(needle)) && (!wanted || row.dataset.status === wanted));
                            body.appendChild(row);
                        });
                        empty.hidden = rows.some(function (row) { return !row.hidden; });
                    }
                    document.querySelector('[data-delivery-apply]').addEventListener('click', apply);
                    document.querySelector('[data-delivery-clear]').addEventListener('click', function () { query.value = ''; status.value = ''; sort.value = 'date-desc'; apply(); });
                    query.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); apply(); } });
                })();
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
