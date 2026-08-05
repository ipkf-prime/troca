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
$providerDefaultManagement = is_array(
    $page['provider_default_management'] ?? null
) ? $page['provider_default_management'] : [];
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
$deliveryReport = is_array(
    $page['delivery_report'] ?? null
) ? $page['delivery_report'] : [];
$deliveries = is_array(
    $deliveryReport['items'] ?? null
) ? $deliveryReport['items'] : [];
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
    'provider_defaults_saved' => ['success', 'پیش‌فرض سرویس‌دهنده‌ها با موفقیت ذخیره شد.'],
    'provider_defaults_input_invalid' => ['error', 'اطلاعات پیش‌فرض سرویس‌دهنده‌ها معتبر نیست.'],
    'provider_defaults_primary_required' => ['error', 'برای انتخاب سرویس جایگزین، ابتدا سرویس اصلی را انتخاب کنید.'],
    'provider_defaults_duplicate' => ['error', 'سرویس اصلی و جایگزین نمی‌توانند یکسان باشند.'],
    'provider_defaults_instance_invalid' => ['error', 'حساب انتخاب‌شده فعال یا معتبر نیست.'],
    'provider_defaults_channel_mismatch' => ['error', 'حساب انتخاب‌شده با کانال مربوط سازگار نیست.'],
    'provider_defaults_save_failed' => ['error', 'ذخیره پیش‌فرض سرویس‌دهنده‌ها انجام نشد.'],
    'provider_test_sent' => ['success', 'ایمیل آزمایشی با موفقیت به سرور ایمیل تحویل شد.'],
    'provider_test_email_sent' => ['success', 'ایمیل آزمایشی با موفقیت به سرور ایمیل تحویل شد.'],
    'provider_test_sms_sent' => ['success', 'پیامک آزمایشی با موفقیت به کاوه‌نگار تحویل شد.'],
    'provider_test_bale_sent' => ['success', 'پیام آزمایشی با موفقیت در بله ارسال شد.'],
    'provider_test_unsupported' => ['error', 'آزمون ارسال برای این سرویس‌دهنده هنوز پشتیبانی نمی‌شود.'],
    'provider_test_mobile_invalid' => ['error', 'شماره تلفن همراه مقصد معتبر نیست.'],
    'provider_test_chat_id_invalid' => ['error', 'شناسه گفت‌وگوی بله معتبر نیست.'],
    'provider_test_api_key_missing' => ['error', 'کلید API پیامک در اطلاعات محرمانه حساب ثبت نشده است.'],
    'provider_test_bot_token_missing' => ['error', 'توکن بات بله در اطلاعات محرمانه حساب ثبت نشده است.'],
    'provider_test_api_endpoint_invalid' => ['error', 'نشانی API سرویس‌دهنده معتبر یا مجاز نیست.'],
    'provider_test_api_connection_failed' => ['error', 'اتصال به API سرویس‌دهنده برقرار نشد.'],
    'provider_test_api_timeout' => ['error', 'مهلت اتصال یا پاسخ API سرویس‌دهنده به پایان رسید.'],
    'provider_test_api_response_invalid' => ['error', 'پاسخ API سرویس‌دهنده معتبر نبود.'],
    'provider_test_api_rejected' => ['error', 'سرویس‌دهنده درخواست ارسال آزمایشی را نپذیرفت.'],
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
                                        $providerTypeCode = (string) (
                                            $instance[
                                                'provider_type_code'
                                            ] ?? ''
                                        );
                                        $providerTestKind = '';

                                        if (
                                            $channelCode === 'email'
                                            && (string) (
                                                $instance[
                                                    'driver_code'
                                                ] ?? ''
                                            ) === 'smtp'
                                        ) {
                                            $providerTestKind = 'email';
                                        } elseif (
                                            $providerTypeCode === 'kavenegar'
                                        ) {
                                            $providerTestKind = 'sms';
                                        } elseif (
                                            $providerTypeCode === 'bale_bot'
                                        ) {
                                            $providerTestKind = 'bale';
                                        }
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
                                                        $providerTestKind !== ''
                                                    ): ?>
                                                        <button
                                                            class="admin-button admin-button--soft admin-button--compact"
                                                            type="button"
                                                            data-provider-test-open
                                                            data-provider-test-kind="<?= admin_h(
                                                                $providerTestKind
                                                            ) ?>"
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
                            <h3
                                id="provider-test-dialog-title"
                                data-provider-test-heading
                            >
                                تست ارسال
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
                            <span data-provider-test-recipient-label>
                                مقصد
                            </span>
                            <input
                                type="text"
                                name="recipient"
                                dir="ltr"
                                required
                                data-provider-test-recipient
                            >
                        </label>

                        <label data-provider-test-subject-row>
                            <span>موضوع</span>
                            <input
                                name="subject"
                                maxlength="190"
                                data-provider-test-subject
                            >
                        </label>

                        <label>
                            <span>متن پیام</span>
                            <textarea
                                name="body"
                                maxlength="10000"
                                required
                                data-provider-test-body
                            ></textarea>
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
                const heading = dialog.querySelector(
                    '[data-provider-test-heading]'
                );
                const title = dialog.querySelector(
                    '[data-provider-test-title]'
                );
                const recipient = dialog.querySelector(
                    '[data-provider-test-recipient]'
                );
                const recipientLabel = dialog.querySelector(
                    '[data-provider-test-recipient-label]'
                );
                const subjectRow = dialog.querySelector(
                    '[data-provider-test-subject-row]'
                );
                const subject = dialog.querySelector(
                    '[data-provider-test-subject]'
                );
                const body = dialog.querySelector(
                    '[data-provider-test-body]'
                );

                const modes = {
                    email: {
                        heading: 'تست ارسال ایمیل',
                        recipientLabel: 'ایمیل مقصد',
                        recipientType: 'email',
                        inputMode: 'email',
                        autocomplete: 'email',
                        placeholder: 'example@example.com',
                        subject: 'آزمون ارسال ایمیل سامانه IPKF',
                        body:
                            'این پیام برای بررسی تنظیمات '
                            + 'ارسال ایمیل سامانه IPKF '
                            + 'ارسال شده است.',
                        showSubject: true,
                        endpoint: 'test-send',
                    },
                    sms: {
                        heading: 'تست ارسال پیامک',
                        recipientLabel: 'شماره تلفن همراه مقصد',
                        recipientType: 'tel',
                        inputMode: 'tel',
                        autocomplete: 'tel',
                        placeholder: '09123456789',
                        subject: '',
                        body:
                            'پیامک آزمایشی سامانه IPKF؛ '
                            + 'تنظیمات ارسال با موفقیت '
                            + 'در حال بررسی است.',
                        showSubject: false,
                        endpoint: 'test-send',
                    },
                    bale: {
                        heading: 'تست ارسال پیام در بله',
                        recipientLabel:
                            'شناسه گفت‌وگو (Chat ID)',
                        recipientType: 'text',
                        inputMode: 'numeric',
                        autocomplete: 'off',
                        placeholder: '123456789',
                        subject: '',
                        body:
                            'این پیام برای بررسی تنظیمات '
                            + 'ربات بله سامانه IPKF '
                            + 'ارسال شده است.',
                        showSubject: false,
                        endpoint: 'test-send',
                    },
                };

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
                        const kind =
                            button.dataset.providerTestKind || '';
                        const mode = modes[kind];

                        if (
                            !form
                            || reference === ''
                            || !mode
                        ) {
                            return;
                        }

                        form.action =
                            '/admin/communications/settings/providers/'
                            + encodeURIComponent(reference)
                            + '/'
                            + mode.endpoint;

                        if (heading) {
                            heading.textContent = mode.heading;
                        }

                        if (title) {
                            title.textContent =
                                button.dataset.providerTitle || '';
                        }

                        if (recipientLabel) {
                            recipientLabel.textContent =
                                mode.recipientLabel;
                        }

                        if (recipient) {
                            recipient.type = mode.recipientType;
                            recipient.inputMode = mode.inputMode;
                            recipient.autocomplete =
                                mode.autocomplete;
                            recipient.placeholder =
                                mode.placeholder;
                            recipient.value = '';
                        }

                        if (subjectRow && subject) {
                            subjectRow.hidden =
                                !mode.showSubject;
                            subject.required =
                                mode.showSubject;
                            subject.value = mode.subject;
                        }

                        if (body) {
                            body.value = mode.body;
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
            <?php
            $defaultChannels = is_array(
                $providerDefaultManagement['channels'] ?? null
            ) ? $providerDefaultManagement['channels'] : [];
            $defaultCsrf = (new \IPKF\Security\Csrf())->token();
            ?>

            <form
                class="provider-default-form"
                method="post"
                action="/admin/communications/settings/defaults/save"
                data-provider-default-form
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= admin_h($defaultCsrf) ?>"
                >

                <section class="provider-default-intro">
                    <div>
                        <h3>مسیر پیش‌فرض ارسال</h3>
                        <p class="communication-muted">
                            برای هر کانال، حساب اصلی و حساب جایگزین را
                            تعیین کنید. درگاه ارسال از همین ترتیب استفاده
                            خواهد کرد.
                        </p>
                    </div>
                    <div class="provider-default-scope">
                        <span>دامنه</span>
                        <strong>کل سامانه</strong>
                        <small>هدف عمومی</small>
                    </div>
                </section>

                <div class="provider-default-grid">
                    <?php foreach ($defaultChannels as $channel): ?>
                        <?php
                        $code = (string) ($channel['code'] ?? '');
                        $title = (string) (
                            $channel['title'] ?? $code
                        );
                        $instances = is_array(
                            $channel['instances'] ?? null
                        ) ? $channel['instances'] : [];
                        $selection = is_array(
                            $channel['selection'] ?? null
                        ) ? $channel['selection'] : [];
                        $resolved = is_array(
                            $channel['resolved'] ?? null
                        ) ? $channel['resolved'] : [];
                        $primary = (string) (
                            $selection['primary_reference'] ?? ''
                        );
                        $fallback = (string) (
                            $selection['fallback_reference'] ?? ''
                        );
                        ?>

                        <section
                            class="provider-default-card"
                            data-provider-default-channel
                        >
                            <header class="provider-default-card__head">
                                <div>
                                    <h3><?= admin_h($title) ?></h3>
                                    <p class="communication-muted">
                                        <?= admin_h(
                                            \App\Support\AdminFormat::digits(
                                                count($instances)
                                            )
                                        ) ?>
                                        حساب فعال
                                    </p>
                                </div>
                                <span><?= admin_h($code) ?></span>
                            </header>

                            <?php if ($instances === []): ?>
                                <div class="provider-default-empty">
                                    حساب فعالی برای این کانال وجود ندارد.
                                </div>
                            <?php else: ?>
                                <label>
                                    <span>حساب اصلی</span>
                                    <select
                                        name="defaults[<?= admin_h(
                                            $code
                                        ) ?>][primary_reference]"
                                        data-provider-default-primary
                                    >
                                        <option value="">
                                            انتخاب نشده
                                        </option>
                                        <?php foreach (
                                            $instances as $instance
                                        ): ?>
                                            <?php
                                            $reference = (string) (
                                                $instance[
                                                    'public_reference'
                                                ] ?? ''
                                            );
                                            ?>
                                            <option
                                                value="<?= admin_h(
                                                    $reference
                                                ) ?>"
                                                <?= $reference === $primary
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= admin_h(
                                                    $instance['title']
                                                ) ?>
                                                —
                                                <?= admin_h(
                                                    $instance[
                                                        'provider_type_title'
                                                    ]
                                                ) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label>
                                    <span>حساب جایگزین</span>
                                    <select
                                        name="defaults[<?= admin_h(
                                            $code
                                        ) ?>][fallback_reference]"
                                        data-provider-default-fallback
                                    >
                                        <option value="">
                                            بدون جایگزین
                                        </option>
                                        <?php foreach (
                                            $instances as $instance
                                        ): ?>
                                            <?php
                                            $reference = (string) (
                                                $instance[
                                                    'public_reference'
                                                ] ?? ''
                                            );
                                            ?>
                                            <option
                                                value="<?= admin_h(
                                                    $reference
                                                ) ?>"
                                                <?= $reference === $fallback
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= admin_h(
                                                    $instance['title']
                                                ) ?>
                                                —
                                                <?= admin_h(
                                                    $instance[
                                                        'provider_type_title'
                                                    ]
                                                ) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>

                            <div class="provider-default-preview">
                                <span>ترتیب فعلی Resolver</span>
                                <?php if ($resolved === []): ?>
                                    <strong>مسیر تعیین نشده</strong>
                                <?php else: ?>
                                    <ol>
                                        <?php foreach (
                                            $resolved as $candidate
                                        ): ?>
                                            <li>
                                                <strong><?= admin_h(
                                                    $candidate['title']
                                                ) ?></strong>
                                                <small><?= admin_h(
                                                    $candidate[
                                                        'provider_type_title'
                                                    ]
                                                ) ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <div class="provider-default-actions">
                    <button class="admin-button" type="submit">
                        ذخیره پیش‌فرض‌ها
                    </button>
                    <span class="communication-muted">
                        تغییرات در ارسال‌های بعدی اعمال می‌شود.
                    </span>
                </div>
            </form>

            <script>
            (() => {
                document.querySelectorAll(
                    '[data-provider-default-channel]'
                ).forEach((card) => {
                    const primary = card.querySelector(
                        '[data-provider-default-primary]'
                    );
                    const fallback = card.querySelector(
                        '[data-provider-default-fallback]'
                    );

                    const sync = () => {
                        if (!primary || !fallback) {
                            return;
                        }

                        Array.from(fallback.options).forEach(
                            (option) => {
                                option.disabled =
                                    option.value !== ''
                                    && option.value === primary.value;
                            }
                        );

                        if (fallback.value === primary.value) {
                            fallback.value = '';
                        }

                        fallback.disabled = primary.value === '';
                    };

                    primary?.addEventListener('change', sync);
                    sync();
                });
            })();
            </script>

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
            <?php
            $reportFilters = is_array(
                $deliveryReport['filters'] ?? null
            ) ? $deliveryReport['filters'] : [];
            $reportSummary = is_array(
                $deliveryReport['summary'] ?? null
            ) ? $deliveryReport['summary'] : [];
            $reportProviders = is_array(
                $deliveryReport['providers'] ?? null
            ) ? $deliveryReport['providers'] : [];
            $reportPage = max(1, (int) ($deliveryReport['page'] ?? 1));
            $reportPages = max(1, (int) ($deliveryReport['pages'] ?? 1));
            $reportTotal = max(0, (int) ($deliveryReport['total'] ?? 0));
            $reportPerPage = (int) ($deliveryReport['per_page'] ?? 20);

            $reportQuery = static function (
                array $overrides = []
            ) use ($reportFilters): string {
                $values = [
                    'section' => 'reports',
                    'q' => (string) ($reportFilters['q'] ?? ''),
                    'channel' => (string) ($reportFilters['channel'] ?? ''),
                    'report_status' => (string) ($reportFilters['status'] ?? ''),
                    'provider' => (string) ($reportFilters['provider'] ?? ''),
                    'from' => (string) ($reportFilters['from_input'] ?? ''),
                    'to' => (string) ($reportFilters['to_input'] ?? ''),
                    'sort' => (string) (
                        $reportFilters['sort'] ?? 'created_desc'
                    ),
                    'per_page' => (int) (
                        $reportFilters['per_page'] ?? 20
                    ),
                    'page' => (int) ($reportFilters['page'] ?? 1),
                ];

                foreach ($overrides as $key => $value) {
                    $values[$key] = $value;
                }

                $values = array_filter(
                    $values,
                    static fn (mixed $value): bool =>
                        $value !== '' && $value !== null
                );

                return '/admin/communications/settings?'
                    . http_build_query(
                        $values,
                        '',
                        '&',
                        PHP_QUERY_RFC3986
                    );
            };

            $reportDate = static function (mixed $value): string {
                $date = \App\Support\AdminFormat::jalaliDateTime($value);

                return $date !== '' ? $date : '—';
            };

            $reportValue = static function (mixed $value): string {
                if (is_bool($value)) {
                    return $value ? 'بله' : 'خیر';
                }

                if ($value === null || $value === '') {
                    return '—';
                }

                if (is_array($value)) {
                    return json_encode(
                        $value,
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ) ?: '—';
                }

                return (string) $value;
            };
            ?>

            <section
                class="notification-delivery-report"
                data-notification-delivery-report
            >
                <!-- notification-delivery-report-v061 -->
                <header class="notification-report-intro">
                    <div>
                        <h3>گزارش یکپارچه ارسال‌ها</h3>
                        <p class="communication-muted">
                            وضعیت ارسال، سرویس‌دهنده، مقصد، پاسخ فنی،
                            تلاش‌های مجدد و مسیر جایگزین هر اعلان را
                            در یک گزارش مشاهده کنید.
                        </p>
                    </div>
                    <span class="notification-report-intro__count">
                        <?= admin_h(
                            \App\Support\AdminFormat::digits(
                                $reportTotal
                            )
                        ) ?>
                        رکورد
                    </span>
                </header>

                <div class="notification-report-summary">
                    <?php foreach ([
                        ['title' => 'کل نتایج', 'key' => 'total'],
                        ['title' => 'موفق', 'key' => 'success'],
                        ['title' => 'ناموفق', 'key' => 'failed'],
                        ['title' => 'در انتظار', 'key' => 'pending'],
                        ['title' => 'استفاده از جایگزین', 'key' => 'fallback'],
                    ] as $summaryCard): ?>
                        <article class="notification-report-summary__card">
                            <span><?= admin_h(
                                $summaryCard['title']
                            ) ?></span>
                            <strong><?= admin_h(
                                \App\Support\AdminFormat::digits(
                                    (int) (
                                        $reportSummary[
                                            $summaryCard['key']
                                        ] ?? 0
                                    )
                                )
                            ) ?></strong>
                        </article>
                    <?php endforeach; ?>
                </div>

                <form
                    class="notification-report-filters"
                    method="get"
                    action="/admin/communications/settings"
                >
                    <input
                        type="hidden"
                        name="section"
                        value="reports"
                    >

                    <label class="notification-report-filters__search">
                        <span>جست‌وجو</span>
                        <input
                            type="search"
                            name="q"
                            value="<?= admin_h(
                                $reportFilters['q'] ?? ''
                            ) ?>"
                            placeholder="عنوان، کاربر، مقصد، شناسه پیام یا سرویس‌دهنده"
                        >
                    </label>

                    <label>
                        <span>کانال</span>
                        <select name="channel">
                            <option value="">همه کانال‌ها</option>
                            <?php foreach ([
                                'in_app',
                                'email',
                                'sms',
                                'messenger',
                            ] as $channelCode): ?>
                                <option
                                    value="<?= admin_h($channelCode) ?>"
                                    <?= (
                                        $reportFilters['channel']
                                        ?? ''
                                    ) === $channelCode
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= admin_h(
                                        $channelLabels[$channelCode]
                                        ?? $channelCode
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>وضعیت</span>
                        <select name="report_status">
                            <option value="">همه وضعیت‌ها</option>
                            <?php foreach (
                                $deliveryStatusLabels
                                as $statusCode => $statusTitle
                            ): ?>
                                <option
                                    value="<?= admin_h($statusCode) ?>"
                                    <?= (
                                        $reportFilters['status']
                                        ?? ''
                                    ) === $statusCode
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= admin_h($statusTitle) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>سرویس‌دهنده</span>
                        <select name="provider">
                            <option value="">
                                همه سرویس‌دهنده‌ها
                            </option>
                            <?php foreach (
                                $reportProviders as $provider
                            ): ?>
                                <?php
                                $providerCode = (string) (
                                    $provider['code'] ?? ''
                                );
                                ?>
                                <option
                                    value="<?= admin_h($providerCode) ?>"
                                    <?= (
                                        $reportFilters['provider']
                                        ?? ''
                                    ) === $providerCode
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= admin_h(
                                        $provider['title']
                                        ?? $providerCode
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>از تاریخ</span>
                        <input
                            name="from"
                            inputmode="numeric"
                            value="<?= admin_h(
                                $reportFilters['from_input']
                                ?? ''
                            ) ?>"
                            placeholder="۱۴۰۵/۰۵/۱۴"
                        >
                    </label>

                    <label>
                        <span>تا تاریخ</span>
                        <input
                            name="to"
                            inputmode="numeric"
                            value="<?= admin_h(
                                $reportFilters['to_input']
                                ?? ''
                            ) ?>"
                            placeholder="۱۴۰۵/۰۵/۱۴"
                        >
                    </label>

                    <label>
                        <span>مرتب‌سازی</span>
                        <select name="sort">
                            <?php foreach ([
                                'created_desc' => 'جدیدترین',
                                'created_asc' => 'قدیمی‌ترین',
                                'status_asc' => 'وضعیت صعودی',
                                'status_desc' => 'وضعیت نزولی',
                                'channel_asc' => 'کانال',
                                'attempts_desc' => 'بیشترین تلاش',
                                'attempts_asc' => 'کمترین تلاش',
                            ] as $sortCode => $sortTitle): ?>
                                <option
                                    value="<?= admin_h($sortCode) ?>"
                                    <?= (
                                        $reportFilters['sort']
                                        ?? 'created_desc'
                                    ) === $sortCode
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= admin_h($sortTitle) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>تعداد در صفحه</span>
                        <select name="per_page">
                            <?php foreach ([20, 50, 100] as $size): ?>
                                <option
                                    value="<?= admin_h($size) ?>"
                                    <?= $reportPerPage === $size
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= admin_h(
                                        \App\Support\AdminFormat::digits(
                                            $size
                                        )
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="notification-report-filters__actions">
                        <button
                            class="admin-button"
                            type="submit"
                        >
                            اعمال فیلتر
                        </button>
                        <a
                            class="admin-button admin-button--soft"
                            href="/admin/communications/settings?section=reports"
                        >
                            پاک‌کردن
                        </a>
                    </div>
                </form>

                <?php if ($deliveries === []): ?>
                    <p class="admin-empty-state">
                        گزارشی مطابق فیلترهای انتخاب‌شده پیدا نشد.
                    </p>
                <?php else: ?>
                    <div class="communication-table-wrap">
                        <table class="communication-table notification-report-table">
                            <thead>
                                <tr>
                                    <th>عنوان و شناسه</th>
                                    <th>مقصد / کاربر</th>
                                    <th>کانال</th>
                                    <th>سرویس‌دهنده</th>
                                    <th>وضعیت</th>
                                    <th>تلاش</th>
                                    <th>آخرین فعالیت</th>
                                    <th>جزئیات</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($deliveries as $item): ?>
                                <?php
                                $reference = (string) (
                                    $item['public_reference'] ?? ''
                                );
                                $channelCode = (string) (
                                    $item['channel_code'] ?? ''
                                );
                                $statusCode = (string) (
                                    $item['status_code'] ?? ''
                                );
                                $providerTitle = trim(
                                    (string) (
                                        $item['provider_title'] ?? ''
                                    )
                                );
                                $providerTypeTitle = trim(
                                    (string) (
                                        $item['provider_type_title']
                                        ?? $item[
                                            'resolved_provider_type_code'
                                        ]
                                        ?? ''
                                    )
                                );
                                $destination = trim(
                                    (string) (
                                        $item['destination_snapshot']
                                        ?? ''
                                    )
                                );
                                $userTitle = trim(
                                    (string) (
                                        $item['user_title'] ?? ''
                                    )
                                );
                                $activityDate =
                                    $item['delivered_at']
                                    ?? $item['sent_at']
                                    ?? $item['failed_at']
                                    ?? $item['last_attempt_at']
                                    ?? $item['created_at']
                                    ?? '';
                                $attempts = is_array(
                                    $item['attempts'] ?? null
                                ) ? $item['attempts'] : [];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= admin_h(
                                            $item['title']
                                            ?? 'بدون عنوان'
                                        ) ?></strong>
                                        <small
                                            class="notification-report-reference"
                                            dir="ltr"
                                        >
                                            <?= admin_h($reference) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong
                                            class="notification-report-destination"
                                            dir="ltr"
                                        >
                                            <?= admin_h(
                                                $destination !== ''
                                                    ? $destination
                                                    : '—'
                                            ) ?>
                                        </strong>
                                        <?php if (
                                            $userTitle !== ''
                                            && $userTitle !== $destination
                                        ): ?>
                                            <small><?= admin_h(
                                                \App\Support\AdminFormat
                                                    ::digits($userTitle)
                                            ) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= admin_h(
                                        $channelLabels[$channelCode]
                                        ?? $channelCode
                                    ) ?></td>
                                    <td>
                                        <strong><?= admin_h(
                                            $providerTitle !== ''
                                                ? $providerTitle
                                                : 'انتخاب خودکار'
                                        ) ?></strong>
                                        <small><?= admin_h(
                                            $providerTypeTitle !== ''
                                                ? $providerTypeTitle
                                                : '—'
                                        ) ?></small>
                                    </td>
                                    <td>
                                        <span
                                            class="notification-report-status notification-report-status--<?= admin_h(
                                                $statusCode
                                            ) ?>"
                                        >
                                            <?= admin_h(
                                                $deliveryStatusLabels[
                                                    $statusCode
                                                ] ?? $statusCode
                                            ) ?>
                                        </span>
                                        <?php if (
                                            !empty(
                                                $item['fallback_used']
                                            )
                                        ): ?>
                                            <small
                                                class="notification-report-fallback"
                                            >
                                                مسیر جایگزین
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= admin_h(
                                        \App\Support\AdminFormat::digits(
                                            (int) (
                                                $item['attempt_count']
                                                ?? 0
                                            )
                                        )
                                    ) ?></td>
                                    <td><?= admin_h(
                                        $reportDate($activityDate)
                                    ) ?></td>
                                    <td>
                                        <button
                                            class="admin-button admin-button--soft admin-button--compact"
                                            type="button"
                                            data-notification-report-toggle="<?= admin_h(
                                                $reference
                                            ) ?>"
                                            aria-expanded="false"
                                        >
                                            مشاهده
                                        </button>
                                    </td>
                                </tr>
                                <tr
                                    class="notification-report-detail-row"
                                    data-notification-report-detail="<?= admin_h(
                                        $reference
                                    ) ?>"
                                    hidden
                                >
                                    <td colspan="8">
                                        <section class="notification-report-detail">
                                            <div class="notification-report-detail__grid">
                                                <?php foreach ([
                                                    'شناسه اعلان' => $item[
                                                        'notification_reference'
                                                    ] ?? '',
                                                    'شناسه درخواست' => $item[
                                                        'request_reference'
                                                    ] ?? '',
                                                    'شناسه پیام سرویس‌دهنده' => $item[
                                                        'provider_message_reference'
                                                    ] ?? '',
                                                    'هدف ارسال' => $item[
                                                        'purpose_code'
                                                    ] ?? 'general',
                                                    'پاسخ آخر' => $item[
                                                        'last_response_code'
                                                    ] ?? '',
                                                    'حداکثر تلاش' => $item[
                                                        'max_attempts'
                                                    ] ?? 0,
                                                    'زمان ایجاد' => $reportDate(
                                                        $item['created_at']
                                                        ?? ''
                                                    ),
                                                    'زمان ارسال' => $reportDate(
                                                        $item['sent_at']
                                                        ?? ''
                                                    ),
                                                    'زمان تحویل' => $reportDate(
                                                        $item['delivered_at']
                                                        ?? ''
                                                    ),
                                                    'زمان شکست' => $reportDate(
                                                        $item['failed_at']
                                                        ?? ''
                                                    ),
                                                ] as $label => $value): ?>
                                                    <div>
                                                        <span><?= admin_h(
                                                            $label
                                                        ) ?></span>
                                                        <strong><?= admin_h(
                                                            \App\Support\AdminFormat
                                                                ::digits(
                                                                    $reportValue(
                                                                        $value
                                                                    )
                                                                )
                                                        ) ?></strong>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="notification-report-message">
                                                <span>متن پیام</span>
                                                <p><?= nl2br(admin_h(
                                                    $item['body'] ?? ''
                                                )) ?></p>
                                            </div>

                                            <?php if (
                                                trim((string) (
                                                    $item['last_error']
                                                    ?? ''
                                                )) !== ''
                                            ): ?>
                                                <div class="notification-report-error">
                                                    <span>آخرین خطا</span>
                                                    <code><?= admin_h(
                                                        $item['last_error']
                                                    ) ?></code>
                                                </div>
                                            <?php endif; ?>

                                            <section class="notification-report-attempts">
                                                <header>
                                                    <h4>تلاش‌های ارسال</h4>
                                                    <span>
                                                        <?= admin_h(
                                                            \App\Support\AdminFormat
                                                                ::digits(
                                                                    count(
                                                                        $attempts
                                                                    )
                                                                )
                                                        ) ?>
                                                        تلاش ثبت‌شده
                                                    </span>
                                                </header>

                                                <?php if ($attempts === []): ?>
                                                    <p class="admin-empty-state admin-empty-state--compact">
                                                        برای این ارسال تلاش فنی ثبت نشده است.
                                                    </p>
                                                <?php else: ?>
                                                    <div class="communication-table-wrap">
                                                        <table class="communication-table notification-report-attempt-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>شماره</th>
                                                                    <th>سرویس‌دهنده</th>
                                                                    <th>وضعیت</th>
                                                                    <th>پاسخ</th>
                                                                    <th>مدت</th>
                                                                    <th>زمان</th>
                                                                    <th>اطلاعات فنی امن</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                            <?php foreach (
                                                                $attempts
                                                                as $attempt
                                                            ): ?>
                                                                <?php
                                                                $metadata =
                                                                    is_array(
                                                                        $attempt[
                                                                            'metadata'
                                                                        ] ?? null
                                                                    )
                                                                        ? $attempt[
                                                                            'metadata'
                                                                        ]
                                                                        : [];
                                                                ?>
                                                                <tr>
                                                                    <td><?= admin_h(
                                                                        \App\Support\AdminFormat
                                                                            ::digits(
                                                                                $attempt[
                                                                                    'attempt_number'
                                                                                ] ?? 0
                                                                            )
                                                                    ) ?></td>
                                                                    <td>
                                                                        <strong><?= admin_h(
                                                                            $attempt[
                                                                                'provider_title'
                                                                            ]
                                                                            ?? $attempt[
                                                                                'provider_type_title'
                                                                            ]
                                                                            ?? $attempt[
                                                                                'provider_type_code'
                                                                            ]
                                                                            ?? '—'
                                                                        ) ?></strong>
                                                                        <small dir="ltr"><?= admin_h(
                                                                            $attempt[
                                                                                'provider_message_reference'
                                                                            ] ?? ''
                                                                        ) ?></small>
                                                                    </td>
                                                                    <td><?= admin_h(
                                                                        $deliveryStatusLabels[
                                                                            $attempt[
                                                                                'status_code'
                                                                            ] ?? ''
                                                                        ]
                                                                        ?? $attempt[
                                                                            'status_code'
                                                                        ]
                                                                        ?? ''
                                                                    ) ?></td>
                                                                    <td>
                                                                        <strong dir="ltr"><?= admin_h(
                                                                            $attempt[
                                                                                'provider_response_code'
                                                                            ] ?? '—'
                                                                        ) ?></strong>
                                                                        <small><?= admin_h(
                                                                            $attempt[
                                                                                'provider_response_message'
                                                                            ] ?? ''
                                                                        ) ?></small>
                                                                    </td>
                                                                    <td>
                                                                        <?= admin_h(
                                                                            \App\Support\AdminFormat
                                                                                ::digits(
                                                                                    $attempt[
                                                                                        'duration_ms'
                                                                                    ] ?? 0
                                                                                )
                                                                        ) ?>
                                                                        ms
                                                                    </td>
                                                                    <td><?= admin_h(
                                                                        $reportDate(
                                                                            $attempt[
                                                                                'attempted_at'
                                                                            ] ?? ''
                                                                        )
                                                                    ) ?></td>
                                                                    <td>
                                                                        <?php if (
                                                                            $metadata === []
                                                                        ): ?>
                                                                            —
                                                                        <?php else: ?>
                                                                            <dl class="notification-report-metadata">
                                                                                <?php foreach (
                                                                                    $metadata
                                                                                    as $key => $value
                                                                                ): ?>
                                                                                    <div>
                                                                                        <dt><?= admin_h(
                                                                                            (string) $key
                                                                                        ) ?></dt>
                                                                                        <dd><?= admin_h(
                                                                                            \App\Support\AdminFormat
                                                                                                ::digits(
                                                                                                    $reportValue(
                                                                                                        $value
                                                                                                    )
                                                                                                )
                                                                                        ) ?></dd>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            </dl>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            </section>
                                        </section>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($reportPages > 1): ?>
                        <nav
                            class="notification-report-pagination"
                            aria-label="صفحه‌بندی گزارش ارسال"
                        >
                            <a
                                class="<?= $reportPage <= 1
                                    ? 'is-disabled'
                                    : '' ?>"
                                href="<?= admin_h(
                                    $reportQuery([
                                        'page' => max(
                                            1,
                                            $reportPage - 1
                                        ),
                                    ])
                                ) ?>"
                            >
                                قبلی
                            </a>

                            <span>
                                صفحه
                                <?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $reportPage
                                    )
                                ) ?>
                                از
                                <?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $reportPages
                                    )
                                ) ?>
                            </span>

                            <a
                                class="<?= $reportPage >= $reportPages
                                    ? 'is-disabled'
                                    : '' ?>"
                                href="<?= admin_h(
                                    $reportQuery([
                                        'page' => min(
                                            $reportPages,
                                            $reportPage + 1
                                        ),
                                    ])
                                ) ?>"
                            >
                                بعدی
                            </a>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>

                <script>
                (() => {
                    const report = document.querySelector(
                        '[data-notification-delivery-report]'
                    );

                    if (!report) {
                        return;
                    }

                    report.querySelectorAll(
                        '[data-notification-report-toggle]'
                    ).forEach((button) => {
                        button.addEventListener('click', () => {
                            const reference =
                                button.dataset
                                    .notificationReportToggle;
                            const detail = report.querySelector(
                                '[data-notification-report-detail="'
                                + CSS.escape(reference)
                                + '"]'
                            );

                            if (!detail) {
                                return;
                            }

                            const open = detail.hidden;
                            detail.hidden = !open;
                            button.setAttribute(
                                'aria-expanded',
                                open ? 'true' : 'false'
                            );
                            button.textContent =
                                open ? 'بستن' : 'مشاهده';
                        });
                    });
                })();
                </script>
            </section>

        <?php endif; ?>
    </section>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/admin/layout.php';
