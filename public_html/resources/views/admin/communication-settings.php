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
$notificationSendCenter = is_array(
    $page['notification_send_center'] ?? null
) ? $page['notification_send_center'] : [];
$notificationApprovalManagement = is_array(
    $page['notification_approval_management'] ?? null
) ? $page['notification_approval_management'] : [];
$baleConnectionManagement = is_array(
    $page['bale_connection_management'] ?? null
) ? $page['bale_connection_management'] : [];
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
    'notification_send_completed' => ['success', 'عملیات ارسال اعلان انجام شد. نتیجه هر مقصد در همین صفحه نمایش داده می‌شود.'],
    'notification_send_approval_submitted' => ['success', 'درخواست ارسال اعلان برای بررسی و تأیید ثبت شد.'],
    'notification_approval_approved_dispatched' => ['success', 'درخواست تأیید شد و اعلان با موفقیت ارسال شد.'],
    'notification_approval_approved_partial' => ['success', 'درخواست تأیید شد؛ بخشی از مقصدها ارسال شدند و بخشی ناموفق بودند.'],
    'notification_approval_approved_failed' => ['error', 'درخواست تأیید شد، اما ارسال اعلان ناموفق بود.'],
    'notification_approval_approved' => ['success', 'درخواست اعلان تأیید شد.'],
    'notification_approval_rejected' => ['success', 'درخواست ارسال اعلان رد شد.'],
    'notification_approval_reject_reason_required' => ['error', 'برای رد درخواست، ثبت دلیل الزامی است.'],
    'notification_approval_decide_forbidden' => ['error', 'دسترسی تصمیم‌گیری برای این درخواست فعال نیست.'],
    'notification_approval_approver_ineligible' => ['error', 'شما تأییدکننده مجاز این مرحله نیستید.'],
    'notification_approval_request_not_found' => ['error', 'درخواست تأیید اعلان پیدا نشد.'],
    'notification_approval_transition_invalid' => ['error', 'وضعیت فعلی درخواست اجازه این عملیات را نمی‌دهد.'],
    'notification_approval_operation_failed' => ['error', 'عملیات تأیید اعلان انجام نشد.'],
    'notification_send_forbidden' => ['error', 'دسترسی ارسال اعلان برای این نقش فعال نیست.'],
    'notification_send_channel_required' => ['error', 'حداقل یک کانال ارسال را انتخاب کنید.'],
    'notification_send_confirmation_required' => ['error', 'تأیید نهایی ارسال الزامی است.'],
    'notification_send_subject_invalid' => ['error', 'برای ارسال ایمیل، موضوع معتبر الزامی است.'],
    'notification_send_body_invalid' => ['error', 'متن اعلان خالی یا بیش از حد مجاز است.'],
    'notification_send_recipient_limit' => ['error', 'تعداد کاربران انتخاب‌شده بیش از حد مجاز این مرحله است.'],
    'notification_send_destination_required' => ['error', 'هیچ مقصد معتبر و قابل ارسالی پیدا نشد.'],
    'notification_send_immediate_limit_exceeded' => ['error', 'تعداد تحویل‌ها از سقف ارسال فوری بیشتر است. گروه بزرگ‌تر باید در صف ارسال انبوه قرار گیرد.'],
    'notification_send_failed' => ['error', 'عملیات ارسال اعلان انجام نشد.'],
    'notification_send_message_type_invalid' => ['error', 'نوع پیام معتبر نیست.'],
    'notification_send_multimedia_sms_not_supported' => ['error', 'پیام چندرسانه‌ای فقط از طریق ایمیل و بله قابل ارسال است.'],
    'notification_send_media_required' => ['error', 'برای پیام چندرسانه‌ای حداقل یک فایل انتخاب کنید.'],
    'notification_send_media_count_exceeded' => ['error', 'حداکثر پنج فایل در هر ارسال مجاز است.'],
    'notification_send_media_file_size_exceeded' => ['error', 'حجم هر فایل باید حداکثر ده مگابایت باشد.'],
    'notification_send_media_total_size_exceeded' => ['error', 'مجموع حجم فایل‌ها باید حداکثر سی مگابایت باشد.'],
    'notification_send_media_type_invalid' => ['error', 'نوع یا محتوای یکی از فایل‌ها مجاز نیست.'],
    'notification_send_media_type_detection_failed' => ['error', 'تشخیص نوع واقعی یکی از فایل‌ها انجام نشد.'],
    'notification_send_media_upload_invalid' => ['error', 'فایل بارگذاری‌شده معتبر نیست.'],
    'notification_send_media_upload_failed' => ['error', 'بارگذاری فایل‌های چندرسانه‌ای انجام نشد.'],
    'notification_send_media_storage_unavailable' => ['error', 'ساختار ذخیره‌سازی فایل‌های اعلان آماده نیست.'],
    'notification_send_media_storage_failed' => ['error', 'ذخیره امن فایل‌های اعلان انجام نشد.'],
    'notification_bale_invitation_sent' => ['success', 'لینک فعال‌سازی بله با پیامک برای کاربران قابل‌ارسال فرستاده شد.'],
    'notification_bale_invitation_recipient_required' => ['error', 'برای دعوت بله حداقل یک کاربر را انتخاب کنید.'],
    'notification_bale_invitation_limit' => ['error', 'تعداد کاربران دعوت بله بیش از سقف ارسال فوری است.'],
    'notification_bale_provider_unavailable' => ['error', 'بات فعال عضویت و احراز هویت بله در دسترس نیست.'],
    'notification_bale_auth_provider_unconfigured' => ['error', 'در تنظیمات بات جدید بله، کاربرد «عضویت و احراز هویت» را انتخاب کنید.'],
    'notification_bale_auth_provider_ambiguous' => ['error', 'بیش از یک بات بله برای عضویت و احراز هویت تعیین شده است؛ فقط یک بات را انتخاب کنید.'],
    'notification_bale_enrollment_link_missing' => ['error', 'نام کاربری ربات یا قالب لینک فعال‌سازی بله تنظیم نشده است.'],
    'notification_bale_enrollment_link_invalid' => ['error', 'لینک فعال‌سازی بله معتبر نیست.'],
    'notification_bale_invitation_failed' => ['error', 'ارسال دعوت فعال‌سازی بله انجام نشد.'],
    'notification_bale_connection_disconnected' => ['success', 'اتصال کاربر به بله با موفقیت قطع شد.'],
    'notification_bale_connection_not_found' => ['error', 'اتصال فعال بله برای این کاربر پیدا نشد.'],
    'notification_bale_connection_disconnect_failed' => ['error', 'قطع اتصال کاربر به بله انجام نشد.'],
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
    <?php elseif (str_starts_with($status, 'notification_approval_')): ?>
        <div class="admin-alert admin-alert--danger">
            عملیات کارتابل تأیید اعلان انجام نشد.
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

        <?php elseif ($section === 'bale_connections'): ?>
            <?php
            $baleProviderState = (string) (
                $baleConnectionManagement[
                    'provider_state'
                ] ?? 'unconfigured'
            );
            $baleProvider = is_array(
                $baleConnectionManagement[
                    'provider'
                ] ?? null
            ) ? $baleConnectionManagement[
                'provider'
            ] : [];
            $baleSummary = is_array(
                $baleConnectionManagement[
                    'summary'
                ] ?? null
            ) ? $baleConnectionManagement[
                'summary'
            ] : [];
            $baleUsers = is_array(
                $baleConnectionManagement[
                    'users'
                ] ?? null
            ) ? $baleConnectionManagement[
                'users'
            ] : [];
            $baleOrganizations = is_array(
                $baleConnectionManagement[
                    'organizations'
                ] ?? null
            ) ? $baleConnectionManagement[
                'organizations'
            ] : [];
            $baleRoles = is_array(
                $baleConnectionManagement[
                    'roles'
                ] ?? null
            ) ? $baleConnectionManagement[
                'roles'
            ] : [];
            $baleCities = is_array(
                $baleConnectionManagement[
                    'cities'
                ] ?? null
            ) ? $baleConnectionManagement[
                'cities'
            ] : [];
            $baleStatusLabels = [
                'connected' => 'متصل به بله',
                'invited' => 'دعوت ارسال‌شده',
                'waiting_confirmation' =>
                    'در انتظار اشتراک شماره',
                'expired' => 'دعوت منقضی‌شده',
                'failed' => 'ارسال دعوت ناموفق',
                'disconnected' => 'اتصال قطع‌شده',
                'not_connected' => 'متصل نیست',
            ];
            $baleProviderStateLabels = [
                'ready' => [
                    'success',
                    'بات عضویت و احراز هویت آماده است.',
                ],
                'unconfigured' => [
                    'error',
                    'بات بله با کاربرد «عضویت و احراز هویت» تنظیم نشده است.',
                ],
                'ambiguous' => [
                    'error',
                    'بیش از یک بات برای عضویت و احراز هویت تعیین شده است.',
                ],
            ];
            $baleProviderStateView =
                $baleProviderStateLabels[
                    $baleProviderState
                ] ?? $baleProviderStateLabels[
                    'unconfigured'
                ];
            $baleCsrf =
                (new \IPKF\Security\Csrf())->token();
            ?>

            <section
                class="bale-connection-management"
                data-bale-connection-management
            >
                <!-- bale-connection-management-v061 -->
                <header class="bale-connection-intro">
                    <div>
                        <h3>اتصال کاربران به پیام‌رسان بله</h3>
                        <p class="communication-muted">
                            دعوت اتصال را با پیامک ارسال کنید،
                            وضعیت فعال‌سازی هر کاربر را ببینید و
                            اتصال‌های ثبت‌شده را مدیریت کنید.
                        </p>
                    </div>

                    <div class="bale-connection-provider">
                        <span>بات عضویت و احراز هویت</span>
                        <?php if ($baleProvider !== []): ?>
                            <strong><?= admin_h(
                                $baleProvider['title']
                                ?? 'بات بله'
                            ) ?></strong>
                            <small dir="ltr">
                                <?= admin_h(
                                    trim((string) (
                                        $baleProvider[
                                            'username'
                                        ] ?? ''
                                    )) !== ''
                                        ? '@' . $baleProvider[
                                            'username'
                                        ]
                                        : 'username not set'
                                ) ?>
                            </small>
                        <?php else: ?>
                            <strong>تنظیم نشده</strong>
                        <?php endif; ?>
                        <em
                            class="bale-connection-provider__state bale-connection-provider__state--<?= admin_h(
                                $baleProviderStateView[0]
                            ) ?>"
                        >
                            <?= admin_h(
                                $baleProviderStateView[1]
                            ) ?>
                        </em>
                    </div>
                </header>

                <div class="bale-connection-summary">
                    <?php foreach ([
                        'total' => 'کل کاربران',
                        'connected' => 'متصل به بله',
                        'awaiting' => 'در انتظار تکمیل',
                        'needs_invitation' =>
                            'نیازمند دعوت',
                        'without_mobile' =>
                            'فاقد شماره همراه',
                    ] as $summaryKey => $summaryTitle): ?>
                        <article>
                            <span><?= admin_h(
                                $summaryTitle
                            ) ?></span>
                            <strong><?= admin_h(
                                \App\Support\AdminFormat
                                    ::digits(
                                        (int) (
                                            $baleSummary[
                                                $summaryKey
                                            ] ?? 0
                                        )
                                    )
                            ) ?></strong>
                        </article>
                    <?php endforeach; ?>
                </div>

                <form
                    class="bale-connection-form"
                    method="post"
                    action="/admin/communications/settings/send/bale-invitations"
                    data-bale-invite-form
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h($baleCsrf) ?>"
                    >

                    <section class="bale-connection-filters">
                        <label class="bale-connection-filter-search">
                            <span>جست‌وجو</span>
                            <input
                                type="search"
                                placeholder="نام، نام کاربری، نقش، سازمان، شهر یا وضعیت"
                                autocomplete="off"
                                data-bale-search
                            >
                        </label>

                        <label>
                            <span>وضعیت اتصال</span>
                            <select data-bale-status-filter>
                                <option value="">
                                    همه وضعیت‌ها
                                </option>
                                <?php foreach (
                                    $baleStatusLabels
                                    as $statusCode =>
                                        $statusTitle
                                ): ?>
                                    <option value="<?= admin_h(
                                        $statusCode
                                    ) ?>">
                                        <?= admin_h(
                                            $statusTitle
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>سازمان</span>
                            <select data-bale-organization-filter>
                                <option value="">
                                    همه سازمان‌ها
                                </option>
                                <?php foreach (
                                    $baleOrganizations
                                    as $organization
                                ): ?>
                                    <option value="<?= admin_h(
                                        mb_strtolower(
                                            $organization,
                                            'UTF-8'
                                        )
                                    ) ?>">
                                        <?= admin_h(
                                            $organization
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>نقش</span>
                            <select data-bale-role-filter>
                                <option value="">
                                    همه نقش‌ها
                                </option>
                                <?php foreach (
                                    $baleRoles as $role
                                ): ?>
                                    <option value="<?= admin_h(
                                        mb_strtolower(
                                            $role,
                                            'UTF-8'
                                        )
                                    ) ?>">
                                        <?= admin_h($role) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>شهر</span>
                            <select data-bale-city-filter>
                                <option value="">
                                    همه شهرها
                                </option>
                                <?php foreach (
                                    $baleCities as $city
                                ): ?>
                                    <option value="<?= admin_h(
                                        mb_strtolower(
                                            $city,
                                            'UTF-8'
                                        )
                                    ) ?>">
                                        <?= admin_h($city) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </section>

                    <div class="bale-connection-actions">
                        <button
                            class="admin-button admin-button--soft"
                            type="button"
                            data-bale-select-visible
                        >
                            انتخاب کاربران قابل دعوت
                        </button>
                        <button
                            class="admin-button admin-button--soft"
                            type="button"
                            data-bale-clear-selection
                        >
                            پاک‌کردن انتخاب
                        </button>
                        <span class="communication-muted">
                            <strong
                                data-bale-selected-count
                            >۰</strong>
                            کاربر انتخاب شده
                        </span>
                        <button
                            class="admin-button"
                            type="submit"
                            data-bale-send-invitations
                            <?= $baleProviderState === 'ready'
                                ? ''
                                : 'disabled' ?>
                        >
                            ارسال دعوت اتصال با پیامک
                        </button>
                    </div>

                    <div class="bale-connection-user-list">
                        <?php foreach (
                            $baleUsers as $baleUser
                        ): ?>
                            <?php
                            $baleUserStatus = (string) (
                                $baleUser[
                                    'bale_status_code'
                                ] ?? 'not_connected'
                            );
                            $baleUserStatusTitle =
                                $baleStatusLabels[
                                    $baleUserStatus
                                ] ?? $baleUserStatus;
                            $baleActivity = trim(
                                (string) (
                                    $baleUser[
                                        'bale_activity_at'
                                    ] ?? ''
                                )
                            );
                            $baleActivityTitle =
                                $baleActivity !== ''
                                    ? \App\Support\AdminFormat
                                        ::jalaliDateTime(
                                            $baleActivity
                                        )
                                    : '';
                            $baleSearch = mb_strtolower(
                                implode(' ', [
                                    $baleUser['title'] ?? '',
                                    $baleUser[
                                        'username'
                                    ] ?? '',
                                    $baleUser[
                                        'organization_title'
                                    ] ?? '',
                                    $baleUser[
                                        'role_titles'
                                    ] ?? '',
                                    $baleUser[
                                        'city_title'
                                    ] ?? '',
                                    $baleUserStatusTitle,
                                ]),
                                'UTF-8'
                            );
                            ?>
                            <article
                                class="bale-connection-user"
                                data-bale-user
                                data-search="<?= admin_h(
                                    $baleSearch
                                ) ?>"
                                data-status="<?= admin_h(
                                    $baleUserStatus
                                ) ?>"
                                data-organization="<?= admin_h(
                                    mb_strtolower(
                                        (string) (
                                            $baleUser[
                                                'organization_title'
                                            ] ?? ''
                                        ),
                                        'UTF-8'
                                    )
                                ) ?>"
                                data-role="<?= admin_h(
                                    mb_strtolower(
                                        (string) (
                                            $baleUser[
                                                'role_titles'
                                            ] ?? ''
                                        ),
                                        'UTF-8'
                                    )
                                ) ?>"
                                data-city="<?= admin_h(
                                    mb_strtolower(
                                        (string) (
                                            $baleUser[
                                                'city_title'
                                            ] ?? ''
                                        ),
                                        'UTF-8'
                                    )
                                ) ?>"
                                data-can-invite="<?= !empty(
                                    $baleUser[
                                        'can_invite_bale'
                                    ]
                                ) ? '1' : '0' ?>"
                            >
                                <label class="bale-connection-user__select">
                                    <input
                                        type="checkbox"
                                        name="recipient_user_ids[]"
                                        value="<?= admin_h(
                                            $baleUser['id']
                                        ) ?>"
                                        data-bale-user-checkbox
                                        <?= !empty(
                                            $baleUser[
                                                'can_invite_bale'
                                            ]
                                        ) ? '' : 'disabled' ?>
                                    >
                                </label>

                                <div class="bale-connection-user__identity">
                                    <strong><?= admin_h(
                                        $baleUser['title']
                                        ?? 'کاربر'
                                    ) ?></strong>
                                    <small>
                                        <?= admin_h(
                                            implode(
                                                ' • ',
                                                array_filter([
                                                    $baleUser[
                                                        'organization_title'
                                                    ] ?? '',
                                                    $baleUser[
                                                        'role_titles'
                                                    ] ?? '',
                                                    $baleUser[
                                                        'city_title'
                                                    ] ?? '',
                                                ])
                                            )
                                        ) ?>
                                    </small>
                                </div>

                                <div class="bale-connection-user__mobile">
                                    <span>شماره همراه</span>
                                    <strong class="<?= !empty(
                                        $baleUser['has_mobile']
                                    ) ? 'is-ready' : 'is-missing' ?>">
                                        <?= !empty(
                                            $baleUser['has_mobile']
                                        )
                                            ? 'ثبت شده'
                                            : 'ثبت نشده' ?>
                                    </strong>
                                </div>

                                <div class="bale-connection-user__status">
                                    <span
                                        class="bale-connection-status bale-connection-status--<?= admin_h(
                                            $baleUserStatus
                                        ) ?>"
                                    >
                                        <?= admin_h(
                                            $baleUserStatusTitle
                                        ) ?>
                                    </span>
                                    <?php if (
                                        $baleActivityTitle !== ''
                                    ): ?>
                                        <small>
                                            آخرین فعالیت:
                                            <?= admin_h(
                                                $baleActivityTitle
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div class="bale-connection-user__row-actions">
                                    <?php if (!empty(
                                        $baleUser[
                                            'can_disconnect_bale'
                                        ]
                                    )): ?>
                                        <button
                                            class="admin-button admin-button--soft admin-button--compact"
                                            type="button"
                                            data-bale-disconnect-user="<?= (int) $baleUser['id'] ?>"
                                            data-bale-disconnect-title="<?= admin_h(
                                                $baleUser['title']
                                                ?? 'کاربر'
                                            ) ?>"
                                        >
                                            قطع اتصال
                                        </button>
                                    <?php elseif (!empty(
                                        $baleUser[
                                            'can_invite_bale'
                                        ]
                                    )): ?>
                                        <small>
                                            قابل دعوت با پیامک
                                        </small>
                                    <?php else: ?>
                                        <small>
                                            ابتدا شماره همراه را
                                            ثبت کنید
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </form>

                <form
                    method="post"
                    action="/admin/communications/settings/bale-connections/disconnect"
                    data-bale-disconnect-form
                    hidden
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h($baleCsrf) ?>"
                    >
                    <input
                        type="hidden"
                        name="user_id"
                        value=""
                        data-bale-disconnect-user-id
                    >
                </form>

                <script>
                (() => {
                    const root = document.querySelector(
                        '[data-bale-connection-management]'
                    );

                    if (!root) {
                        return;
                    }

                    const users = Array.from(
                        root.querySelectorAll(
                            '[data-bale-user]'
                        )
                    );
                    const search = root.querySelector(
                        '[data-bale-search]'
                    );
                    const status = root.querySelector(
                        '[data-bale-status-filter]'
                    );
                    const organization = root.querySelector(
                        '[data-bale-organization-filter]'
                    );
                    const role = root.querySelector(
                        '[data-bale-role-filter]'
                    );
                    const city = root.querySelector(
                        '[data-bale-city-filter]'
                    );
                    const selectedCount = root.querySelector(
                        '[data-bale-selected-count]'
                    );
                    const submit = root.querySelector(
                        '[data-bale-send-invitations]'
                    );
                    const digits = new Intl.NumberFormat(
                        'fa-IR'
                    );

                    const refreshSelection = () => {
                        const selected = users.filter(
                            (user) => user.querySelector(
                                '[data-bale-user-checkbox]'
                            )?.checked
                        ).length;

                        if (selectedCount) {
                            selectedCount.textContent =
                                digits.format(selected);
                        }

                        if (submit) {
                            submit.disabled =
                                selected < 1
                                || <?= $baleProviderState === 'ready'
                                    ? 'false'
                                    : 'true' ?>;
                        }
                    };

                    const applyFilters = () => {
                        const needle = (
                            search?.value || ''
                        ).trim().toLocaleLowerCase('fa');
                        const wantedStatus =
                            status?.value || '';
                        const wantedOrganization =
                            organization?.value || '';
                        const wantedRole =
                            role?.value || '';
                        const wantedCity =
                            city?.value || '';

                        users.forEach((user) => {
                            user.hidden = !(
                                (
                                    needle === ''
                                    || user.dataset.search
                                        .includes(needle)
                                )
                                && (
                                    wantedStatus === ''
                                    || user.dataset.status
                                        === wantedStatus
                                )
                                && (
                                    wantedOrganization === ''
                                    || user.dataset.organization
                                        === wantedOrganization
                                )
                                && (
                                    wantedRole === ''
                                    || user.dataset.role
                                        .includes(wantedRole)
                                )
                                && (
                                    wantedCity === ''
                                    || user.dataset.city
                                        === wantedCity
                                )
                            );
                        });
                    };

                    search?.addEventListener(
                        'input',
                        applyFilters
                    );
                    status?.addEventListener(
                        'change',
                        applyFilters
                    );
                    organization?.addEventListener(
                        'change',
                        applyFilters
                    );
                    role?.addEventListener(
                        'change',
                        applyFilters
                    );
                    city?.addEventListener(
                        'change',
                        applyFilters
                    );

                    root.querySelector(
                        '[data-bale-select-visible]'
                    )?.addEventListener('click', () => {
                        users
                            .filter(
                                (user) =>
                                    !user.hidden
                                    && user.dataset.canInvite
                                        === '1'
                            )
                            .forEach((user) => {
                                const checkbox =
                                    user.querySelector(
                                        '[data-bale-user-checkbox]'
                                    );

                                if (checkbox) {
                                    checkbox.checked = true;
                                }
                            });

                        refreshSelection();
                    });

                    root.querySelector(
                        '[data-bale-clear-selection]'
                    )?.addEventListener('click', () => {
                        users.forEach((user) => {
                            const checkbox =
                                user.querySelector(
                                    '[data-bale-user-checkbox]'
                                );

                            if (checkbox) {
                                checkbox.checked = false;
                            }
                        });

                        refreshSelection();
                    });

                    root.addEventListener(
                        'change',
                        (event) => {
                            if (event.target.matches(
                                '[data-bale-user-checkbox]'
                            )) {
                                refreshSelection();
                            }
                        }
                    );

                    const disconnectForm =
                        root.querySelector(
                            '[data-bale-disconnect-form]'
                        );
                    const disconnectUserId =
                        disconnectForm?.querySelector(
                            '[data-bale-disconnect-user-id]'
                        );

                    root.querySelectorAll(
                        '[data-bale-disconnect-user]'
                    ).forEach((button) => {
                        button.addEventListener(
                            'click',
                            () => {
                                const userId =
                                    button.dataset
                                        .baleDisconnectUser
                                    || '';
                                const title =
                                    button.dataset
                                        .baleDisconnectTitle
                                    || 'این کاربر';

                                if (
                                    userId === ''
                                    || !disconnectForm
                                    || !disconnectUserId
                                ) {
                                    return;
                                }

                                if (!window.confirm(
                                    'اتصال بله برای '
                                    + title
                                    + ' قطع شود؟'
                                )) {
                                    return;
                                }

                                disconnectUserId.value =
                                    userId;
                                disconnectForm.submit();
                            }
                        );
                    });

                    applyFilters();
                    refreshSelection();
                })();
                </script>
            </section>

        <?php elseif ($section === 'approvals'): ?>
            <?php
            $approvalItems = is_array(
                $notificationApprovalManagement[
                    'items'
                ] ?? null
            ) ? $notificationApprovalManagement[
                'items'
            ] : [];

            $approvalCanDecide = !empty(
                $notificationApprovalManagement[
                    'can_decide'
                ]
            );

            $approvalHistory = is_array(
                $notificationApprovalManagement[
                    'history'
                ] ?? null
            ) ? $notificationApprovalManagement[
                'history'
            ] : [];

            $approvalHistoryItems = is_array(
                $approvalHistory[
                    'items'
                ] ?? null
            ) ? $approvalHistory[
                'items'
            ] : [];

            $approvalHistoryFilters = is_array(
                $approvalHistory[
                    'filters'
                ] ?? null
            ) ? $approvalHistory[
                'filters'
            ] : [];

            $approvalSummary = is_array(
                $approvalHistory[
                    'summary'
                ] ?? null
            ) ? $approvalHistory[
                'summary'
            ] : [];

            $approvalHistoryTotal = (int) (
                $approvalHistory[
                    'total'
                ] ?? 0
            );

            $approvalHistoryPage = max(
                1,
                (int) (
                    $approvalHistory[
                        'page'
                    ] ?? 1
                )
            );

            $approvalHistoryPages = max(
                1,
                (int) (
                    $approvalHistory[
                        'pages'
                    ] ?? 1
                )
            );

            $approvalHistoryPerPage = (int) (
                $approvalHistory[
                    'per_page'
                ] ?? 20
            );

            $approvalHistoryUrl =
                static function (
                    int $wantedPage
                ) use (
                    $approvalHistoryFilters,
                    $approvalHistoryPerPage
                ): string {
                    $params = [
                        'section' => 'approvals',
                        'q' => (string) (
                            $approvalHistoryFilters[
                                'q'
                            ] ?? ''
                        ),
                        'report_status' => (string) (
                            $approvalHistoryFilters[
                                'decision'
                            ] ?? ''
                        ),
                        'from' => (string) (
                            $approvalHistoryFilters[
                                'from_input'
                            ] ?? ''
                        ),
                        'to' => (string) (
                            $approvalHistoryFilters[
                                'to_input'
                            ] ?? ''
                        ),
                        'per_page' =>
                            $approvalHistoryPerPage,
                        'page' =>
                            max(1, $wantedPage),
                    ];

                    $params = array_filter(
                        $params,
                        static fn (
                            mixed $value
                        ): bool =>
                            $value !== ''
                            && $value !== null
                    );

                    return
                        '/admin/communications/settings?'
                        . http_build_query(
                            $params,
                            '',
                            '&',
                            PHP_QUERY_RFC3986
                        )
                        . '#approval-history';
                };

            $approvalCsrf =
                (new \IPKF\Security\Csrf())->token();

            $approvalChannelLabels = [
                'email' => 'ایمیل',
                'sms' => 'پیام کوتاه',
                'messenger' => 'پیام‌رسان بله',
            ];
            ?>

            <section
                class="notification-approval-summary"
                aria-label="خلاصه وضعیت تأیید اعلان‌ها"
            >
                <a
                    class="notification-approval-summary-card notification-approval-summary-card--pending"
                    href="#approval-pending"
                >
                    <span>در انتظار تأیید</span>
                    <strong>
                        <?= admin_h(
                            \App\Support\AdminFormat::digits(
                                (int) (
                                    $approvalSummary[
                                        'pending'
                                    ] ?? 0
                                )
                            )
                        ) ?>
                    </strong>
                </a>

                <a
                    class="notification-approval-summary-card notification-approval-summary-card--approved"
                    href="/admin/communications/settings?section=approvals&report_status=approve#approval-history"
                >
                    <span>تأییدشده</span>
                    <strong>
                        <?= admin_h(
                            \App\Support\AdminFormat::digits(
                                (int) (
                                    $approvalSummary[
                                        'approved'
                                    ] ?? 0
                                )
                            )
                        ) ?>
                    </strong>
                </a>

                <a
                    class="notification-approval-summary-card notification-approval-summary-card--rejected"
                    href="/admin/communications/settings?section=approvals&report_status=reject#approval-history"
                >
                    <span>ردشده</span>
                    <strong>
                        <?= admin_h(
                            \App\Support\AdminFormat::digits(
                                (int) (
                                    $approvalSummary[
                                        'rejected'
                                    ] ?? 0
                                )
                            )
                        ) ?>
                    </strong>
                </a>
            </section>

            <section
                class="provider-management-card"
                id="approval-pending"
            >
                <header class="provider-management-card__head">
                    <div>
                        <h3>کارتابل تأیید اعلان‌ها</h3>
                        <p class="communication-muted">
                            درخواست‌های در انتظار را بررسی کنید.
                            فقط درخواست تأییدشده وارد فرایند ارسال می‌شود.
                        </p>
                    </div>

                    <span class="communication-badge">
                        <?= admin_h(
                            \App\Support\AdminFormat::digits(
                                count($approvalItems)
                            )
                        ) ?>
                        درخواست در انتظار
                    </span>
                </header>

                <?php if ($approvalItems === []): ?>
                    <div class="provider-empty-state">
                        <strong>
                            درخواست در انتظار تأییدی وجود ندارد.
                        </strong>
                        <p>
                            درخواست‌های جدید کاربران پس از ثبت،
                            در این کارتابل نمایش داده می‌شوند.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="communication-table-wrap">
                        <table class="communication-table">
                            <thead>
                                <tr>
                                    <th>درخواست‌دهنده</th>
                                    <th>محتوا</th>
                                    <th>مقصدها و کانال‌ها</th>
                                    <th>تعداد</th>
                                    <th>زمان ثبت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php foreach (
                                $approvalItems
                                as $approvalItem
                            ): ?>
                                <?php
                                $approvalReference = (string) (
                                    $approvalItem[
                                        'public_reference'
                                    ] ?? ''
                                );

                                $approvalTargetSummary = is_array(
                                    $approvalItem[
                                        'target_summary'
                                    ] ?? null
                                ) ? $approvalItem[
                                    'target_summary'
                                ] : [];

                                $approvalTargetChannels = is_array(
                                    $approvalTargetSummary[
                                        'channels'
                                    ] ?? null
                                ) ? $approvalTargetSummary[
                                    'channels'
                                ] : [];

                                $approvalTargetCount = (int) (
                                    $approvalTargetSummary[
                                        'total'
                                    ]
                                    ?? $approvalItem[
                                        'target_count'
                                    ]
                                    ?? 0
                                );

                                $approvalChannels = is_array(
                                    $approvalItem[
                                        'channels'
                                    ] ?? null
                                ) ? $approvalItem[
                                    'channels'
                                ] : [];

                                $approvalSubject = trim(
                                    (string) (
                                        $approvalItem[
                                            'subject'
                                        ] ?? ''
                                    )
                                );

                                $approvalBody = trim(
                                    (string) (
                                        $approvalItem[
                                            'body'
                                        ] ?? ''
                                    )
                                );

                                $approvalReason = trim(
                                    (string) (
                                        $approvalItem[
                                            'request_reason'
                                        ] ?? ''
                                    )
                                );
                                ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?= admin_h(
                                                $approvalItem[
                                                    'requester_title'
                                                ] ?? 'کاربر'
                                            ) ?>
                                        </strong>

                                        <small dir="ltr">
                                            <?= admin_h(
                                                $approvalReference
                                            ) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?php if (
                                            $approvalSubject !== ''
                                        ): ?>
                                            <strong>
                                                <?= admin_h(
                                                    $approvalSubject
                                                ) ?>
                                            </strong>
                                        <?php else: ?>
                                            <strong>
                                                بدون موضوع
                                            </strong>
                                        <?php endif; ?>

                                        <details>
                                            <summary>
                                                مشاهده متن اعلان
                                            </summary>

                                            <p>
                                                <?= nl2br(
                                                    admin_h(
                                                        $approvalBody
                                                    )
                                                ) ?>
                                            </p>
                                        </details>

                                        <?php if (
                                            $approvalReason !== ''
                                        ): ?>
                                            <small>
                                                دلیل درخواست:
                                                <?= admin_h(
                                                    $approvalReason
                                                ) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div
                                            class="notification-approval-target-summary"
                                        >
                                            <?php if (
                                                $approvalTargetChannels
                                                !== []
                                            ): ?>
                                                <?php foreach (
                                                    $approvalTargetChannels
                                                    as $channel =>
                                                        $channelCount
                                                ): ?>
                                                    <span
                                                        class="communication-badge notification-approval-target-summary__badge"
                                                    >
                                                        <?= admin_h(
                                                            $approvalChannelLabels[
                                                                $channel
                                                            ] ?? $channel
                                                        ) ?>
                                                        :
                                                        <?= admin_h(
                                                            \App\Support\AdminFormat::digits(
                                                                (int) $channelCount
                                                            )
                                                        ) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php foreach (
                                                    $approvalChannels
                                                    as $channel
                                                ): ?>
                                                    <span
                                                        class="communication-badge"
                                                    >
                                                        <?= admin_h(
                                                            $approvalChannelLabels[
                                                                $channel
                                                            ] ?? $channel
                                                        ) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                            <?php if (
                                                $approvalTargetCount > 0
                                            ): ?>
                                                <button
                                                    class="admin-button admin-button--soft admin-button--compact notification-approval-target-open"
                                                    type="button"
                                                    data-approval-targets-open
                                                    data-approval-reference="<?= admin_h(
                                                        $approvalReference
                                                    ) ?>"
                                                    data-approval-subject="<?= admin_h(
                                                        $approvalSubject !== ''
                                                            ? $approvalSubject
                                                            : 'بدون موضوع'
                                                    ) ?>"
                                                >
                                                    مشاهده
                                                    <?= admin_h(
                                                        \App\Support\AdminFormat::digits(
                                                            $approvalTargetCount
                                                        )
                                                    ) ?>
                                                    گیرنده
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div>
                                            گیرنده:
                                            <strong>
                                                <?= admin_h(
                                                    \App\Support\AdminFormat::digits(
                                                        $approvalTargetCount
                                                    )
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div>
                                            پیوست:
                                            <strong>
                                                <?= admin_h(
                                                    \App\Support\AdminFormat::digits(
                                                        (int) (
                                                            $approvalItem[
                                                                'media_count'
                                                            ] ?? 0
                                                        )
                                                    )
                                                ) ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td>
                                        <?= admin_h(
                                            \App\Support\AdminFormat::digits(
                                                (string) (
                                                    $approvalItem[
                                                        'submitted_at'
                                                    ] ?? '—'
                                                )
                                            )
                                        ) ?>

                                        <div>
                                            <span class="communication-status communication-status--active">
                                                <?= admin_h(
                                                    $approvalItem[
                                                        'status_label'
                                                    ] ?? 'در انتظار تأیید'
                                                ) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if (
                                            $approvalCanDecide
                                        ): ?>
                                            <div
                                                class="notification-approval-actions"
                                            >
                                                <form
                                                    method="post"
                                                    action="<?= admin_h(
                                                        '/admin/communications/settings/approvals/'
                                                        . rawurlencode(
                                                            $approvalReference
                                                        )
                                                        . '/approve'
                                                    ) ?>"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="_token"
                                                        value="<?= admin_h(
                                                            $approvalCsrf
                                                        ) ?>"
                                                    >

                                                    <button
                                                        class="admin-button admin-button--compact"
                                                        type="submit"
                                                    >
                                                        تأیید و ارسال
                                                    </button>
                                                </form>

                                                <form
                                                    class="notification-approval-reject-form"
                                                    method="post"
                                                    action="<?= admin_h(
                                                        '/admin/communications/settings/approvals/'
                                                        . rawurlencode(
                                                            $approvalReference
                                                        )
                                                        . '/reject'
                                                    ) ?>"
                                                    data-approval-reject-form
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="_token"
                                                        value="<?= admin_h(
                                                            $approvalCsrf
                                                        ) ?>"
                                                    >

                                                    <button
                                                        class="admin-button admin-button--compact notification-approval-danger"
                                                        type="button"
                                                        data-approval-reject-toggle
                                                    >
                                                        رد درخواست
                                                    </button>

                                                    <div
                                                        class="notification-approval-reject-reason"
                                                        data-approval-reject-reason
                                                        hidden
                                                    >
                                                        <label>
                                                            <span>
                                                                علت رد درخواست
                                                            </span>
                                                            <input
                                                                type="text"
                                                                name="reason"
                                                                maxlength="2000"
                                                                placeholder="علت رد درخواست را وارد کنید"
                                                                data-approval-reject-input
                                                            >
                                                        </label>

                                                        <div
                                                            class="notification-approval-reject-actions"
                                                        >
                                                            <button
                                                                class="admin-button admin-button--compact notification-approval-danger"
                                                                type="submit"
                                                            >
                                                                ثبت رد
                                                            </button>

                                                            <button
                                                                class="admin-button admin-button--soft admin-button--compact"
                                                                type="button"
                                                                data-approval-reject-cancel
                                                            >
                                                                انصراف
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="communication-muted">
                                                فقط مشاهده
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section
                class="provider-management-card notification-approval-history"
                id="approval-history"
            >
                <header class="provider-management-card__head">
                    <div>
                        <h3>سوابق تصمیم‌ها</h3>
                        <p class="communication-muted">
                            سابقه تأیید و رد درخواست‌ها،
                            تصمیم‌گیرنده و نتیجه نهایی ارسال.
                        </p>
                    </div>

                    <span class="communication-badge">
                        <?= admin_h(
                            \App\Support\AdminFormat::digits(
                                $approvalHistoryTotal
                            )
                        ) ?>
                        سابقه
                    </span>
                </header>

                <form
                    class="notification-approval-history-filters"
                    method="get"
                    action="/admin/communications/settings"
                >
                    <input
                        type="hidden"
                        name="section"
                        value="approvals"
                    >

                    <label
                        class="notification-approval-history-search"
                    >
                        <span>جست‌وجو</span>
                        <input
                            type="search"
                            name="q"
                            value="<?= admin_h(
                                $approvalHistoryFilters[
                                    'q'
                                ] ?? ''
                            ) ?>"
                            placeholder="درخواست‌دهنده، تصمیم‌گیرنده، شناسه، موضوع یا علت"
                            autocomplete="off"
                        >
                    </label>

                    <label>
                        <span>نتیجه تصمیم</span>
                        <select name="report_status">
                            <option value="">
                                همه تصمیم‌ها
                            </option>
                            <option
                                value="approve"
                                <?= (
                                    (
                                        $approvalHistoryFilters[
                                            'decision'
                                        ] ?? ''
                                    ) === 'approve'
                                ) ? 'selected' : '' ?>
                            >
                                تأیید
                            </option>
                            <option
                                value="reject"
                                <?= (
                                    (
                                        $approvalHistoryFilters[
                                            'decision'
                                        ] ?? ''
                                    ) === 'reject'
                                ) ? 'selected' : '' ?>
                            >
                                رد
                            </option>
                        </select>
                    </label>

                    <label>
                        <span>از تاریخ</span>
                        <input
                            type="text"
                            name="from"
                            value="<?= admin_h(
                                $approvalHistoryFilters[
                                    'from_input'
                                ] ?? ''
                            ) ?>"
                            placeholder="۱۴۰۵/۰۵/۰۱"
                            autocomplete="off"
                        >
                    </label>

                    <label>
                        <span>تا تاریخ</span>
                        <input
                            type="text"
                            name="to"
                            value="<?= admin_h(
                                $approvalHistoryFilters[
                                    'to_input'
                                ] ?? ''
                            ) ?>"
                            placeholder="۱۴۰۵/۰۵/۳۱"
                            autocomplete="off"
                        >
                    </label>

                    <label>
                        <span>تعداد</span>
                        <select name="per_page">
                            <?php foreach (
                                [20, 50, 100]
                                as $pageSize
                            ): ?>
                                <option
                                    value="<?= admin_h(
                                        $pageSize
                                    ) ?>"
                                    <?= (
                                        $approvalHistoryPerPage
                                        === $pageSize
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= admin_h(
                                        \App\Support\AdminFormat::digits(
                                            $pageSize
                                        )
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div
                        class="notification-approval-history-filter-actions"
                    >
                        <button
                            class="admin-button admin-button--compact"
                            type="submit"
                        >
                            اعمال فیلتر
                        </button>

                        <a
                            class="admin-button admin-button--soft admin-button--compact"
                            href="/admin/communications/settings?section=approvals#approval-history"
                        >
                            پاک‌کردن
                        </a>
                    </div>
                </form>

                <?php if (
                    $approvalHistoryItems === []
                ): ?>
                    <div class="provider-empty-state">
                        <strong>
                            سابقه‌ای مطابق فیلترها پیدا نشد.
                        </strong>
                        <p>
                            با حذف فیلترها همه تأییدها و ردها
                            نمایش داده می‌شوند.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="communication-table-wrap">
                        <table
                            class="communication-table notification-approval-history-table"
                        >
                            <thead>
                                <tr>
                                    <th>تصمیم</th>
                                    <th>درخواست‌دهنده</th>
                                    <th>تصمیم‌گیرنده</th>
                                    <th>محتوا و دلایل</th>
                                    <th>گیرندگان</th>
                                    <th>زمان‌ها</th>
                                    <th>نتیجه ارسال</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php foreach (
                                $approvalHistoryItems
                                as $historyItem
                            ): ?>
                                <?php
                                $historyDecisionCode =
                                    (string) (
                                        $historyItem[
                                            'decision_code'
                                        ] ?? ''
                                    );

                                $historyRequestReason = trim(
                                    (string) (
                                        $historyItem[
                                            'request_reason'
                                        ] ?? ''
                                    )
                                );

                                $historyDecisionReason = trim(
                                    (string) (
                                        $historyItem[
                                            'decision_reason'
                                        ] ?? ''
                                    )
                                );

                                $historySubject = trim(
                                    (string) (
                                        $historyItem[
                                            'subject'
                                        ] ?? ''
                                    )
                                );

                                $historyBody = trim(
                                    (string) (
                                        $historyItem[
                                            'body'
                                        ] ?? ''
                                    )
                                );

                                $historyTargetSummary = is_array(
                                    $historyItem[
                                        'target_summary'
                                    ] ?? null
                                ) ? $historyItem[
                                    'target_summary'
                                ] : [];

                                $historyTargetChannels = is_array(
                                    $historyTargetSummary[
                                        'channels'
                                    ] ?? null
                                ) ? $historyTargetSummary[
                                    'channels'
                                ] : [];

                                $historyTargetCount = (int) (
                                    $historyTargetSummary[
                                        'total'
                                    ]
                                    ?? $historyItem[
                                        'target_count'
                                    ]
                                    ?? 0
                                );

                                $historyDispatchStatus =
                                    (string) (
                                        $historyItem[
                                            'dispatch_status_code'
                                        ] ?? ''
                                    );
                                ?>

                                <tr>
                                    <td>
                                        <span
                                            class="notification-approval-history-badge notification-approval-history-badge--<?= admin_h(
                                                $historyDecisionCode
                                            ) ?>"
                                        >
                                            <?= admin_h(
                                                $historyItem[
                                                    'decision_label'
                                                ] ?? '—'
                                            ) ?>
                                        </span>

                                        <small dir="ltr">
                                            <?= admin_h(
                                                $historyItem[
                                                    'public_reference'
                                                ] ?? ''
                                            ) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= admin_h(
                                                $historyItem[
                                                    'requester_title'
                                                ] ?? 'کاربر'
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= admin_h(
                                                $historyItem[
                                                    'actor_title'
                                                ] ?? 'کاربر'
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= admin_h(
                                                $historySubject !== ''
                                                    ? $historySubject
                                                    : 'بدون موضوع'
                                            ) ?>
                                        </strong>

                                        <?php if (
                                            $historyBody !== ''
                                        ): ?>
                                            <details>
                                                <summary>
                                                    مشاهده متن اعلان
                                                </summary>
                                                <p>
                                                    <?= nl2br(
                                                        admin_h(
                                                            $historyBody
                                                        )
                                                    ) ?>
                                                </p>
                                            </details>
                                        <?php endif; ?>

                                        <?php if (
                                            $historyRequestReason
                                            !== ''
                                        ): ?>
                                            <small
                                                class="notification-approval-history-note"
                                            >
                                                <b>
                                                    توضیح درخواست:
                                                </b>
                                                <?= admin_h(
                                                    $historyRequestReason
                                                ) ?>
                                            </small>
                                        <?php endif; ?>

                                        <?php if (
                                            $historyDecisionReason
                                            !== ''
                                        ): ?>
                                            <small
                                                class="notification-approval-history-note notification-approval-history-note--reject"
                                            >
                                                <b>
                                                    علت رد:
                                                </b>
                                                <?= admin_h(
                                                    $historyDecisionReason
                                                ) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div
                                            class="notification-approval-target-summary"
                                        >
                                            <?php if (
                                                $historyTargetChannels
                                                !== []
                                            ): ?>
                                                <?php foreach (
                                                    $historyTargetChannels
                                                    as $channel =>
                                                        $channelCount
                                                ): ?>
                                                    <span
                                                        class="communication-badge notification-approval-target-summary__badge"
                                                    >
                                                        <?= admin_h(
                                                            $approvalChannelLabels[
                                                                $channel
                                                            ] ?? $channel
                                                        ) ?>
                                                        :
                                                        <?= admin_h(
                                                            \App\Support\AdminFormat::digits(
                                                                (int) $channelCount
                                                            )
                                                        ) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span
                                                    class="communication-muted"
                                                >
                                                    بدون مقصد
                                                </span>
                                            <?php endif; ?>

                                            <?php if (
                                                $historyTargetCount > 0
                                            ): ?>
                                                <button
                                                    class="admin-button admin-button--soft admin-button--compact notification-approval-target-open"
                                                    type="button"
                                                    data-approval-targets-open
                                                    data-approval-reference="<?= admin_h(
                                                        $historyItem[
                                                            'public_reference'
                                                        ] ?? ''
                                                    ) ?>"
                                                    data-approval-subject="<?= admin_h(
                                                        $historySubject !== ''
                                                            ? $historySubject
                                                            : 'بدون موضوع'
                                                    ) ?>"
                                                >
                                                    مشاهده
                                                    <?= admin_h(
                                                        \App\Support\AdminFormat::digits(
                                                            $historyTargetCount
                                                        )
                                                    ) ?>
                                                    گیرنده
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <small>
                                            گیرنده:
                                            <?= admin_h(
                                                \App\Support\AdminFormat::digits(
                                                    $historyTargetCount
                                                )
                                            ) ?>
                                            · پیوست:
                                            <?= admin_h(
                                                \App\Support\AdminFormat::digits(
                                                    (int) (
                                                        $historyItem[
                                                            'media_count'
                                                        ] ?? 0
                                                    )
                                                )
                                            ) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <div>
                                            <small>
                                                ثبت درخواست
                                            </small>
                                            <strong>
                                                <?= admin_h(
                                                    \App\Support\AdminFormat::jalaliDateTime(
                                                        $historyItem[
                                                            'submitted_at'
                                                        ] ?? null
                                                    ) ?: '—'
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div>
                                            <small>
                                                تصمیم
                                            </small>
                                            <strong>
                                                <?= admin_h(
                                                    \App\Support\AdminFormat::jalaliDateTime(
                                                        $historyItem[
                                                            'decided_at'
                                                        ] ?? null
                                                    ) ?: '—'
                                                ) ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if (
                                            $historyDecisionCode
                                            === 'reject'
                                        ): ?>
                                            <span
                                                class="notification-approval-history-dispatch notification-approval-history-dispatch--rejected"
                                            >
                                                ارسال نشد
                                            </span>
                                            <small>
                                                درخواست رد شده است.
                                            </small>
                                        <?php elseif (
                                            $historyDispatchStatus
                                            !== ''
                                        ): ?>
                                            <span
                                                class="notification-approval-history-dispatch notification-approval-history-dispatch--<?= admin_h(
                                                    $historyDispatchStatus
                                                ) ?>"
                                            >
                                                <?= admin_h(
                                                    $historyItem[
                                                        'dispatch_status_label'
                                                    ] ?? $historyDispatchStatus
                                                ) ?>
                                            </span>

                                            <small>
                                                ارسال‌شده:
                                                <?= admin_h(
                                                    \App\Support\AdminFormat::digits(
                                                        (int) (
                                                            $historyItem[
                                                                'dispatch_sent_count'
                                                            ] ?? 0
                                                        )
                                                    )
                                                ) ?>
                                                · ناموفق:
                                                <?= admin_h(
                                                    \App\Support\AdminFormat::digits(
                                                        (int) (
                                                            $historyItem[
                                                                'dispatch_failed_count'
                                                            ] ?? 0
                                                        )
                                                    )
                                                ) ?>
                                            </small>
                                        <?php else: ?>
                                            <span
                                                class="notification-approval-history-dispatch"
                                            >
                                                <?= admin_h(
                                                    $historyItem[
                                                        'status_label'
                                                    ] ?? 'تأییدشده'
                                                ) ?>
                                            </span>
                                            <small>
                                                سابقه ارسال ثبت نشده است.
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <footer
                        class="notification-approval-history-footer"
                    >
                        <span>
                            صفحه
                            <strong>
                                <?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $approvalHistoryPage
                                    )
                                ) ?>
                            </strong>
                            از
                            <strong>
                                <?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $approvalHistoryPages
                                    )
                                ) ?>
                            </strong>
                        </span>

                        <nav
                            class="communication-pagination"
                            aria-label="صفحه‌بندی سوابق تصمیم‌ها"
                        >
                            <?php if (
                                $approvalHistoryPage > 1
                            ): ?>
                                <a
                                    href="<?= admin_h(
                                        $approvalHistoryUrl(
                                            $approvalHistoryPage - 1
                                        )
                                    ) ?>"
                                >
                                    قبلی
                                </a>
                            <?php endif; ?>

                            <?php if (
                                $approvalHistoryPage
                                < $approvalHistoryPages
                            ): ?>
                                <a
                                    href="<?= admin_h(
                                        $approvalHistoryUrl(
                                            $approvalHistoryPage + 1
                                        )
                                    ) ?>"
                                >
                                    بعدی
                                </a>
                            <?php endif; ?>
                        </nav>
                    </footer>
                <?php endif; ?>
            </section>

            <div
                class="notification-approval-target-dialog"
                data-approval-targets-dialog
                hidden
            >
                <button
                    class="notification-approval-target-dialog__backdrop"
                    type="button"
                    aria-label="بستن"
                    data-approval-targets-close
                ></button>

                <section
                    class="notification-approval-target-dialog__panel"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="approval-target-dialog-title"
                >
                    <header
                        class="notification-approval-target-dialog__head"
                    >
                        <div>
                            <h3 id="approval-target-dialog-title">
                                گیرندگان درخواست
                            </h3>

                            <strong
                                data-approval-targets-subject
                            ></strong>

                            <small
                                dir="ltr"
                                data-approval-targets-reference
                            ></small>
                        </div>

                        <button
                            class="admin-button admin-button--soft admin-button--compact"
                            type="button"
                            data-approval-targets-close
                        >
                            بستن
                        </button>
                    </header>

                    <div
                        class="notification-approval-target-dialog__summary"
                        data-approval-targets-summary
                    ></div>

                    <form
                        class="notification-approval-target-dialog__filters"
                        data-approval-targets-filter-form
                    >
                        <label>
                            <span>جست‌وجو</span>
                            <input
                                type="search"
                                autocomplete="off"
                                placeholder="نام گیرنده، شناسه یا مقصد ماسک‌شده"
                                data-approval-targets-query
                            >
                        </label>

                        <label>
                            <span>کانال</span>
                            <select
                                data-approval-targets-channel
                            >
                                <option value="">
                                    همه کانال‌ها
                                </option>
                                <option value="messenger">
                                    پیام‌رسان بله
                                </option>
                                <option value="sms">
                                    پیام کوتاه
                                </option>
                                <option value="email">
                                    ایمیل
                                </option>
                            </select>
                        </label>

                        <label>
                            <span>وضعیت</span>
                            <select
                                data-approval-targets-status
                            >
                                <option value="">
                                    همه وضعیت‌ها
                                </option>
                                <option value="pending">
                                    در انتظار
                                </option>
                                <option value="sent">
                                    ارسال‌شده
                                </option>
                                <option value="failed">
                                    ناموفق
                                </option>
                            </select>
                        </label>

                        <label>
                            <span>تعداد</span>
                            <select
                                data-approval-targets-per-page
                            >
                                <option value="20">۲۰</option>
                                <option value="50">۵۰</option>
                                <option value="100">۱۰۰</option>
                            </select>
                        </label>

                        <div
                            class="notification-approval-target-dialog__filter-actions"
                        >
                            <button
                                class="admin-button admin-button--compact"
                                type="submit"
                            >
                                اعمال
                            </button>

                            <button
                                class="admin-button admin-button--soft admin-button--compact"
                                type="button"
                                data-approval-targets-clear
                            >
                                پاک‌کردن
                            </button>
                        </div>
                    </form>

                    <div
                        class="notification-approval-target-dialog__state"
                        data-approval-targets-state
                    >
                        در حال دریافت گیرندگان...
                    </div>

                    <div
                        class="communication-table-wrap notification-approval-target-dialog__table-wrap"
                        data-approval-targets-table-wrap
                        hidden
                    >
                        <table
                            class="communication-table notification-approval-target-dialog__table"
                        >
                            <thead>
                                <tr>
                                    <th>گیرنده</th>
                                    <th>کانال</th>
                                    <th>مقصد</th>
                                    <th>وضعیت</th>
                                    <th>ارائه‌دهنده</th>
                                </tr>
                            </thead>

                            <tbody
                                data-approval-targets-body
                            ></tbody>
                        </table>
                    </div>

                    <footer
                        class="notification-approval-target-dialog__footer"
                    >
                        <span
                            data-approval-targets-page-info
                        ></span>

                        <div
                            class="communication-pagination"
                        >
                            <button
                                type="button"
                                data-approval-targets-prev
                                disabled
                            >
                                قبلی
                            </button>

                            <button
                                type="button"
                                data-approval-targets-next
                                disabled
                            >
                                بعدی
                            </button>
                        </div>
                    </footer>
                </section>
            </div>

            <script>
            (() => {
                const dialog = document.querySelector(
                    '[data-approval-targets-dialog]'
                );

                if (!dialog) {
                    return;
                }

                const subject = dialog.querySelector(
                    '[data-approval-targets-subject]'
                );

                const referenceText = dialog.querySelector(
                    '[data-approval-targets-reference]'
                );

                const summary = dialog.querySelector(
                    '[data-approval-targets-summary]'
                );

                const filterForm = dialog.querySelector(
                    '[data-approval-targets-filter-form]'
                );

                const query = dialog.querySelector(
                    '[data-approval-targets-query]'
                );

                const channel = dialog.querySelector(
                    '[data-approval-targets-channel]'
                );

                const status = dialog.querySelector(
                    '[data-approval-targets-status]'
                );

                const perPage = dialog.querySelector(
                    '[data-approval-targets-per-page]'
                );

                const clear = dialog.querySelector(
                    '[data-approval-targets-clear]'
                );

                const state = dialog.querySelector(
                    '[data-approval-targets-state]'
                );

                const tableWrap = dialog.querySelector(
                    '[data-approval-targets-table-wrap]'
                );

                const body = dialog.querySelector(
                    '[data-approval-targets-body]'
                );

                const pageInfo = dialog.querySelector(
                    '[data-approval-targets-page-info]'
                );

                const prev = dialog.querySelector(
                    '[data-approval-targets-prev]'
                );

                const next = dialog.querySelector(
                    '[data-approval-targets-next]'
                );

                let activeReference = '';
                let activePage = 1;
                let loading = false;

                const digits = (value) =>
                    new Intl.NumberFormat(
                        'fa-IR'
                    ).format(
                        Number(value || 0)
                    );

                const channelLabels = {
                    messenger: 'پیام‌رسان بله',
                    sms: 'پیام کوتاه',
                    email: 'ایمیل',
                };

                const statusLabels = {
                    pending: 'در انتظار',
                    sent: 'ارسال‌شده',
                    failed: 'ناموفق',
                };

                const addSummaryBadge = (
                    label,
                    value
                ) => {
                    if (!summary) {
                        return;
                    }

                    const badge =
                        document.createElement('span');

                    badge.className =
                        'communication-badge';

                    badge.textContent =
                        label
                        + ': '
                        + digits(value);

                    summary.appendChild(badge);
                };

                const renderSummary = (
                    data
                ) => {
                    if (!summary) {
                        return;
                    }

                    summary.replaceChildren();

                    const info =
                        data?.summary || {};

                    addSummaryBadge(
                        'کل گیرندگان',
                        info.total || 0
                    );

                    Object.entries(
                        info.channels || {}
                    ).forEach(
                        ([code, count]) => {
                            addSummaryBadge(
                                channelLabels[code]
                                    || code,
                                count
                            );
                        }
                    );

                    Object.entries(
                        info.statuses || {}
                    ).forEach(
                        ([code, count]) => {
                            addSummaryBadge(
                                statusLabels[code]
                                    || code,
                                count
                            );
                        }
                    );
                };

                const appendCell = (
                    row,
                    text,
                    direction = ''
                ) => {
                    const cell =
                        document.createElement('td');

                    cell.textContent =
                        text || '—';

                    if (direction !== '') {
                        cell.dir = direction;
                    }

                    row.appendChild(cell);
                };

                const renderRows = (
                    items
                ) => {
                    body?.replaceChildren();

                    if (
                        !body
                        || !Array.isArray(items)
                    ) {
                        return;
                    }

                    items.forEach((item) => {
                        const row =
                            document.createElement('tr');

                        appendCell(
                            row,
                            item.recipient_title
                                || 'گیرنده'
                        );

                        appendCell(
                            row,
                            item.channel_label
                                || channelLabels[
                                    item.channel_code
                                ]
                                || item.channel_code
                                || '—'
                        );

                        appendCell(
                            row,
                            item.destination_masked
                                || '—',
                            'ltr'
                        );

                        appendCell(
                            row,
                            item.status_label
                                || statusLabels[
                                    item.status_code
                                ]
                                || item.status_code
                                || '—'
                        );

                        appendCell(
                            row,
                            item.provider_title
                                || '—'
                        );

                        body.appendChild(row);
                    });
                };

                const load = async (
                    wantedPage = 1
                ) => {
                    if (
                        loading
                        || activeReference === ''
                    ) {
                        return;
                    }

                    activePage = Math.max(
                        1,
                        Number(wantedPage || 1)
                    );

                    loading = true;

                    if (state) {
                        state.hidden = false;
                        state.textContent =
                            'در حال دریافت گیرندگان...';
                    }

                    if (tableWrap) {
                        tableWrap.hidden = true;
                    }

                    if (prev) {
                        prev.disabled = true;
                    }

                    if (next) {
                        next.disabled = true;
                    }

                    const params =
                        new URLSearchParams({
                            section: 'approvals',
                            targets_reference:
                                activeReference,
                            targets_q:
                                query?.value.trim()
                                || '',
                            targets_channel:
                                channel?.value
                                || '',
                            targets_status:
                                status?.value
                                || '',
                            targets_page:
                                String(activePage),
                            targets_per_page:
                                perPage?.value
                                || '20',
                        });

                    try {
                        const response =
                            await fetch(
                                '/admin/communications/settings?'
                                + params.toString(),
                                {
                                    credentials:
                                        'same-origin',
                                    headers: {
                                        Accept:
                                            'application/json',
                                    },
                                }
                            );

                        const payload =
                            await response.json();

                        if (
                            !response.ok
                            || payload?.status !== 'ok'
                            || !payload?.data
                        ) {
                            throw new Error(
                                payload?.code
                                || 'request_failed'
                            );
                        }

                        const data =
                            payload.data;

                        const items =
                            Array.isArray(
                                data.items
                            )
                                ? data.items
                                : [];

                        renderSummary(data);
                        renderRows(items);

                        if (state) {
                            state.hidden =
                                items.length > 0;

                            state.textContent =
                                items.length > 0
                                    ? ''
                                    : 'گیرنده‌ای مطابق فیلترها پیدا نشد.';
                        }

                        if (tableWrap) {
                            tableWrap.hidden =
                                items.length === 0;
                        }

                        const page =
                            Math.max(
                                1,
                                Number(
                                    data.page || 1
                                )
                            );

                        const pages =
                            Math.max(
                                1,
                                Number(
                                    data.pages || 1
                                )
                            );

                        activePage = page;

                        if (pageInfo) {
                            pageInfo.textContent =
                                'صفحه '
                                + digits(page)
                                + ' از '
                                + digits(pages)
                                + ' · '
                                + digits(
                                    data.total || 0
                                )
                                + ' نتیجه';
                        }

                        if (prev) {
                            prev.disabled =
                                page <= 1;
                        }

                        if (next) {
                            next.disabled =
                                page >= pages;
                        }
                    } catch (error) {
                        body?.replaceChildren();

                        if (tableWrap) {
                            tableWrap.hidden = true;
                        }

                        if (state) {
                            state.hidden = false;
                            state.textContent =
                                'دریافت فهرست گیرندگان انجام نشد.';
                        }

                        if (pageInfo) {
                            pageInfo.textContent = '';
                        }
                    } finally {
                        loading = false;
                    }
                };

                const closeDialog = () => {
                    dialog.hidden = true;

                    document.body.classList.remove(
                        'notification-approval-target-dialog-open'
                    );

                    activeReference = '';
                    activePage = 1;
                };

                document.querySelectorAll(
                    '[data-approval-targets-open]'
                ).forEach((button) => {
                    button.addEventListener(
                        'click',
                        () => {
                            activeReference =
                                button.dataset
                                    .approvalReference
                                || '';

                            if (
                                activeReference === ''
                            ) {
                                return;
                            }

                            if (subject) {
                                subject.textContent =
                                    button.dataset
                                        .approvalSubject
                                    || 'بدون موضوع';
                            }

                            if (referenceText) {
                                referenceText.textContent =
                                    activeReference;
                            }

                            if (query) {
                                query.value = '';
                            }

                            if (channel) {
                                channel.value = '';
                            }

                            if (status) {
                                status.value = '';
                            }

                            if (perPage) {
                                perPage.value = '20';
                            }

                            dialog.hidden = false;

                            document.body.classList.add(
                                'notification-approval-target-dialog-open'
                            );

                            load(1);
                        }
                    );
                });

                dialog.querySelectorAll(
                    '[data-approval-targets-close]'
                ).forEach((button) => {
                    button.addEventListener(
                        'click',
                        closeDialog
                    );
                });

                filterForm?.addEventListener(
                    'submit',
                    (event) => {
                        event.preventDefault();
                        load(1);
                    }
                );

                clear?.addEventListener(
                    'click',
                    () => {
                        if (query) {
                            query.value = '';
                        }

                        if (channel) {
                            channel.value = '';
                        }

                        if (status) {
                            status.value = '';
                        }

                        if (perPage) {
                            perPage.value = '20';
                        }

                        load(1);
                    }
                );

                prev?.addEventListener(
                    'click',
                    () => {
                        load(activePage - 1);
                    }
                );

                next?.addEventListener(
                    'click',
                    () => {
                        load(activePage + 1);
                    }
                );

                document.addEventListener(
                    'keydown',
                    (event) => {
                        if (
                            event.key === 'Escape'
                            && !dialog.hidden
                        ) {
                            closeDialog();
                        }
                    }
                );
            })();
            </script>

            <script>
            (() => {
                document.querySelectorAll(
                    '[data-approval-reject-form]'
                ).forEach((form) => {
                    const toggle = form.querySelector(
                        '[data-approval-reject-toggle]'
                    );
                    const panel = form.querySelector(
                        '[data-approval-reject-reason]'
                    );
                    const input = form.querySelector(
                        '[data-approval-reject-input]'
                    );
                    const cancel = form.querySelector(
                        '[data-approval-reject-cancel]'
                    );

                    if (!toggle || !panel || !input) {
                        return;
                    }

                    toggle.addEventListener(
                        'click',
                        () => {
                            panel.hidden = false;
                            toggle.hidden = true;
                            input.required = true;
                            input.focus();
                        }
                    );

                    cancel?.addEventListener(
                        'click',
                        () => {
                            input.required = false;
                            input.value = '';
                            panel.hidden = true;
                            toggle.hidden = false;
                            toggle.focus();
                        }
                    );

                    form.addEventListener(
                        'submit',
                        (event) => {
                            if (
                                input.value.trim() === ''
                            ) {
                                event.preventDefault();
                                input.required = true;
                                input.focus();
                                input.reportValidity();
                            }
                        }
                    );
                });
            })();
            </script>

        <?php elseif ($section === 'send'): ?>
            <?php
            $sendRecipients = is_array(
                $notificationSendCenter[
                    'recipients'
                ] ?? null
            ) ? $notificationSendCenter[
                'recipients'
            ] : [];
            $sendOrganizations = is_array(
                $notificationSendCenter[
                    'organizations'
                ] ?? null
            ) ? $notificationSendCenter[
                'organizations'
            ] : [];
            $sendRoles = is_array(
                $notificationSendCenter[
                    'roles'
                ] ?? null
            ) ? $notificationSendCenter[
                'roles'
            ] : [];
            $sendCities = is_array(
                $notificationSendCenter[
                    'cities'
                ] ?? null
            ) ? $notificationSendCenter[
                'cities'
            ] : [];
            $sendLimit = max(
                1,
                (int) (
                    $notificationSendCenter[
                        'immediate_limit'
                    ] ?? 30
                )
            );
            $sendResult = is_array(
                $notificationSendCenter[
                    'result'
                ] ?? null
            ) ? $notificationSendCenter[
                'result'
            ] : [];
            $sendResultItems = is_array(
                $sendResult['items'] ?? null
            ) ? $sendResult['items'] : [];

            $sendAccessPolicyCode = (string) (
                $notificationSendCenter[
                    'access_policy_code'
                ] ?? ''
            );

            $sendApprovalRequired =
                $sendAccessPolicyCode ===
                    'approval_required';

            $sendPendingApproval =
                (string) (
                    $sendResult[
                        'workflow_status'
                    ] ?? ''
                ) === 'pending_approval';

            $sendSummaryLabels =
                $sendPendingApproval
                    ? [
                        'total' => 'کل مقصدها',
                        'sent' => 'ارسال‌شده',
                        'failed' => 'ناموفق',
                        'skipped' => 'ردشده',
                    ]
                    : [
                        'total' => 'کل تحویل',
                        'sent' => 'ارسال‌شده',
                        'failed' => 'ناموفق',
                        'skipped' => 'ردشده',
                    ];
            ?>

            <section
                class="notification-send-center"
                data-notification-send-center
            >
                <!-- notification-send-center-v061 -->
                <header class="notification-send-intro">
                    <div>
                        <h3>ارسال تکی و گروهی اعلان</h3>
                        <p class="communication-muted">
                            کاربران سامانه یا مقصدهای دستی را انتخاب
                            کنید و اعلان را از یک یا چند کانال فعال
                            ارسال کنید.
                        </p>
                    </div>
                    <div class="notification-send-limit">
                        <span>سقف ارسال فوری</span>
                        <strong>
                            <?= admin_h(
                                \App\Support\AdminFormat::digits(
                                    $sendLimit
                                )
                            ) ?>
                            تحویل
                        </strong>
                        <small>
                            گروه‌های بزرگ‌تر در مرحله صف انبوه
                        </small>
                    </div>
                </header>

                <?php if ($sendResult !== []): ?>
                    <section class="notification-send-result">
                        <header>
                            <div>
                                <h3>
                                    <?= $sendPendingApproval
                                        ? 'نتیجه آخرین درخواست ارسال'
                                        : 'نتیجه آخرین ارسال' ?>
                                </h3>
                                <small dir="ltr">
                                    <?= admin_h(
                                        $sendResult[
                                            'public_reference'
                                        ] ?? ''
                                    ) ?>
                                </small>
                            </div>
                            <a
                                class="admin-button admin-button--soft"
                                href="/admin/communications/settings?section=reports"
                            >
                                مشاهده گزارش کامل
                            </a>
                        </header>

                        <div class="notification-send-result__summary">
                            <?php foreach (
                                $sendSummaryLabels
                                as $key => $label
                            ): ?>
                                <article>
                                    <span><?= admin_h(
                                        $label
                                    ) ?></span>
                                    <strong><?= admin_h(
                                        \App\Support\AdminFormat
                                            ::digits(
                                                (int) (
                                                    $sendResult[
                                                        $key
                                                    ] ?? 0
                                                )
                                            )
                                    ) ?></strong>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if (
                            $sendResultItems !== []
                        ): ?>
                            <div class="communication-table-wrap">
                                <table class="communication-table notification-send-result-table">
                                    <thead>
                                        <tr>
                                            <th>گیرنده</th>
                                            <th>مقصد</th>
                                            <th>کانال</th>
                                            <th>وضعیت</th>
                                            <th>سرویس‌دهنده</th>
                                            <th>شناسه تحویل / خطا</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach (
                                        $sendResultItems
                                        as $resultItem
                                    ): ?>
                                        <?php
                                        $resultChannel = (string) (
                                            $resultItem[
                                                'channel_code'
                                            ] ?? ''
                                        );
                                        $resultStatus = (string) (
                                            $resultItem[
                                                'status_code'
                                            ] ?? ''
                                        );
                                        ?>
                                        <tr>
                                            <td><?= admin_h(
                                                $resultItem[
                                                    'recipient_title'
                                                ] ?? '—'
                                            ) ?></td>
                                            <td dir="ltr"><?= admin_h(
                                                $resultItem[
                                                    'destination_masked'
                                                ] ?? '—'
                                            ) ?></td>
                                            <td><?= admin_h(
                                                $channelLabels[
                                                    $resultChannel
                                                ] ?? $resultChannel
                                            ) ?></td>
                                            <td>
                                                <span
                                                    class="notification-send-result-status notification-send-result-status--<?= admin_h(
                                                        $resultStatus
                                                    ) ?>"
                                                >
                                                    <?= admin_h(
                                                        [
                                                            'pending' => 'در انتظار تأیید',
                                                            'sent' => 'ارسال‌شده',
                                                            'failed' => 'ناموفق',
                                                            'skipped' => 'ردشده',
                                                        ][$resultStatus]
                                                        ?? $resultStatus
                                                    ) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= admin_h(
                                                    $resultItem[
                                                        'provider_title'
                                                    ] ?? '—'
                                                ) ?>
                                                <?php if (
                                                    !empty(
                                                        $resultItem[
                                                            'fallback_used'
                                                        ]
                                                    )
                                                ): ?>
                                                    <small>
                                                        مسیر جایگزین
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (
                                                    $resultStatus
                                                    === 'sent'
                                                ): ?>
                                                    <code dir="ltr"><?= admin_h(
                                                        $resultItem[
                                                            'delivery_reference'
                                                        ] ?? ''
                                                    ) ?></code>
                                                <?php else: ?>
                                                    <code dir="ltr"><?= admin_h(
                                                        $resultItem[
                                                            'error_code'
                                                        ] ?? ''
                                                    ) ?></code>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <form
                    class="notification-send-form"
                    method="post"
                    action="/admin/communications/settings/send"
                    enctype="multipart/form-data"
                    data-notification-send-form
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h(
                            (new \IPKF\Security\Csrf())
                                ->token()
                        ) ?>"
                    >

                    <fieldset class="notification-send-section">
                        <legend>۱. کانال‌های ارسال</legend>

                        <div class="notification-send-channel-grid">
                            <?php foreach ([
                                'email' => [
                                    'title' => 'ایمیل',
                                    'description' =>
                                        'نشانی ایمیل ثبت‌شده کاربران یا مقصد دستی',
                                ],
                                'sms' => [
                                    'title' => 'پیام کوتاه (SMS)',
                                    'description' =>
                                        'شماره همراه کاربران یا مقصد دستی',
                                ],
                                'messenger' => [
                                    'title' => 'پیام‌رسان بله',
                                    'description' =>
                                        'شناسه گفت‌وگوی ثبت‌شده یا مقصد دستی',
                                ],
                            ] as $channelCode => $definition): ?>
                                <label
                                    class="notification-send-channel"
                                    data-send-channel-card="<?= admin_h(
                                        $channelCode
                                    ) ?>"
                                >
                                    <input
                                        type="checkbox"
                                        name="channels[]"
                                        value="<?= admin_h(
                                            $channelCode
                                        ) ?>"
                                        data-send-channel="<?= admin_h(
                                            $channelCode
                                        ) ?>"
                                    >
                                    <span>
                                        <strong><?= admin_h(
                                            $definition['title']
                                        ) ?></strong>
                                        <small><?= admin_h(
                                            $definition[
                                                'description'
                                            ]
                                        ) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="notification-send-section">
                        <legend>۲. انتخاب کاربران</legend>

                        <div class="notification-send-user-tools">
                            <label class="notification-send-user-search">
                                <span>جست‌وجو</span>
                                <input
                                    type="search"
                                    data-send-user-search
                                    placeholder="نام، نام کاربری، نقش، سازمان یا شهر"
                                    autocomplete="off"
                                >
                            </label>

                            <label>
                                <span>سازمان</span>
                                <select data-send-user-organization>
                                    <option value="">
                                        همه سازمان‌ها
                                    </option>
                                    <?php foreach (
                                        $sendOrganizations
                                        as $organization
                                    ): ?>
                                        <option value="<?= admin_h(
                                            mb_strtolower(
                                                $organization,
                                                'UTF-8'
                                            )
                                        ) ?>">
                                            <?= admin_h(
                                                $organization
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>نقش</span>
                                <select data-send-user-role>
                                    <option value="">
                                        همه نقش‌ها
                                    </option>
                                    <?php foreach (
                                        $sendRoles as $role
                                    ): ?>
                                        <option value="<?= admin_h(
                                            mb_strtolower(
                                                $role,
                                                'UTF-8'
                                            )
                                        ) ?>">
                                            <?= admin_h($role) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>شهر</span>
                                <select data-send-user-city>
                                    <option value="">
                                        همه شهرها
                                    </option>
                                    <?php foreach (
                                        $sendCities as $city
                                    ): ?>
                                        <option value="<?= admin_h(
                                            mb_strtolower(
                                                $city,
                                                'UTF-8'
                                            )
                                        ) ?>">
                                            <?= admin_h($city) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="notification-send-user-actions">
                            <button
                                class="admin-button admin-button--soft"
                                type="button"
                                data-send-select-visible
                            >
                                انتخاب نتایج نمایان
                            </button>
                            <button
                                class="admin-button admin-button--soft"
                                type="button"
                                data-send-clear-users
                            >
                                پاک‌کردن انتخاب کاربران
                            </button>
                            <span class="communication-muted">
                                <strong data-send-selected-count>۰</strong>
                                کاربر انتخاب شده
                            </span>
                        </div>

                        <div class="notification-send-user-list">
                            <?php foreach (
                                $sendRecipients as $recipient
                            ): ?>
                                <?php
                                $recipientSearch = mb_strtolower(
                                    implode(' ', [
                                        $recipient['title'] ?? '',
                                        $recipient['username'] ?? '',
                                        $recipient[
                                            'organization_title'
                                        ] ?? '',
                                        $recipient[
                                            'role_titles'
                                        ] ?? '',
                                        $recipient[
                                            'city_title'
                                        ] ?? '',
                                    ]),
                                    'UTF-8'
                                );
                                ?>
                                <label
                                    class="notification-send-user"
                                    data-send-user
                                    data-search="<?= admin_h(
                                        $recipientSearch
                                    ) ?>"
                                    data-organization="<?= admin_h(
                                        mb_strtolower(
                                            (string) (
                                                $recipient[
                                                    'organization_title'
                                                ] ?? ''
                                            ),
                                            'UTF-8'
                                        )
                                    ) ?>"
                                    data-role="<?= admin_h(
                                        mb_strtolower(
                                            (string) (
                                                $recipient[
                                                    'role_titles'
                                                ] ?? ''
                                            ),
                                            'UTF-8'
                                        )
                                    ) ?>"
                                    data-city="<?= admin_h(
                                        mb_strtolower(
                                            (string) (
                                                $recipient[
                                                    'city_title'
                                                ] ?? ''
                                            ),
                                            'UTF-8'
                                        )
                                    ) ?>"
                                    data-email="<?= !empty(
                                        $recipient['has_email']
                                    ) ? '1' : '0' ?>"
                                    data-sms="<?= !empty(
                                        $recipient['has_sms']
                                    ) ? '1' : '0' ?>"
                                    data-messenger="<?= !empty(
                                        $recipient[
                                            'has_messenger'
                                        ]
                                    ) ? '1' : '0' ?>"
                                >
                                    <input
                                        type="checkbox"
                                        name="recipient_user_ids[]"
                                        value="<?= admin_h(
                                            $recipient['id']
                                        ) ?>"
                                        data-send-user-checkbox
                                    >
                                    <span class="notification-send-user__identity">
                                        <strong><?= admin_h(
                                            $recipient['title']
                                        ) ?></strong>
                                        <small>
                                            <?= admin_h(
                                                implode(' • ', array_filter([
                                                    $recipient[
                                                        'organization_title'
                                                    ] ?? '',
                                                    $recipient[
                                                        'role_titles'
                                                    ] ?? '',
                                                    $recipient[
                                                        'city_title'
                                                    ] ?? '',
                                                ]))
                                            ) ?>
                                        </small>
                                    </span>
                                    <span class="notification-send-user__channels">
                                        <small class="<?= !empty(
                                            $recipient['has_email']
                                        ) ? 'is-ready' : '' ?>">
                                            ایمیل
                                        </small>
                                        <small class="<?= !empty(
                                            $recipient['has_sms']
                                        ) ? 'is-ready' : '' ?>">
                                            پیامک
                                        </small>
                                        <small class="<?= !empty(
                                            $recipient[
                                                'has_messenger'
                                            ]
                                        ) ? 'is-ready' : '' ?>">
                                            بله
                                        </small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="notification-send-section">
                        <legend>۳. مقصدهای دستی</legend>
                        <p class="communication-muted">
                            هر مقصد را در یک خط وارد کنید. جداکردن با
                            ویرگول نیز پشتیبانی می‌شود. مقصدهای تکراری
                            قبل از ارسال حذف می‌شوند.
                        </p>

                        <div class="notification-send-manual-grid">
                            <label
                                data-send-manual-channel="email"
                                hidden
                            >
                                <span>ایمیل‌های دستی</span>
                                <textarea
                                    name="manual_email"
                                    dir="ltr"
                                    placeholder="user@example.com"
                                ></textarea>
                            </label>

                            <label
                                data-send-manual-channel="sms"
                                hidden
                            >
                                <span>شماره‌های همراه دستی</span>
                                <textarea
                                    name="manual_sms"
                                    dir="ltr"
                                    placeholder="09123456789"
                                ></textarea>
                            </label>

                            <label
                                data-send-manual-channel="messenger"
                                hidden
                            >
                                <span>Chat IDهای بله</span>
                                <textarea
                                    name="manual_messenger"
                                    dir="ltr"
                                    placeholder="123456789"
                                ></textarea>
                            </label>
                        </div>
                    </fieldset>

                    <fieldset
                        class="notification-send-section notification-send-content-step"
                        data-send-content-step
                    >
                        <legend>۴. محتوای اعلان</legend>

                        <section class="notification-send-content-card">
                            <header class="notification-send-content-card__header">
                                <div>
                                    <h3>محتوای پیام</h3>
                                    <p>
                                        متن اصلی اعلان را بنویسید؛
                                        موضوع فقط برای ایمیل استفاده
                                        می‌شود.
                                    </p>
                                </div>
                                <span data-send-content-status>
                                    آماده‌سازی محتوا
                                </span>
                            </header>

                            <div class="notification-send-content-grid">
                                <label
                                    class="notification-send-subject-field"
                                    data-send-subject-field
                                    hidden
                                >
                                    <span>
                                        <strong>موضوع ایمیل</strong>
                                        <small>الزامی برای ایمیل</small>
                                    </span>
                                    <input
                                        name="subject"
                                        maxlength="190"
                                        data-send-subject
                                        placeholder="موضوع کوتاه و روشن بنویسید"
                                    >
                                </label>

                                <label class="notification-send-content-grid__body">
                                    <span class="notification-send-body-heading">
                                        <strong>متن اعلان</strong>
                                        <small>
                                            <b data-send-body-count>۰</b>
                                            از ۱۰٬۰۰۰ نویسه
                                        </small>
                                    </span>
                                    <textarea
                                        name="body"
                                        maxlength="10000"
                                        required
                                        data-send-body
                                        placeholder="متن اعلان را بنویسید..."
                                    ></textarea>
                                    <small class="communication-muted">
                                        متن برای همه کانال‌های انتخاب‌شده
                                        استفاده می‌شود.
                                    </small>
                                </label>
                            </div>
                        </section>

                        <?php if ($sendApprovalRequired): ?>
                            <section
                                class="notification-send-request-reason"
                            >
                                <label>
                                    <span>
                                        <strong>
                                            توضیح / دلیل درخواست ارسال
                                        </strong>
                                        <small>
                                            اختیاری؛ این توضیح برای
                                            تأییدکننده نمایش داده می‌شود.
                                        </small>
                                    </span>

                                    <textarea
                                        name="request_reason"
                                        maxlength="1000"
                                        placeholder="در صورت نیاز، دلیل یا توضیح درخواست ارسال را بنویسید..."
                                    ></textarea>
                                </label>
                            </section>
                        <?php endif; ?>
                    </fieldset>

                    <section class="notification-send-review">
                        <div>
                            <span>
                                <?= $sendApprovalRequired
                                    ? 'برآورد مقصدهای درخواست'
                                    : 'برآورد ارسال فوری' ?>
                            </span>
                            <strong>
                                <span data-send-estimated-count>۰</span>
                                <?= $sendApprovalRequired
                                    ? 'مقصد'
                                    : 'تحویل' ?>
                            </strong>
                            <small>
                                فقط کاربران دارای مقصد معتبر در
                                کانال انتخاب‌شده محاسبه می‌شوند.
                            </small>
                        </div>

                        <label class="notification-send-confirm">
                            <input
                                type="checkbox"
                                name="confirm_dispatch"
                                value="1"
                                required
                            >
                            <span>
                                <?php if ($sendApprovalRequired): ?>
                                    مقصدها و متن را بررسی کردم و
                                    ثبت درخواست ارسال برای تأیید
                                    را تأیید می‌کنم.
                                <?php else: ?>
                                    مقصدها و متن را بررسی کردم و
                                    ارسال واقعی را تأیید می‌کنم.
                                <?php endif; ?>
                            </span>
                        </label>
                    </section>

                    <div class="notification-send-actions">
                        <button
                            class="admin-button"
                            type="submit"
                            data-send-submit
                        >
                            <?= $sendApprovalRequired
                                ? 'ثبت درخواست برای تأیید'
                                : 'ارسال واقعی اعلان' ?>
                        </button>
                        <a
                            class="admin-button admin-button--soft"
                            href="/admin/communications/settings?section=reports"
                        >
                            گزارش ارسال‌ها
                        </a>
                    </div>
                </form>

                <script>
                (() => {
                    const root = document.querySelector(
                        '[data-notification-send-center]'
                    );

                    if (!root) {
                        return;
                    }

                    const form = root.querySelector(
                        '[data-notification-send-form]'
                    );
                    const users = Array.from(
                        root.querySelectorAll(
                            '[data-send-user]'
                        )
                    );
                    const channels = Array.from(
                        root.querySelectorAll(
                            '[data-send-channel]'
                        )
                    );
                    const search = root.querySelector(
                        '[data-send-user-search]'
                    );
                    const organization = root.querySelector(
                        '[data-send-user-organization]'
                    );
                    const role = root.querySelector(
                        '[data-send-user-role]'
                    );
                    const city = root.querySelector(
                        '[data-send-user-city]'
                    );
                    const selectedCount = root.querySelector(
                        '[data-send-selected-count]'
                    );
                    const estimatedCount = root.querySelector(
                        '[data-send-estimated-count]'
                    );
                    const subject = root.querySelector(
                        '[data-send-subject]'
                    );
                    const limit = <?= (int) $sendLimit ?>;
                    const digits = new Intl.NumberFormat(
                        'fa-IR'
                    );

                    const selectedChannels = () =>
                        channels
                            .filter((item) => item.checked)
                            .map((item) => item.value);

                    const applyUserFilters = () => {
                        const needle = (
                            search?.value || ''
                        ).trim().toLocaleLowerCase('fa');
                        const wantedOrganization =
                            organization?.value || '';
                        const wantedRole =
                            role?.value || '';
                        const wantedCity =
                            city?.value || '';

                        users.forEach((user) => {
                            user.hidden = !(
                                (
                                    needle === ''
                                    || user.dataset.search
                                        .includes(needle)
                                )
                                && (
                                    wantedOrganization === ''
                                    || user.dataset.organization
                                        === wantedOrganization
                                )
                                && (
                                    wantedRole === ''
                                    || user.dataset.role
                                        .includes(wantedRole)
                                )
                                && (
                                    wantedCity === ''
                                    || user.dataset.city
                                        === wantedCity
                                )
                            );
                        });
                    };

                    const manualCount = (channel) => {
                        const field = root.querySelector(
                            '[name="manual_'
                            + channel
                            + '"]'
                        );

                        if (!field) {
                            return 0;
                        }

                        return new Set(
                            (field.value || '')
                                .split(/[\r\n,;،]+/u)
                                .map((item) => item.trim())
                                .filter(Boolean)
                        ).size;
                    };

                    const refresh = () => {
                        const activeChannels =
                            selectedChannels();
                        const selectedUsers = users.filter(
                            (user) => user.querySelector(
                                '[data-send-user-checkbox]'
                            )?.checked
                        );
                        let estimated = 0;

                        selectedUsers.forEach((user) => {
                            activeChannels.forEach(
                                (channel) => {
                                    if (
                                        user.dataset[channel]
                                        === '1'
                                    ) {
                                        estimated++;
                                    }
                                }
                            );
                        });

                        activeChannels.forEach((channel) => {
                            estimated += manualCount(channel);
                        });

                        if (selectedCount) {
                            selectedCount.textContent =
                                digits.format(
                                    selectedUsers.length
                                );
                        }

                        if (estimatedCount) {
                            estimatedCount.textContent =
                                digits.format(estimated);
                            estimatedCount.closest(
                                'strong'
                            )?.classList.toggle(
                                'is-over-limit',
                                estimated > limit
                            );
                        }

                        channels.forEach((channel) => {
                            const card = channel.closest(
                                '[data-send-channel-card]'
                            );
                            card?.classList.toggle(
                                'is-active',
                                channel.checked
                            );

                            const manual = root.querySelector(
                                '[data-send-manual-channel="'
                                + channel.value
                                + '"]'
                            );

                            if (manual) {
                                manual.hidden =
                                    !channel.checked;
                            }
                        });

                        if (subject) {
                            subject.required =
                                activeChannels.includes(
                                    'email'
                                );
                        }
                    };

                    search?.addEventListener(
                        'input',
                        applyUserFilters
                    );
                    organization?.addEventListener(
                        'change',
                        applyUserFilters
                    );
                    role?.addEventListener(
                        'change',
                        applyUserFilters
                    );
                    city?.addEventListener(
                        'change',
                        applyUserFilters
                    );

                    root.querySelector(
                        '[data-send-select-visible]'
                    )?.addEventListener('click', () => {
                        users
                            .filter((user) => !user.hidden)
                            .forEach((user) => {
                                const checkbox =
                                    user.querySelector(
                                        '[data-send-user-checkbox]'
                                    );

                                if (checkbox) {
                                    checkbox.checked = true;
                                }
                            });
                        refresh();
                    });

                    root.querySelector(
                        '[data-send-clear-users]'
                    )?.addEventListener('click', () => {
                        users.forEach((user) => {
                            const checkbox =
                                user.querySelector(
                                    '[data-send-user-checkbox]'
                                );

                            if (checkbox) {
                                checkbox.checked = false;
                            }
                        });
                        refresh();
                    });

                    form?.addEventListener(
                        'input',
                        refresh
                    );
                    form?.addEventListener(
                        'change',
                        refresh
                    );
                    form?.addEventListener(
                        'submit',
                        (event) => {
                            const estimated = Number(
                                (
                                    estimatedCount
                                        ?.textContent
                                    || '۰'
                                ).replace(
                                    /[۰-۹]/g,
                                    (digit) =>
                                        '۰۱۲۳۴۵۶۷۸۹'
                                            .indexOf(digit)
                                )
                            );

                            if (estimated > limit) {
                                event.preventDefault();
                                window.alert(
                                    'تعداد تحویل‌ها از سقف '
                                    + digits.format(limit)
                                    + ' بیشتر است.'
                                );
                            }
                        }
                    );

                    applyUserFilters();
                    refresh();
                })();
                </script>
                <!-- notification-send-tabs-v061 -->
                <script>
                (() => {
                    const root = document.querySelector(
                        '[data-notification-send-center]'
                    );
                    const form = root?.querySelector(
                        '[data-notification-send-form]'
                    );

                    if (!root || !form) return;

                    const sections = Array.from(
                        form.querySelectorAll(
                            '.notification-send-section'
                        )
                    );
                    const review = form.querySelector(
                        '.notification-send-review'
                    );

                    if (
                        sections.length < 4
                        || !review
                        || form.querySelector(
                            '[data-send-step-tabs]'
                        )
                    ) {
                        return;
                    }

                    const typeBlock =
                        document.createElement('section');
                    typeBlock.className =
                        'notification-send-message-types';
                    typeBlock.innerHTML = `
                        <h3>نوع پیام</h3>
                        <div class="notification-send-type-grid">
                            <label class="notification-send-type is-active">
                                <input type="radio"
                                    name="message_type_code"
                                    value="text" checked
                                    data-send-message-type>
                                <span>
                                    <strong>پیام متنی</strong>
                                    <small>ایمیل، پیام کوتاه و بله</small>
                                </span>
                            </label>
                            <label class="notification-send-type">
                                <input type="radio"
                                    name="message_type_code"
                                    value="multimedia"
                                    data-send-message-type>
                                <span>
                                    <strong>پیام چندرسانه‌ای</strong>
                                    <small>ایمیل و بله؛ پیامک غیرفعال</small>
                                </span>
                            </label>
                        </div>
                        <p class="notification-send-media-note"
                            data-send-media-note hidden>
                            تصویر، ویدئو، صوت و سند پیش‌بینی شده است.
                            پیامک در این حالت قابل انتخاب نیست.
                        </p>
                    `;
                    sections[0].prepend(typeBlock);

                    const mediaBlock =
                        document.createElement('section');
                    mediaBlock.className =
                        'notification-send-media-foundation';
                    mediaBlock.hidden = true;
                    mediaBlock.dataset.sendMediaFoundation = '';
                    mediaBlock.innerHTML = `
                        <header class="notification-send-media-header">
                            <div>
                                <h3>فایل‌های پیوست</h3>
                                <p>تصویر، ویدئو، صوت یا سند</p>
                            </div>
                            <span data-send-media-file-count>
                                ۰ فایل
                            </span>
                        </header>

                        <div
                            class="notification-send-dropzone"
                            data-send-dropzone
                            tabindex="0"
                            role="button"
                            aria-label="انتخاب فایل‌های چندرسانه‌ای"
                        >
                            <span class="notification-send-dropzone__icon">＋</span>
                            <div>
                                <strong>فایل را انتخاب یا اینجا رها کنید</strong>
                                <small>
                                    حداکثر ۵ فایل؛ هر فایل ۱۰ مگابایت
                                </small>
                            </div>
                            <label class="notification-send-file-trigger">
                                انتخاب فایل
                                <input type="file"
                                    name="media_files[]" multiple
                                    data-send-media-files
                                    accept=".jpg,.jpeg,.png,.webp,.mp4,.mp3,.m4a,.ogg,.pdf,.docx,.xlsx,.txt">
                            </label>
                        </div>

                        <div
                            class="notification-send-media-feedback"
                            data-send-media-feedback
                            aria-live="polite"
                        >
                            هنوز فایلی انتخاب نشده است.
                        </div>

                        <div class="notification-send-media-preview"
                            data-send-media-preview></div>

                        <p class="notification-send-media-limits">
                            مجموع فایل‌ها حداکثر ۳۰ مگابایت
                        </p>
                    `;
                    sections[3].append(mediaBlock);

                    const manualMessenger =
                        sections[2].querySelector(
                            '[data-send-manual-channel="messenger"]'
                        );
                    manualMessenger?.remove();


                    const panels = [
                        sections[0],
                        sections[1],
                        sections[2],
                        sections[3],
                        review
                    ];
                    const titles = [
                        'کانال',
                        'گیرندگان',
                        'مقصد دستی',
                        'محتوا',
                        'بازبینی'
                    ];
                    const tabs =
                        document.createElement('nav');
                    tabs.className =
                        'notification-send-step-tabs';
                    tabs.dataset.sendStepTabs = '';

                    titles.forEach((title, index) => {
                        const button =
                            document.createElement('button');
                        button.type = 'button';
                        button.dataset.sendStepTab =
                            String(index + 1);
                        button.innerHTML =
                            '<span>'
                            + new Intl.NumberFormat('fa-IR')
                                .format(index + 1)
                            + '</span>'
                            + title;
                        tabs.append(button);
                    });

                    form.insertBefore(
                        tabs,
                        panels[0]
                    );

                    const actions =
                        form.querySelector(
                            '.notification-send-actions'
                        );
                    const originalSubmit =
                        actions?.querySelector(
                            '[type="submit"]'
                        );
                    const previous =
                        document.createElement('button');
                    const next =
                        document.createElement('button');

                    previous.type = 'button';
                    previous.className =
                        'admin-button admin-button--soft';
                    previous.textContent = 'مرحله قبل';
                    previous.dataset.sendPrevious = '';

                    next.type = 'button';
                    next.className = 'admin-button';
                    next.textContent = 'مرحله بعد';
                    next.dataset.sendNext = '';

                    actions?.prepend(previous, next);

                    let step = 1;

                    const showStep = (value) => {
                        step = Math.max(
                            1,
                            Math.min(5, value)
                        );

                        panels.forEach((panel, index) => {
                            const active =
                                index + 1 === step;
                            panel.hidden = !active;
                            panel.classList.toggle(
                                'is-active',
                                active
                            );
                        });

                        Array.from(tabs.children)
                            .forEach((tab, index) => {
                                tab.classList.toggle(
                                    'is-active',
                                    index + 1 === step
                                );
                            });

                        previous.hidden = step === 1;
                        next.hidden = step === 5;
                        if (originalSubmit) {
                            originalSubmit.hidden =
                                step !== 5;
                        }
                    };

                    Array.from(tabs.children)
                        .forEach((tab) => {
                            tab.addEventListener(
                                'click',
                                () => {
                                    const target = Number(
                                        tab.dataset.sendStepTab
                                    );

                                    if (
                                        target === 5
                                        && !validateContent()
                                    ) {
                                        return;
                                    }

                                    showStep(target);
                                }
                            );
                        });

                    previous.addEventListener(
                        'click',
                        () => showStep(step - 1)
                    );
                    next.addEventListener(
                        'click',
                        () => {
                            if (
                                step === 4
                                && !validateContent()
                            ) {
                                return;
                            }

                            showStep(step + 1);
                        }
                    );

                    form.addEventListener(
                        'submit',
                        (event) => {
                            if (!validateContent()) {
                                event.preventDefault();
                                showStep(4);
                            }
                        }
                    );

                    const messageTypes = Array.from(
                        form.querySelectorAll(
                            '[data-send-message-type]'
                        )
                    );
                    const channels = Array.from(
                        form.querySelectorAll(
                            '[data-send-channel]'
                        )
                    );
                    const sms = channels.find(
                        (item) => item.value === 'sms'
                    );
                    const warning =
                        form.querySelector(
                            '[data-send-media-note]'
                        );
                    const media =
                        form.querySelector(
                            '[data-send-media-foundation]'
                        );
                    const mediaInput =
                        form.querySelector(
                            '[data-send-media-files]'
                        );
                    const mediaPreview =
                        form.querySelector(
                            '[data-send-media-preview]'
                        );
                    const mediaCount =
                        form.querySelector(
                            '[data-send-media-file-count]'
                        );
                    const mediaFeedback =
                        form.querySelector(
                            '[data-send-media-feedback]'
                        );
                    const dropzone =
                        form.querySelector(
                            '[data-send-dropzone]'
                        );
                    const contentStep =
                        form.querySelector(
                            '[data-send-content-step]'
                        );
                    const subjectField =
                        form.querySelector(
                            '[data-send-subject-field]'
                        );
                    const bodyInput =
                        form.querySelector(
                            '[data-send-body]'
                        );
                    const bodyCount =
                        form.querySelector(
                            '[data-send-body-count]'
                        );
                    const contentStatus =
                        form.querySelector(
                            '[data-send-content-status]'
                        );
                    const subject =
                        form.querySelector(
                            '[data-send-subject]'
                        );
                    const digits =
                        new Intl.NumberFormat('fa-IR');
                    const formatBytes = (bytes) => {
                        const size = Number(bytes) || 0;

                        if (size < 1024 * 1024) {
                            return digits.format(
                                Math.max(
                                    1,
                                    Math.ceil(size / 1024)
                                )
                            ) + ' کیلوبایت';
                        }

                        return new Intl.NumberFormat(
                            'fa-IR',
                            {
                                maximumFractionDigits: 1,
                            }
                        ).format(
                            size / (1024 * 1024)
                        ) + ' مگابایت';
                    };
                    const maxFiles = 5;
                    const maxFileBytes =
                        10 * 1024 * 1024;
                    const maxTotalBytes =
                        30 * 1024 * 1024;
                    let selectedFiles = [];

                    const refreshType = () => {
                        const type =
                            messageTypes.find(
                                (item) => item.checked
                            )?.value || 'text';
                        const multimedia =
                            type === 'multimedia';
                        const emailSelected =
                            channels.some(
                                (item) =>
                                    item.value === 'email'
                                    && item.checked
                            );

                        if (sms) {
                            if (multimedia) {
                                sms.checked = false;
                            }
                            sms.disabled = multimedia;
                            sms.closest(
                                '[data-send-channel-card]'
                            )?.classList.toggle(
                                'is-disabled',
                                multimedia
                            );
                        }

                        warning.hidden = !multimedia;
                        media.hidden = !multimedia;
                        contentStep?.classList.toggle(
                            'has-media',
                            multimedia
                        );

                        if (subjectField) {
                            subjectField.hidden =
                                !emailSelected;
                        }

                        if (subject) {
                            subject.required =
                                emailSelected;
                        }

                        if (contentStatus) {
                            contentStatus.textContent =
                                multimedia
                                    ? digits.format(
                                        selectedFiles.length
                                    ) + ' فایل پیوست'
                                    : 'پیام متنی';
                        }

                        messageTypes.forEach((item) => {
                            item.closest(
                                '.notification-send-type'
                            )?.classList.toggle(
                                'is-active',
                                item.checked
                            );
                        });
                    };

                    const syncInputFiles = () => {
                        if (
                            !mediaInput
                            || typeof DataTransfer
                                === 'undefined'
                        ) {
                            return;
                        }

                        const transfer =
                            new DataTransfer();

                        selectedFiles.forEach((file) => {
                            transfer.items.add(file);
                        });

                        mediaInput.files = transfer.files;
                    };

                    const validateFiles = (files) => {
                        if (files.length > maxFiles) {
                            return 'حداکثر '
                                + digits.format(maxFiles)
                                + ' فایل مجاز است.';
                        }

                        if (files.some(
                            (file) =>
                                file.size > maxFileBytes
                        )) {
                            return 'حجم هر فایل باید حداکثر '
                                + '۱۰ مگابایت باشد.';
                        }

                        const total = files.reduce(
                            (sum, file) =>
                                sum + file.size,
                            0
                        );

                        if (total > maxTotalBytes) {
                            return 'مجموع فایل‌ها باید حداکثر '
                                + '۳۰ مگابایت باشد.';
                        }

                        return '';
                    };

                    const renderFiles = () => {
                        if (!mediaPreview) {
                            return;
                        }

                        mediaPreview.innerHTML = '';

                        selectedFiles.forEach(
                            (file, index) => {
                                const item =
                                    document.createElement(
                                        'article'
                                    );
                                const extension = (
                                    file.name
                                        .split('.')
                                        .pop()
                                    || 'فایل'
                                ).toUpperCase();

                                item.innerHTML = `
                                    <span class="notification-send-media-preview__type"></span>
                                    <span class="notification-send-media-preview__info">
                                        <strong></strong>
                                        <small></small>
                                    </span>
                                    <button
                                        type="button"
                                        aria-label="حذف فایل"
                                    >
                                        حذف
                                    </button>
                                `;

                                item.querySelector(
                                    '.notification-send-media-preview__type'
                                ).textContent = extension;
                                item.querySelector(
                                    'strong'
                                ).textContent = file.name;
                                item.querySelector(
                                    'small'
                                ).textContent =
                                    formatBytes(file.size);
                                item.querySelector(
                                    'button'
                                )?.addEventListener(
                                    'click',
                                    () => {
                                        selectedFiles.splice(
                                            index,
                                            1
                                        );
                                        syncInputFiles();
                                        renderFiles();
                                        refreshType();
                                    }
                                );

                                mediaPreview.append(item);
                            }
                        );

                        if (mediaCount) {
                            mediaCount.textContent =
                                digits.format(
                                    selectedFiles.length
                                ) + ' فایل';
                        }

                        if (mediaFeedback) {
                            const total = selectedFiles
                                .reduce(
                                    (sum, file) =>
                                        sum + file.size,
                                    0
                                );

                            mediaFeedback.textContent =
                                selectedFiles.length > 0
                                    ? digits.format(
                                        selectedFiles.length
                                    )
                                        + ' فایل با مجموع '
                                        + formatBytes(total)
                                        + ' آماده ارسال است.'
                                    : 'هنوز فایلی انتخاب نشده است.';
                            mediaFeedback.classList.toggle(
                                'is-ready',
                                selectedFiles.length > 0
                            );
                        }
                    };

                    const setFiles = (files) => {
                        const normalized =
                            Array.from(files || []);
                        const error =
                            validateFiles(normalized);

                        if (error !== '') {
                            window.alert(error);
                            selectedFiles = [];

                            if (mediaInput) {
                                mediaInput.value = '';
                            }

                            renderFiles();
                            refreshType();
                            return;
                        }

                        selectedFiles = normalized;
                        syncInputFiles();
                        renderFiles();
                        refreshType();
                    };

                    mediaInput?.addEventListener(
                        'change',
                        () => setFiles(
                            mediaInput.files || []
                        )
                    );

                    dropzone?.addEventListener(
                        'dragover',
                        (event) => {
                            event.preventDefault();
                            dropzone.classList.add(
                                'is-dragging'
                            );
                        }
                    );
                    dropzone?.addEventListener(
                        'dragleave',
                        () => dropzone.classList.remove(
                            'is-dragging'
                        )
                    );
                    dropzone?.addEventListener(
                        'drop',
                        (event) => {
                            event.preventDefault();
                            dropzone.classList.remove(
                                'is-dragging'
                            );
                            setFiles(
                                event.dataTransfer?.files
                                || []
                            );
                        }
                    );
                    dropzone?.addEventListener(
                        'keydown',
                        (event) => {
                            if (
                                event.key === 'Enter'
                                || event.key === ' '
                            ) {
                                event.preventDefault();
                                mediaInput?.click();
                            }
                        }
                    );

                    bodyInput?.addEventListener(
                        'input',
                        () => {
                            if (bodyCount) {
                                bodyCount.textContent =
                                    digits.format(
                                        bodyInput.value.length
                                    );
                            }
                        }
                    );

                    const validateContent = () => {
                        const type =
                            messageTypes.find(
                                (item) => item.checked
                            )?.value || 'text';
                        const emailSelected =
                            channels.some(
                                (item) =>
                                    item.value === 'email'
                                    && item.checked
                            );

                        if (
                            emailSelected
                            && (subject?.value || '')
                                .trim() === ''
                        ) {
                            window.alert(
                                'برای ارسال ایمیل، موضوع '
                                + 'را وارد کنید.'
                            );
                            subject?.focus();
                            return false;
                        }

                        if (
                            (bodyInput?.value || '')
                                .trim() === ''
                        ) {
                            window.alert(
                                'متن اعلان را وارد کنید.'
                            );
                            bodyInput?.focus();
                            return false;
                        }

                        if (
                            type === 'multimedia'
                            && selectedFiles.length < 1
                        ) {
                            window.alert(
                                'برای پیام چندرسانه‌ای '
                                + 'حداقل یک فایل انتخاب کنید.'
                            );
                            mediaInput?.focus();
                            return false;
                        }

                        return true;
                    };

                    form.addEventListener(
                        'change',
                        refreshType
                    );

                    renderFiles();

                    if (bodyCount && bodyInput) {
                        bodyCount.textContent =
                            digits.format(
                                bodyInput.value.length
                            );
                    }

                    refreshType();
                    showStep(1);
                })();
                </script>
                <!-- notification-send-minimal-overview-v061 -->
                <script>
                (() => {
                    const root = document.querySelector(
                        '[data-notification-send-center]'
                    );
                    const form = root?.querySelector(
                        '[data-notification-send-form]'
                    );
                    const tabs = form?.querySelector(
                        '[data-send-step-tabs]'
                    );

                    if (
                        !root
                        || !form
                        || !tabs
                        || form.querySelector(
                            '[data-send-live-summary]'
                        )
                    ) {
                        return;
                    }

                    const summary =
                        document.createElement('section');

                    summary.className =
                        'notification-send-live-summary';
                    summary.dataset.sendLiveSummary = '';
                    summary.setAttribute(
                        'aria-live',
                        'polite'
                    );
                    summary.innerHTML = `
                        <div>
                            <span>مرحله جاری</span>
                            <strong data-send-overview-step>
                                ۱ از ۵
                            </strong>
                        </div>
                        <div>
                            <span>نوع پیام</span>
                            <strong data-send-overview-type>
                                متنی
                            </strong>
                        </div>
                        <div>
                            <span>کانال‌ها</span>
                            <strong data-send-overview-channels>
                                انتخاب نشده
                            </strong>
                        </div>
                        <div>
                            <span>گیرندگان و تحویل</span>
                            <strong data-send-overview-targets>
                                ۰ کاربر · ۰ تحویل
                            </strong>
                        </div>
                    `;

                    tabs.insertAdjacentElement(
                        'afterend',
                        summary
                    );

                    const stepView = summary.querySelector(
                        '[data-send-overview-step]'
                    );
                    const typeView = summary.querySelector(
                        '[data-send-overview-type]'
                    );
                    const channelView = summary.querySelector(
                        '[data-send-overview-channels]'
                    );
                    const targetView = summary.querySelector(
                        '[data-send-overview-targets]'
                    );
                    const digits = new Intl.NumberFormat(
                        'fa-IR'
                    );
                    const channelLabels = {
                        email: 'ایمیل',
                        sms: 'پیامک',
                        messenger: 'بله',
                    };

                    const toNumber = (value) =>
                        Number(
                            (value || '')
                                .replace(
                                    /[۰-۹]/g,
                                    (digit) =>
                                        '۰۱۲۳۴۵۶۷۸۹'
                                            .indexOf(digit)
                                )
                                .replace(/[^\d]/g, '')
                        ) || 0;

                    const refresh = () => {
                        const tabItems = Array.from(
                            tabs.querySelectorAll(
                                '[data-send-step-tab]'
                            )
                        );
                        const activeTab = tabItems.find(
                            (tab) =>
                                tab.classList.contains(
                                    'is-active'
                                )
                        ) || tabItems[0];
                        const step = Number(
                            activeTab?.dataset
                                .sendStepTab || 1
                        );
                        const stepTitle = (
                            activeTab?.textContent || ''
                        )
                            .replace(
                                /^\s*[۰-۹0-9]+\s*/u,
                                ''
                            )
                            .trim();

                        tabItems.forEach((tab) => {
                            const active =
                                tab === activeTab;
                            tab.setAttribute(
                                'aria-selected',
                                active
                                    ? 'true'
                                    : 'false'
                            );

                            if (active) {
                                tab.setAttribute(
                                    'aria-current',
                                    'step'
                                );
                            } else {
                                tab.removeAttribute(
                                    'aria-current'
                                );
                            }
                        });

                        stepView.textContent =
                            digits.format(step)
                            + ' از '
                            + digits.format(5)
                            + (
                                stepTitle !== ''
                                    ? ' · ' + stepTitle
                                    : ''
                            );

                        const messageType =
                            form.querySelector(
                                '[data-send-message-type]:checked'
                            )?.value || 'text';

                        const mediaFiles =
                            form.querySelector(
                                '[data-send-media-files]'
                            )?.files?.length || 0;

                        typeView.textContent =
                            messageType === 'multimedia'
                                ? 'چندرسانه‌ای · '
                                    + digits.format(
                                        mediaFiles
                                    )
                                    + ' فایل'
                                : 'متنی';

                        const selectedChannels =
                            Array.from(
                                form.querySelectorAll(
                                    '[data-send-channel]:checked'
                                )
                            ).map(
                                (channel) =>
                                    channelLabels[
                                        channel.value
                                    ] || channel.value
                            );

                        channelView.textContent =
                            selectedChannels.length > 0
                                ? selectedChannels.join('، ')
                                : 'انتخاب نشده';

                        const selectedUsers =
                            form.querySelectorAll(
                                '[data-send-user-checkbox]:checked'
                            ).length;
                        const deliveries = toNumber(
                            form.querySelector(
                                '[data-send-estimated-count]'
                            )?.textContent || '۰'
                        );

                        targetView.textContent =
                            digits.format(selectedUsers)
                            + ' کاربر · '
                            + digits.format(deliveries)
                            + ' تحویل';
                    };

                    form.addEventListener(
                        'input',
                        () => window.setTimeout(
                            refresh,
                            0
                        )
                    );
                    form.addEventListener(
                        'change',
                        () => window.setTimeout(
                            refresh,
                            0
                        )
                    );
                    tabs.addEventListener(
                        'click',
                        () => window.setTimeout(
                            refresh,
                            0
                        )
                    );

                    const observer =
                        new MutationObserver(refresh);

                    observer.observe(
                        tabs,
                        {
                            attributes: true,
                            subtree: true,
                            attributeFilter: ['class'],
                        }
                    );

                    refresh();
                })();
                </script>
            </section>

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
