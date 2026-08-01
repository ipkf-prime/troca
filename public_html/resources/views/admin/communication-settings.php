<?php

$section = (string) ($page['section'] ?? 'providers');
$types = $page['provider_types'] ?? [];
$instances = $page['provider_instances'] ?? [];
$defaults = $page['provider_defaults'] ?? [];
$rules = $page['routing_rules'] ?? [];
$channels = $page['channels'] ?? [];
$preferences = $page['preferences'] ?? [];
$deliveries = $page['deliveries'] ?? [];
$enabled = [];

foreach ($preferences as $preference) {
    if (!empty($preference['is_enabled'])) {
        $enabled[] = (string) $preference['channel_code'];
    }
}

$sections = [
    'providers' => 'سرویس‌دهنده‌ها',
    'defaults' => 'پیش‌فرض سرویس‌دهنده‌ها',
    'routing' => 'قواعد ارسال',
    'preferences' => 'ترجیحات ارسال من',
    'reports' => 'گزارش ارسال و تحویل',
];

ob_start();
require BASE_PATH
    . '/resources/views/admin/partials/communication-style.php';
?>
<div class="communication-tabs">
    <?php foreach ($sections as $key => $label): ?>
        <a
            class="<?= $section === $key
                ? 'is-active'
                : '' ?>"
            href="<?= admin_h(
                '/admin/communications/settings?section='
                . rawurlencode($key)
            ) ?>"
        >
            <?= admin_h($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<section class="communication-panel">
    <h2><?= admin_h(
        $sections[$section] ?? 'تنظیمات'
    ) ?></h2>

    <?php if ($section === 'providers'): ?>
        <p class="communication-muted">
            انواع سرویس‌دهنده و Schema تنظیمات از دیتابیس
            خوانده می‌شوند. ثبت حساب و Secret در مرحله بعد
            تکمیل می‌شود.
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
                        <td><?= admin_h(
                            $type['channel_code']
                        ) ?></td>
                        <td dir="ltr"><?= admin_h(
                            $type['driver_code']
                        ) ?></td>
                        <td><?= !empty(
                            $type['supports_balance']
                        ) ? 'دارد' : 'ندارد' ?></td>
                        <td><?= admin_h(
                            \App\Support\AdminFormat::digits(
                                count(array_filter(
                                    $instances,
                                    fn (array $instance): bool =>
                                        (int) $instance[
                                            'provider_type_id'
                                        ] === (int) $type['id']
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
            <p class="communication-muted">
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
                            <td><?= admin_h(
                                $item['channel_code']
                            ) ?></td>
                            <td><?= admin_h(
                                $item['provider_title']
                            ) ?></td>
                            <td><?= admin_h(
                                $item['priority']
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
                        <td><?= admin_h(
                            $rule['channel_code']
                        ) ?></td>
                        <td><?= admin_h(
                            $rule['provider_title']
                            ?? 'انتخاب خودکار'
                        ) ?></td>
                        <td><?= !empty(
                            $rule['is_mandatory']
                        ) ? 'بله' : 'خیر' ?></td>
                        <td><?= !empty(
                            $rule['is_enabled']
                        ) ? 'فعال' : 'غیرفعال' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($section === 'preferences'): ?>
        <p class="communication-muted">
            قواعد اجباری مستقل از ترجیحات شخصی اجرا می‌شوند.
        </p>
        <form
            class="communication-form"
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
            <?php foreach ($channels as $channel): ?>
                <label>
                    <input
                        type="checkbox"
                        name="channels[]"
                        value="<?= admin_h(
                            $channel['code']
                        ) ?>"
                        <?= in_array(
                            (string) $channel['code'],
                            $enabled,
                            true
                        ) || (
                            $preferences === []
                            && $channel['code'] === 'in_app'
                        ) ? 'checked' : '' ?>
                    >
                    <?= admin_h($channel['title']) ?>
                </label>
            <?php endforeach; ?>
            <button class="admin-button" type="submit">
                ذخیره ترجیحات
            </button>
        </form>

    <?php elseif ($section === 'reports'): ?>
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
                        <td><?= admin_h(
                            $item['title']
                        ) ?></td>
                        <td><?= admin_h(
                            $item['user_id']
                            ?? $item['user_reference']
                        ) ?></td>
                        <td><?= admin_h(
                            $item['channel_code']
                        ) ?></td>
                        <td><?= admin_h(
                            $item['status_code']
                        ) ?></td>
                        <td><?= admin_h(
                            $item['attempt_count']
                        ) ?></td>
                        <td dir="ltr"><?= admin_h(
                            $item['delivered_at']
                            ?? $item['sent_at']
                            ?? '—'
                        ) ?></td>
                        <td><?= admin_h(
                            $item['last_error'] ?? ''
                        ) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
