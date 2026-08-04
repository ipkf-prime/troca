<?php

$section = (string) ($page['section'] ?? '');
$sections = is_array($page['sections'] ?? null)
    ? $page['sections']
    : [];
$types = $page['provider_types'] ?? [];
$instances = $page['provider_instances'] ?? [];
$providerManagement = is_array(
    $page['provider_management'] ?? null
) ? $page['provider_management'] : [];
$providerDefinitions = is_array(
    $providerManagement['definitions'] ?? null
) ? $providerManagement['definitions'] : [];
$providerForm = is_array(
    $providerManagement['form'] ?? null
) ? $providerManagement['form'] : [];
$defaults = $page['provider_defaults'] ?? [];
$rules = $page['routing_rules'] ?? [];
$channels = $page['channels'] ?? [];
$preferences = $page['preferences'] ?? [];
$deliveries = $page['deliveries'] ?? [];
$messageSettings = $page['message_settings'] ?? [];
$status = (string) ($status ?? '');
$statusMessages = [
    'saved' => ['success', 'روش‌های دریافت اعلان ذخیره شد.'],
    'provider_created' => ['success', 'حساب سرویس‌دهنده با موفقیت ثبت شد.'],
    'provider_updated' => ['success', 'حساب سرویس‌دهنده با موفقیت ویرایش شد.'],
    'provider_enabled' => ['success', 'حساب سرویس‌دهنده فعال شد.'],
    'provider_disabled' => ['success', 'حساب سرویس‌دهنده غیرفعال شد.'],
    'invalid_csrf' => ['error', 'اعتبار درخواست منقضی شده است. صفحه را تازه‌سازی کنید.'],
    'provider_channel_required' => ['error', 'کانال ارسال را انتخاب کنید.'],
    'provider_channel_mismatch' => ['error', 'سرویس‌دهنده با کانال انتخاب‌شده سازگار نیست.'],
    'provider_type_required' => ['error', 'سرویس‌دهنده را انتخاب کنید.'],
    'provider_title_invalid' => ['error', 'عنوان حساب معتبر نیست.'],
    'provider_code_invalid' => ['error', 'کد داخلی حساب معتبر نیست.'],
    'provider_code_exists' => ['error', 'این کد داخلی قبلاً استفاده شده است.'],
    'provider_limit_invalid' => ['error', 'محدودیت روزانه یا ماهانه معتبر نیست.'],
    'provider_instance_not_found' => ['error', 'حساب سرویس‌دهنده پیدا نشد.'],
    'provider_save_failed' => ['error', 'ذخیره حساب سرویس‌دهنده انجام نشد.'],
    'provider_status_failed' => ['error', 'تغییر وضعیت حساب انجام نشد.'],
    'provider_test_sent' => ['success', 'ایمیل آزمایشی با موفقیت به سرور ایمیل تحویل شد.'],
    'provider_test_email_unsupported' => ['error', 'این حساب از آزمون ارسال ایمیل پشتیبانی نمی‌کند.'],
    'provider_test_recipient_invalid' => ['error', 'نشانی ایمیل مقصد معتبر نیست.'],
    'provider_test_subject_invalid' => ['error', 'موضوع ایمیل آزمایشی معتبر نیست.'],
    'provider_test_body_invalid' => ['error', 'متن ایمیل آزمایشی معتبر نیست.'],
    'provider_test_config_invalid' => ['error', 'تنظیمات اتصال حساب ایمیل کامل یا معتبر نیست.'],
    'provider_test_secret_unavailable' => ['error', 'اطلاعات محرمانه حساب ایمیل قابل استفاده نیست.'],
    'provider_test_connection_failed' => ['error', 'اتصال به سرور SMTP برقرار نشد.'],
    'provider_test_timeout' => ['error', 'مهلت اتصال یا پاسخ سرور SMTP به پایان رسید.'],
    'provider_test_tls_failed' => ['error', 'برقراری ارتباط امن TLS با سرور SMTP ناموفق بود.'],
    'provider_test_auth_failed' => ['error', 'احراز هویت در سرور SMTP ناموفق بود.'],
    'provider_test_sender_rejected' => ['error', 'سرور SMTP نشانی فرستنده را نپذیرفت.'],
    'provider_test_recipient_rejected' => ['error', 'سرور SMTP نشانی گیرنده را نپذیرفت.'],
    'provider_test_send_failed' => ['error', 'سرور SMTP پیام آزمایشی را نپذیرفت.'],
    'provider_test_failed' => ['error', 'ارسال آزمایشی ایمیل انجام نشد.'],
];
$enabled = [];
$channelLabels = [
    'in_app' => 'پیام‌رسان داخلی',
    'email' => 'ایمیل',
    'sms' => 'پیام کوتاه (SMS)',
    'messenger' => 'پیام‌رسان',
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

    <?php if (isset($statusMessages[$status])): ?>
        <?php [$alertKind, $alertMessage] = $statusMessages[$status]; ?>
        <div class="admin-alert admin-alert--<?= $alertKind === 'error'
            ? 'danger'
            : 'success' ?>">
            <?= admin_h($alertMessage) ?>
        </div>
    <?php elseif (str_starts_with($status, 'provider_config_required_')): ?>
        <div class="admin-alert admin-alert--danger">
            یکی از تنظیمات الزامی سرویس‌دهنده تکمیل نشده است.
        </div>
    <?php elseif (str_starts_with($status, 'provider_config_invalid_')): ?>
        <div class="admin-alert admin-alert--danger">
            یکی از تنظیمات سرویس‌دهنده معتبر نیست.
        </div>
    <?php elseif (str_starts_with($status, 'provider_secret_required_')): ?>
        <div class="admin-alert admin-alert--danger">
            اطلاعات محرمانه الزامی سرویس‌دهنده وارد نشده است.
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
            <?php
            $providerTypeId = (int) (
                $providerForm['provider_type_id'] ?? 0
            );
            $providerChannelCode = (string) (
                $providerForm['channel_code'] ?? ''
            );
            $isProviderEdit = !empty(
                $providerForm['is_edit']
            );
            $providerConfiguration = is_array(
                $providerForm['configuration'] ?? null
            ) ? $providerForm['configuration'] : [];
            $storedSecretKeys = is_array(
                $providerForm['stored_secret_keys'] ?? null
            ) ? $providerForm['stored_secret_keys'] : [];
            $providerCsrf = (new \IPKF\Security\Csrf())->token();
            $providerError = in_array($status, [
                'invalid_csrf',
                'provider_channel_required',
                'provider_channel_mismatch',
                'provider_type_required',
                'provider_title_invalid',
                'provider_code_invalid',
                'provider_code_exists',
                'provider_limit_invalid',
                'provider_instance_not_found',
                'provider_save_failed',
                'provider_status_failed',
            ], true)
                || str_starts_with($status, 'provider_config_')
                || str_starts_with($status, 'provider_secret_');
            $providerEditorOpen = $isProviderEdit || $providerError;
            $providerOptionLabels = [
                'none' => 'بدون رمزنگاری',
                'tls' => 'TLS',
                'ssl' => 'SSL',
                'plain' => 'متن ساده',
                'HTML' => 'HTML',
                'MarkdownV2' => 'MarkdownV2',
            ];
            ?>
            <div
                class="provider-workspace"
                data-provider-workspace
                data-initial-view="<?= $providerEditorOpen
                    ? 'editor'
                    : 'accounts' ?>"
            >
                <div
                    class="provider-workspace-tabs"
                    role="tablist"
                    aria-label="مدیریت حساب‌های سرویس‌دهنده"
                >
                    <button
                        class="provider-workspace-tab <?= !$providerEditorOpen
                            ? 'is-active'
                            : '' ?>"
                        type="button"
                        role="tab"
                        aria-selected="<?= !$providerEditorOpen
                            ? 'true'
                            : 'false' ?>"
                        data-provider-workspace-tab="accounts"
                    >
                        حساب‌های ثبت‌شده
                        <span><?= admin_h(
                            \App\Support\AdminFormat::digits(
                                count($instances)
                            )
                        ) ?></span>
                    </button>
                    <button
                        class="provider-workspace-tab <?= $providerEditorOpen
                            ? 'is-active'
                            : '' ?>"
                        type="button"
                        role="tab"
                        aria-selected="<?= $providerEditorOpen
                            ? 'true'
                            : 'false' ?>"
                        data-provider-workspace-tab="editor"
                    >
                        <?= $isProviderEdit
                            ? 'ویرایش حساب'
                            : 'افزودن حساب' ?>
                    </button>
                </div>

                <section
                    class="provider-workspace-panel"
                    role="tabpanel"
                    data-provider-workspace-panel="accounts"
                    <?= $providerEditorOpen ? 'hidden' : '' ?>
                >
                    <section class="provider-management-card">
                        <header class="provider-management-card__head">
                            <div>
                                <h3>حساب‌ها و بات‌های ثبت‌شده</h3>
                                <p class="communication-muted">
                                    برای هر سرویس‌دهنده می‌توان چند حساب، بات یا
                                    شماره مستقل ثبت کرد.
                                </p>
                            </div>
                            <?php if ($isProviderEdit): ?>
                                <a
                                    class="admin-button"
                                    href="/admin/communications/settings?section=providers"
                                >
                                    افزودن حساب جدید
                                </a>
                            <?php else: ?>
                                <button
                                    class="admin-button"
                                    type="button"
                                    data-provider-open-editor
                                >
                                    افزودن حساب جدید
                                </button>
                            <?php endif; ?>
                        </header>

                        <?php if ($instances === []): ?>
                            <div class="provider-empty-state">
                                <strong>هنوز حسابی ثبت نشده است.</strong>
                                <p>
                                    نخستین حساب ایمیل، پیام کوتاه یا پیام‌رسان را از
                                    تب «افزودن حساب» ثبت کنید.
                                </p>
                                <button
                                    class="admin-button"
                                    type="button"
                                    data-provider-open-editor
                                >
                                    ثبت نخستین حساب
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="communication-table-wrap">
                                <table class="communication-table provider-accounts-table">
                                    <thead>
                                        <tr>
                                            <th>عنوان</th>
                                            <th>سرویس‌دهنده</th>
                                            <th>کانال</th>
                                            <th>وضعیت</th>
                                            <th>اطلاعات محرمانه</th>
                                            <th>اولویت</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($instances as $instance): ?>
                                        <?php
                                        $instanceEnabled = !empty(
                                            $instance['is_enabled']
                                        );
                                        $reference = (string) (
                                            $instance['public_reference'] ?? ''
                                        );
                                        $channelCode = (string) (
                                            $instance['channel_code'] ?? ''
                                        );
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= admin_h(
                                                    $instance['title']
                                                ) ?></strong>
                                                <small
                                                    class="provider-code"
                                                    dir="ltr"
                                                >
                                                    <?= admin_h(
                                                        $instance['code']
                                                    ) ?>
                                                </small>
                                            </td>
                                            <td><?= admin_h(
                                                $instance[
                                                    'provider_type_title'
                                                ]
                                            ) ?></td>
                                            <td><?= admin_h(
                                                $channelLabels[$channelCode]
                                                ?? $channelCode
                                            ) ?></td>
                                            <td>
                                                <span class="communication-status <?= $instanceEnabled
                                                    ? 'communication-status--active'
                                                    : 'communication-status--closed' ?>">
                                                    <?= $instanceEnabled
                                                        ? 'فعال'
                                                        : 'غیرفعال' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= !empty(
                                                    $instance['has_secret']
                                                )
                                                    ? 'ثبت شده'
                                                    : 'ثبت نشده' ?>
                                            </td>
                                            <td><?= admin_h(
                                                \App\Support\AdminFormat::digits(
                                                    $instance['priority']
                                                )
                                            ) ?></td>
                                            <td>
                                                <div class="provider-row-actions">
                                                    <a
                                                        class="admin-button admin-button--soft admin-button--compact"
                                                        href="<?= admin_h(
                                                            '/admin/communications/settings?section=providers&edit='
                                                            . rawurlencode(
                                                                $reference
                                                            )
                                                        ) ?>"
                                                    >
                                                        ویرایش
                                                    </a>
                                                    <?php if (
                                                        $channelCode === 'email'
                                                        && (string) (
                                                            $instance[
                                                                'driver_code'
                                                            ] ?? ''
                                                        ) === 'smtp'
                                                    ): ?>
                                                        <button
                                                            class="admin-button admin-button--soft admin-button--compact"
                                                            type="button"
                                                            data-provider-test-open
                                                            data-provider-reference="<?= admin_h(
                                                                $reference
                                                            ) ?>"
                                                            data-provider-title="<?= admin_h(
                                                                $instance['title']
                                                            ) ?>"
                                                        >
                                                            تست ارسال
                                                        </button>
                                                    <?php endif; ?>
                                                    <form
                                                        method="post"
                                                        action="<?= admin_h(
                                                            '/admin/communications/settings/providers/'
                                                            . rawurlencode(
                                                                $reference
                                                            )
                                                            . '/status'
                                                        ) ?>"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="_token"
                                                            value="<?= admin_h(
                                                                $providerCsrf
                                                            ) ?>"
                                                        >
                                                        <input
                                                            type="hidden"
                                                            name="enabled"
                                                            value="<?= $instanceEnabled
                                                                ? '0'
                                                                : '1' ?>"
                                                        >
                                                        <button
                                                            class="admin-button admin-button--compact <?= $instanceEnabled
                                                                ? 'admin-button--soft'
                                                                : '' ?>"
                                                            type="submit"
                                                        >
                                                            <?= $instanceEnabled
                                                                ? 'غیرفعال‌کردن'
                                                                : 'فعال‌کردن' ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </section>

                <section
                    class="provider-workspace-panel"
                    role="tabpanel"
                    data-provider-workspace-panel="editor"
                    <?= $providerEditorOpen ? '' : 'hidden' ?>
                >
                    <section class="provider-management-card provider-editor-card">
                        <header class="provider-management-card__head">
                            <div>
                                <h3>
                                    <?= $isProviderEdit
                                        ? 'ویرایش حساب سرویس‌دهنده'
                                        : 'ثبت حساب سرویس‌دهنده' ?>
                                </h3>
                                <p class="communication-muted">
                                    اطلاعات اتصال و اطلاعات محرمانه جداگانه
                                    ذخیره می‌شوند. مقدارهای محرمانه ثبت‌شده
                                    هرگز دوباره نمایش داده نمی‌شوند.
                                </p>
                            </div>
                            <?php if ($isProviderEdit): ?>
                                <a
                                    class="admin-button admin-button--soft"
                                    href="/admin/communications/settings?section=providers"
                                >
                                    ثبت حساب جدید
                                </a>
                            <?php endif; ?>
                        </header>

                        <?php if (
                            ($providerForm['secret_state'] ?? '')
                            === 'unavailable'
                        ): ?>
                            <div class="admin-alert admin-alert--danger">
                                اطلاعات محرمانه این حساب قابل رمزگشایی نیست.
                                پیش از ذخیره، کلید رمزنگاری سامانه را بررسی کنید.
                            </div>
                        <?php endif; ?>

                        <form
                            class="communication-form provider-management-form"
                            method="post"
                            action="/admin/communications/settings/providers/save"
                            data-provider-form
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value="<?= admin_h($providerCsrf) ?>"
                            >
                            <input
                                type="hidden"
                                name="form_mode"
                                value="<?= $isProviderEdit
                                    ? 'edit'
                                    : 'create' ?>"
                            >
                            <input
                                type="hidden"
                                name="public_reference"
                                value="<?= admin_h(
                                    $providerForm['public_reference'] ?? ''
                                ) ?>"
                            >

                            <div
                                class="provider-editor-tabs communication-form__wide"
                                role="tablist"
                                aria-label="بخش‌های فرم حساب سرویس‌دهنده"
                            >
                                <button
                                    class="provider-editor-tab is-active"
                                    type="button"
                                    role="tab"
                                    aria-selected="true"
                                    data-provider-editor-tab="account"
                                >
                                    مشخصات حساب
                                </button>
                                <button
                                    class="provider-editor-tab"
                                    type="button"
                                    role="tab"
                                    aria-selected="false"
                                    data-provider-editor-tab="connection"
                                    <?= $providerTypeId < 1
                                        ? 'disabled aria-disabled="true"'
                                        : '' ?>
                                >
                                    تنظیمات اتصال
                                </button>
                                <button
                                    class="provider-editor-tab"
                                    type="button"
                                    role="tab"
                                    aria-selected="false"
                                    data-provider-editor-tab="secrets"
                                    <?= $providerTypeId < 1
                                        ? 'disabled aria-disabled="true"'
                                        : '' ?>
                                >
                                    اطلاعات محرمانه
                                </button>
                                <button
                                    class="provider-editor-tab"
                                    type="button"
                                    role="tab"
                                    aria-selected="false"
                                    data-provider-editor-tab="advanced"
                                >
                                    تنظیمات پیشرفته
                                </button>
                            </div>

                            <div class="provider-editor-panels communication-form__wide">
                                <section
                                    class="provider-editor-panel"
                                    role="tabpanel"
                                    data-provider-editor-panel="account"
                                >
                                    <div class="provider-form-grid">
                                        <label>
                                            <span>کانال ارسال</span>
                                            <?php if ($isProviderEdit): ?>
                                                <input
                                                    type="hidden"
                                                    name="channel_code"
                                                    value="<?= admin_h(
                                                        $providerChannelCode
                                                    ) ?>"
                                                >
                                            <?php endif; ?>
                                            <select
                                                name="<?= $isProviderEdit
                                                    ? 'channel_code_display'
                                                    : 'channel_code' ?>"
                                                data-provider-channel
                                                <?= $isProviderEdit
                                                    ? 'disabled'
                                                    : '' ?>
                                                required
                                            >
                                                <option value="">
                                                    انتخاب کانال
                                                </option>
                                                <?php foreach (
                                                    ['email', 'sms', 'messenger']
                                                    as $channelCode
                                                ): ?>
                                                    <option
                                                        value="<?= admin_h(
                                                            $channelCode
                                                        ) ?>"
                                                        <?= $providerChannelCode
                                                            === $channelCode
                                                            ? 'selected'
                                                            : '' ?>
                                                    >
                                                        <?= admin_h(
                                                            $channelLabels[
                                                                $channelCode
                                                            ] ?? $channelCode
                                                        ) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small>
                                                ابتدا نوع کانال ارسال را انتخاب کنید.
                                            </small>
                                        </label>

                                        <label>
                                            <span>سرویس‌دهنده</span>
                                            <?php if ($isProviderEdit): ?>
                                                <input
                                                    type="hidden"
                                                    name="provider_type_id"
                                                    value="<?= $providerTypeId ?>"
                                                >
                                            <?php endif; ?>
                                            <select
                                                name="<?= $isProviderEdit
                                                    ? 'provider_type_display'
                                                    : 'provider_type_id' ?>"
                                                data-provider-type
                                                data-provider-locked="<?= $isProviderEdit
                                                    ? 'true'
                                                    : 'false' ?>"
                                                <?= $isProviderEdit
                                                    || $providerChannelCode === ''
                                                    ? 'disabled'
                                                    : '' ?>
                                                required
                                            >
                                                <option value="">
                                                    انتخاب سرویس‌دهنده
                                                </option>
                                                <?php foreach (
                                                    $providerDefinitions
                                                    as $definition
                                                ): ?>
                                                    <option
                                                        value="<?= (int) $definition['id'] ?>"
                                                        data-provider-code="<?= admin_h(
                                                            $definition['code']
                                                        ) ?>"
                                                        data-provider-channel-code="<?= admin_h(
                                                            $definition[
                                                                'channel_code'
                                                            ]
                                                        ) ?>"
                                                        <?= $providerTypeId
                                                            === (int) $definition['id']
                                                            ? 'selected'
                                                            : '' ?>
                                                    >
                                                        <?= admin_h(
                                                            $definition['title']
                                                        ) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small>
                                                سرویس‌دهنده پس از ثبت قابل تغییر نیست.
                                            </small>
                                        </label>

                                        <label>
                                            <span>عنوان حساب</span>
                                            <input
                                                name="title"
                                                maxlength="190"
                                                required
                                                value="<?= admin_h(
                                                    $providerForm['title'] ?? ''
                                                ) ?>"
                                                placeholder="مثلاً بات اطلاع‌رسانی سازمان"
                                            >
                                        </label>

                                        <label class="provider-form-grid__wide">
                                            <span>توضیحات</span>
                                            <textarea
                                                name="description"
                                                maxlength="1000"
                                                placeholder="کاربرد این حساب، بات یا شماره"
                                            ><?= admin_h(
                                                $providerForm['description'] ?? ''
                                            ) ?></textarea>
                                        </label>

                                        <label class="provider-status-field provider-form-grid__wide">
                                            <span class="provider-status-field__text">
                                                <strong>وضعیت حساب</strong>
                                                <small>
                                                    حساب غیرفعال در ارسال خودکار استفاده نمی‌شود.
                                                </small>
                                            </span>
                                            <span class="communication-switch">
                                                <input
                                                    type="checkbox"
                                                    name="is_enabled"
                                                    value="1"
                                                    data-provider-enabled
                                                    <?= !empty(
                                                        $providerForm['is_enabled']
                                                    ) ? 'checked' : '' ?>
                                                >
                                                <span aria-hidden="true"></span>
                                            </span>
                                            <strong data-provider-enabled-label>
                                                <?= !empty(
                                                    $providerForm['is_enabled']
                                                ) ? 'فعال' : 'غیرفعال' ?>
                                            </strong>
                                        </label>
                                    </div>
                                </section>

                                <section
                                    class="provider-editor-panel"
                                    role="tabpanel"
                                    data-provider-editor-panel="connection"
                                    hidden
                                >
                                    <div
                                        class="provider-tab-empty"
                                        data-provider-connection-empty
                                        <?= $providerTypeId > 0
                                            ? 'hidden'
                                            : '' ?>
                                    >
                                        ابتدا نوع سرویس‌دهنده را در تب
                                        «مشخصات حساب» انتخاب کنید.
                                    </div>
                                    <?php foreach (
                                        $providerDefinitions as $definition
                                    ): ?>
                                        <?php
                                        $definitionSelected = $providerTypeId
                                            === (int) $definition['id'];
                                        ?>
                                        <fieldset
                                            class="provider-dynamic-fields"
                                            data-provider-fields="<?= (int) $definition['id'] ?>"
                                            <?= $definitionSelected ? '' : 'hidden' ?>
                                        >
                                            <legend>
                                                تنظیمات اتصال
                                                <?= admin_h($definition['title']) ?>
                                            </legend>
                                            <?php if (
                                                $definition['public_fields'] === []
                                            ): ?>
                                                <p class="provider-tab-empty">
                                                    برای این سرویس‌دهنده تنظیم عمومی
                                                    جداگانه‌ای تعریف نشده است.
                                                </p>
                                            <?php else: ?>
                                                <div class="provider-dynamic-grid">
                                                    <?php foreach (
                                                        $definition['public_fields']
                                                        as $field
                                                    ): ?>
                                                        <?php
                                                        $fieldKey = (string) $field['key'];
                                                        $fieldType = (string) $field['type'];
                                                        $fieldValue = $definitionSelected
                                                            ? ($providerConfiguration[
                                                                $fieldKey
                                                            ] ?? '')
                                                            : '';
                                                        ?>
                                                        <label>
                                                            <span>
                                                                <?= admin_h(
                                                                    $field['label']
                                                                ) ?>
                                                                <?= !empty(
                                                                    $field['required']
                                                                ) ? ' *' : '' ?>
                                                            </span>
                                                            <?php if (
                                                                $fieldType === 'select'
                                                            ): ?>
                                                                <select
                                                                    name="configuration[<?= admin_h(
                                                                        $fieldKey
                                                                    ) ?>]"
                                                                    <?= $definitionSelected
                                                                        ? ''
                                                                        : 'disabled' ?>
                                                                    <?= !empty(
                                                                        $field['required']
                                                                    ) ? 'required' : '' ?>
                                                                >
                                                                    <option value="">
                                                                        انتخاب کنید
                                                                    </option>
                                                                    <?php foreach (
                                                                        $field['options']
                                                                        as $option
                                                                    ): ?>
                                                                        <option
                                                                            value="<?= admin_h(
                                                                                $option
                                                                            ) ?>"
                                                                            <?= (string) $fieldValue
                                                                                === (string) $option
                                                                                ? 'selected'
                                                                                : '' ?>
                                                                        >
                                                                            <?= admin_h(
                                                                                $providerOptionLabels[
                                                                                    (string) $option
                                                                                ] ?? $option
                                                                            ) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            <?php else: ?>
                                                                <input
                                                                    type="<?= admin_h(
                                                                        $fieldType
                                                                    ) ?>"
                                                                    name="configuration[<?= admin_h(
                                                                        $fieldKey
                                                                    ) ?>]"
                                                                    value="<?= admin_h(
                                                                        $fieldValue
                                                                    ) ?>"
                                                                    <?= in_array(
                                                                        $fieldType,
                                                                        ['url', 'email'],
                                                                        true
                                                                    ) ? 'dir="ltr"' : '' ?>
                                                                    <?= $fieldType === 'number'
                                                                        ? 'step="1" inputmode="numeric"'
                                                                        : '' ?>
                                                                    <?= !empty(
                                                                        $field['required']
                                                                    ) ? 'required' : '' ?>
                                                                    <?= $definitionSelected
                                                                        ? ''
                                                                        : 'disabled' ?>
                                                                >
                                                            <?php endif; ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </fieldset>
                                    <?php endforeach; ?>
                                </section>

                                <section
                                    class="provider-editor-panel"
                                    role="tabpanel"
                                    data-provider-editor-panel="secrets"
                                    hidden
                                >
                                    <div
                                        class="provider-tab-empty"
                                        data-provider-secrets-empty
                                        <?= $providerTypeId > 0
                                            ? 'hidden'
                                            : '' ?>
                                    >
                                        ابتدا نوع سرویس‌دهنده را در تب
                                        «مشخصات حساب» انتخاب کنید.
                                    </div>
                                    <?php foreach (
                                        $providerDefinitions as $definition
                                    ): ?>
                                        <?php
                                        $definitionSelected = $providerTypeId
                                            === (int) $definition['id'];
                                        ?>
                                        <fieldset
                                            class="provider-dynamic-fields"
                                            data-provider-secrets="<?= (int) $definition['id'] ?>"
                                            <?= $definitionSelected ? '' : 'hidden' ?>
                                        >
                                            <legend>
                                                اطلاعات محرمانه
                                                <?= admin_h($definition['title']) ?>
                                            </legend>
                                            <?php if (
                                                $definition['secret_fields'] === []
                                            ): ?>
                                                <p class="provider-tab-empty">
                                                    برای این سرویس‌دهنده اطلاعات محرمانه
                                                    جداگانه‌ای تعریف نشده است.
                                                </p>
                                            <?php else: ?>
                                                <div class="provider-dynamic-grid">
                                                    <?php foreach (
                                                        $definition['secret_fields']
                                                        as $secretField
                                                    ): ?>
                                                        <?php
                                                        $secretKey = (string) $secretField['key'];
                                                        $secretStored = $definitionSelected
                                                            && in_array(
                                                                $secretKey,
                                                                $storedSecretKeys,
                                                                true
                                                            );
                                                        ?>
                                                        <label>
                                                            <span>
                                                                <?= admin_h(
                                                                    $secretField['label']
                                                                ) ?>
                                                                <?= !empty(
                                                                    $secretField['required']
                                                                ) && !$secretStored
                                                                    ? ' *'
                                                                    : '' ?>
                                                            </span>
                                                            <span class="provider-secret-input">
                                                                <input
                                                                    type="password"
                                                                    name="secrets[<?= admin_h(
                                                                        $secretKey
                                                                    ) ?>]"
                                                                    autocomplete="new-password"
                                                                    dir="ltr"
                                                                    data-provider-secret-input
                                                                    <?= $definitionSelected
                                                                        ? ''
                                                                        : 'disabled' ?>
                                                                    <?= !empty(
                                                                        $secretField['required']
                                                                    ) && !$secretStored
                                                                        ? 'required'
                                                                        : '' ?>
                                                                    placeholder="<?= $secretStored
                                                                        ? 'برای حفظ مقدار فعلی خالی بگذارید'
                                                                        : 'مقدار محرمانه را وارد کنید' ?>"
                                                                >
                                                                <button
                                                                    class="provider-secret-toggle"
                                                                    type="button"
                                                                    data-provider-secret-toggle
                                                                    aria-label="نمایش مقدار محرمانه"
                                                                >
                                                                    نمایش
                                                                </button>
                                                            </span>
                                                            <?php if ($secretStored): ?>
                                                                <small class="provider-secret-state">
                                                                    مقدار رمز‌شده قبلی حفظ می‌شود، مگر
                                                                    اینکه مقدار جدیدی وارد کنید.
                                                                </small>
                                                            <?php endif; ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </fieldset>
                                    <?php endforeach; ?>
                                </section>

                                <section
                                    class="provider-editor-panel"
                                    role="tabpanel"
                                    data-provider-editor-panel="advanced"
                                    hidden
                                >
                                    <div class="provider-form-grid">
                                        <label>
                                            <span>کد داخلی</span>
                                            <input
                                                name="code"
                                                maxlength="120"
                                                dir="ltr"
                                                value="<?= admin_h(
                                                    $providerForm['code'] ?? ''
                                                ) ?>"
                                                placeholder="در صورت خالی‌بودن خودکار ساخته می‌شود"
                                            >
                                            <small>
                                                شناسه فنی ثابت برای گزارش‌ها و یکپارچه‌سازی‌ها.
                                            </small>
                                        </label>

                                        <label>
                                            <span>اولویت اجرایی</span>
                                            <input
                                                type="number"
                                                name="priority"
                                                min="-1000"
                                                max="1000"
                                                inputmode="numeric"
                                                value="<?= admin_h(
                                                    $providerForm['priority'] ?? 0
                                                ) ?>"
                                            >
                                        </label>

                                        <label>
                                            <span>سقف روزانه</span>
                                            <input
                                                type="number"
                                                name="daily_limit"
                                                min="0"
                                                max="1000000000"
                                                inputmode="numeric"
                                                value="<?= admin_h(
                                                    $providerForm['daily_limit'] ?? ''
                                                ) ?>"
                                                placeholder="اختیاری"
                                            >
                                        </label>

                                        <label>
                                            <span>سقف ماهانه</span>
                                            <input
                                                type="number"
                                                name="monthly_limit"
                                                min="0"
                                                max="1000000000"
                                                inputmode="numeric"
                                                value="<?= admin_h(
                                                    $providerForm['monthly_limit'] ?? ''
                                                ) ?>"
                                                placeholder="اختیاری"
                                            >
                                        </label>
                                    </div>
                                </section>
                            </div>

                            <div class="provider-form-actions communication-form__wide">
                                <button class="admin-button" type="submit">
                                    <?= $isProviderEdit
                                        ? 'ذخیره تغییرات حساب'
                                        : 'ثبت حساب سرویس‌دهنده' ?>
                                </button>
                                <?php if ($isProviderEdit): ?>
                                    <a
                                        class="admin-button admin-button--soft"
                                        href="/admin/communications/settings?section=providers"
                                    >
                                        انصراف
                                    </a>
                                <?php else: ?>
                                    <button
                                        class="admin-button admin-button--soft"
                                        type="reset"
                                        data-provider-reset
                                    >
                                        پاک‌کردن فرم
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </section>
                </section>
            </div>

            <div
                class="provider-test-dialog"
                data-provider-test-dialog
                hidden
            >
                <button
                    class="provider-test-dialog__backdrop"
                    type="button"
                    data-provider-test-close
                    aria-label="بستن پنجره آزمون ارسال"
                ></button>
                <section
                    class="provider-test-dialog__panel"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="provider-test-dialog-title"
                >
                    <header class="provider-test-dialog__head">
                        <div>
                            <h3 id="provider-test-dialog-title">
                                تست ارسال ایمیل
                            </h3>
                            <p class="communication-muted">
                                ارسال مستقیم با حساب
                                <strong data-provider-test-title></strong>
                            </p>
                        </div>
                        <button
                            class="provider-test-dialog__close"
                            type="button"
                            data-provider-test-close
                            aria-label="بستن"
                        >
                            ×
                        </button>
                    </header>

                    <form
                        class="provider-test-form"
                        method="post"
                        data-provider-test-form
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= admin_h($providerCsrf) ?>"
                        >

                        <label>
                            <span>ایمیل مقصد</span>
                            <input
                                type="email"
                                name="recipient"
                                dir="ltr"
                                autocomplete="email"
                                required
                                data-provider-test-recipient
                                placeholder="example@example.com"
                            >
                        </label>

                        <label>
                            <span>موضوع</span>
                            <input
                                name="subject"
                                maxlength="190"
                                required
                                value="آزمون ارسال ایمیل سامانه IPKF"
                            >
                        </label>

                        <label>
                            <span>متن پیام</span>
                            <textarea
                                name="body"
                                maxlength="10000"
                                required
                            >این پیام برای بررسی تنظیمات ارسال ایمیل سامانه IPKF ارسال شده است.</textarea>
                        </label>

                        <div class="provider-test-form__actions">
                            <button
                                class="admin-button"
                                type="submit"
                            >
                                ارسال آزمایشی
                            </button>
                            <button
                                class="admin-button admin-button--soft"
                                type="button"
                                data-provider-test-close
                            >
                                انصراف
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <script>
            (() => {
                const dialog = document.querySelector(
                    '[data-provider-test-dialog]'
                );

                if (!dialog) {
                    return;
                }

                const form = dialog.querySelector(
                    '[data-provider-test-form]'
                );
                const title = dialog.querySelector(
                    '[data-provider-test-title]'
                );
                const recipient = dialog.querySelector(
                    '[data-provider-test-recipient]'
                );

                const close = () => {
                    dialog.hidden = true;
                    document.body.classList.remove(
                        'provider-test-dialog-open'
                    );
                };

                document.querySelectorAll(
                    '[data-provider-test-open]'
                ).forEach((button) => {
                    button.addEventListener('click', () => {
                        const reference =
                            button.dataset.providerReference || '';

                        if (!form || reference === '') {
                            return;
                        }

                        form.action =
                            '/admin/communications/settings/providers/'
                            + encodeURIComponent(reference)
                            + '/test-email';

                        if (title) {
                            title.textContent =
                                button.dataset.providerTitle || '';
                        }

                        dialog.hidden = false;
                        document.body.classList.add(
                            'provider-test-dialog-open'
                        );
                        window.setTimeout(
                            () => recipient?.focus(),
                            0
                        );
                    });
                });

                dialog.querySelectorAll(
                    '[data-provider-test-close]'
                ).forEach((button) => {
                    button.addEventListener('click', close);
                });

                document.addEventListener('keydown', (event) => {
                    if (
                        event.key === 'Escape'
                        && !dialog.hidden
                    ) {
                        close();
                    }
                });
            })();
            </script>

            <script>
            (() => {
                const workspace = document.querySelector(
                    '[data-provider-workspace]'
                );

                if (!workspace) {
                    return;
                }

                const form = workspace.querySelector(
                    '[data-provider-form]'
                );
                const channelSelect = form?.querySelector(
                    '[data-provider-channel]'
                );
                const providerSelect = form?.querySelector(
                    '[data-provider-type]'
                );
                const workspaceTabs = Array.from(
                    workspace.querySelectorAll(
                        '[data-provider-workspace-tab]'
                    )
                );
                const workspacePanels = Array.from(
                    workspace.querySelectorAll(
                        '[data-provider-workspace-panel]'
                    )
                );
                const editorTabs = Array.from(
                    workspace.querySelectorAll(
                        '[data-provider-editor-tab]'
                    )
                );
                const editorPanels = Array.from(
                    workspace.querySelectorAll(
                        '[data-provider-editor-panel]'
                    )
                );

                const activateWorkspace = (name) => {
                    workspaceTabs.forEach((tab) => {
                        const active =
                            tab.dataset.providerWorkspaceTab === name;
                        tab.classList.toggle('is-active', active);
                        tab.setAttribute(
                            'aria-selected',
                            active ? 'true' : 'false'
                        );
                    });

                    workspacePanels.forEach((panel) => {
                        panel.hidden =
                            panel.dataset.providerWorkspacePanel !== name;
                    });
                };

                const activateEditor = (name) => {
                    const target = editorTabs.find(
                        (tab) => tab.dataset.providerEditorTab === name
                    );

                    if (!target || target.disabled) {
                        return;
                    }

                    editorTabs.forEach((tab) => {
                        const active =
                            tab.dataset.providerEditorTab === name;
                        tab.classList.toggle('is-active', active);
                        tab.setAttribute(
                            'aria-selected',
                            active ? 'true' : 'false'
                        );
                    });

                    editorPanels.forEach((panel) => {
                        panel.hidden =
                            panel.dataset.providerEditorPanel !== name;
                    });
                };

                workspaceTabs.forEach((tab) => {
                    tab.addEventListener('click', () => {
                        activateWorkspace(
                            tab.dataset.providerWorkspaceTab
                        );
                    });
                });

                workspace.querySelectorAll(
                    '[data-provider-open-editor]'
                ).forEach((button) => {
                    button.addEventListener('click', () => {
                        activateWorkspace('editor');
                        activateEditor('account');
                    });
                });

                editorTabs.forEach((tab) => {
                    tab.addEventListener('click', () => {
                        activateEditor(
                            tab.dataset.providerEditorTab
                        );
                    });
                });

                if (!form || !channelSelect || !providerSelect) {
                    activateWorkspace(
                        workspace.dataset.initialView || 'accounts'
                    );
                    return;
                }

                const connectionEmpty = form.querySelector(
                    '[data-provider-connection-empty]'
                );
                const secretsEmpty = form.querySelector(
                    '[data-provider-secrets-empty]'
                );

                const updateProviderOptions = () => {
                    const channelCode = channelSelect.value;
                    const locked =
                        providerSelect.dataset.providerLocked
                        === 'true';

                    providerSelect.querySelectorAll(
                        'option[data-provider-channel-code]'
                    ).forEach((option) => {
                        const matches =
                            option.dataset.providerChannelCode
                            === channelCode;

                        option.hidden = !matches;
                        option.disabled = !matches;
                    });

                    const selectedOption =
                        providerSelect.selectedOptions[0];

                    if (
                        !locked
                        && selectedOption?.dataset
                            .providerChannelCode !== channelCode
                    ) {
                        providerSelect.value = '';
                    }

                    providerSelect.disabled =
                        locked || channelCode === '';
                };

                const updateProviderFields = () => {
                    const selectedId = providerSelect.value;
                    const hasProvider = selectedId !== '';

                    form.querySelectorAll(
                        '[data-provider-fields], [data-provider-secrets]'
                    ).forEach((group) => {
                        const targetId =
                            group.dataset.providerFields
                            || group.dataset.providerSecrets;
                        const active = targetId === selectedId;

                        group.hidden = !active;

                        group.querySelectorAll(
                            'input, select, textarea'
                        ).forEach((field) => {
                            field.disabled = !active;
                        });
                    });

                    if (connectionEmpty) {
                        connectionEmpty.hidden = hasProvider;
                    }

                    if (secretsEmpty) {
                        secretsEmpty.hidden = hasProvider;
                    }

                    editorTabs.forEach((tab) => {
                        if (!['connection', 'secrets'].includes(
                            tab.dataset.providerEditorTab
                        )) {
                            return;
                        }

                        tab.disabled = !hasProvider;
                        tab.setAttribute(
                            'aria-disabled',
                            hasProvider ? 'false' : 'true'
                        );
                    });

                    const activeEditorTab = editorTabs.find(
                        (tab) => tab.classList.contains('is-active')
                    );

                    if (
                        !hasProvider
                        && ['connection', 'secrets'].includes(
                            activeEditorTab?.dataset.providerEditorTab
                        )
                    ) {
                        activateEditor('account');
                    }
                };

                channelSelect.addEventListener(
                    'change',
                    () => {
                        if (
                            providerSelect.dataset.providerLocked
                            !== 'true'
                        ) {
                            providerSelect.value = '';
                        }

                        updateProviderOptions();
                        updateProviderFields();
                    }
                );

                providerSelect.addEventListener(
                    'change',
                    updateProviderFields
                );

                const enabledInput = form.querySelector(
                    '[data-provider-enabled]'
                );
                const enabledLabel = form.querySelector(
                    '[data-provider-enabled-label]'
                );

                const updateEnabledLabel = () => {
                    if (!enabledInput || !enabledLabel) {
                        return;
                    }

                    enabledLabel.textContent = enabledInput.checked
                        ? 'فعال'
                        : 'غیرفعال';
                };

                enabledInput?.addEventListener(
                    'change',
                    updateEnabledLabel
                );

                form.querySelectorAll(
                    '[data-provider-secret-toggle]'
                ).forEach((button) => {
                    button.addEventListener('click', () => {
                        const input = button.parentElement?.querySelector(
                            '[data-provider-secret-input]'
                        );

                        if (!input) {
                            return;
                        }

                        const visible = input.type === 'text';
                        input.type = visible ? 'password' : 'text';
                        button.textContent = visible
                            ? 'نمایش'
                            : 'پنهان';
                        button.setAttribute(
                            'aria-label',
                            visible
                                ? 'نمایش مقدار محرمانه'
                                : 'پنهان‌کردن مقدار محرمانه'
                        );
                    });
                });

                form.addEventListener('reset', () => {
                    window.setTimeout(() => {
                        updateProviderOptions();
                        updateProviderFields();
                        updateEnabledLabel();
                        activateEditor('account');
                    }, 0);
                });

                updateProviderOptions();
                updateProviderFields();
                updateEnabledLabel();
                activateWorkspace(
                    workspace.dataset.initialView || 'accounts'
                );
            })();
            </script>

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
