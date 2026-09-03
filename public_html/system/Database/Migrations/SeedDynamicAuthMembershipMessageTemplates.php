<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

class SeedDynamicAuthMembershipMessageTemplates extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'auth.identity.mobile_verification',
                'auth.identity.mobile_verification',
                'sms',
                null,
                "{{brand_name}}\n"
                    . "کد تأیید شماره همراه: {{code}}\n"
                    . "اعتبار کد: {{expires_minutes}} دقیقه.",
                null,
            ],
            [
                'auth.identity.email_verification',
                'auth.identity.email_verification',
                'email',
                'کد تأیید {{brand_name}}',
                "کد تأیید شما: {{code}}\n"
                    . "اعتبار کد: {{expires_minutes}} دقیقه.",
                null,
            ],
            [
                'auth.registration.mobile_otp',
                'auth.registration.mobile_otp',
                'sms',
                null,
                "{{brand_name}}\n"
                    . "کد تأیید ثبت‌نام: {{code}}\n"
                    . "اعتبار کد: {{expires_minutes}} دقیقه.",
                null,
            ],
            [
                'auth.password_reset.mobile_otp',
                'auth.password_reset.mobile_otp',
                'sms',
                null,
                "{{brand_name}}\n"
                    . "کد بازیابی کلمه عبور: {{code}}\n"
                    . "اعتبار کد: {{expires_minutes}} دقیقه.",
                null,
            ],
            [
                'auth.bale.enrollment',
                'auth.bale.enrollment',
                'sms',
                null,
                "برای اتصال حساب بله به {{brand_name}}، "
                    . "لینک زیر را باز کنید:\n{{link}}",
                '{{link}}',
            ],
            [
                'membership.request.received',
                'membership.request.received',
                'in_app',
                'درخواست عضویت دریافت شد',
                'درخواست عضویت شما در {{scope_title}} دریافت شد.',
                '{{action_url}}',
            ],
            [
                'membership.request.approved',
                'membership.request.approved',
                'in_app',
                'عضویت تأیید شد',
                'عضویت شما در {{scope_title}} با نقش '
                    . '{{role_title}} تأیید شد.',
                '{{action_url}}',
            ],
            [
                'membership.request.rejected',
                'membership.request.rejected',
                'in_app',
                'درخواست عضویت رد شد',
                'درخواست عضویت شما در {{scope_title}} رد شد.',
                '{{action_url}}',
            ],
            [
                'membership.role.changed',
                'membership.role.changed',
                'in_app',
                'نقش عضویت تغییر کرد',
                'نقش شما در {{scope_title}} به '
                    . '{{role_title}} تغییر کرد.',
                '{{action_url}}',
            ],
            [
                'membership.revoked',
                'membership.revoked',
                'in_app',
                'عضویت لغو شد',
                'عضویت شما در {{scope_title}} لغو شد.',
                '{{action_url}}',
            ],
            [
                'membership.restored',
                'membership.restored',
                'in_app',
                'عضویت بازگردانی شد',
                'عضویت شما در {{scope_title}} بازگردانی شد.',
                '{{action_url}}',
            ],
        ];

        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO notification_templates (
                    code,
                    event_type,
                    channel_code,
                    locale,
                    title_template,
                    body_template,
                    action_url_template,
                    format_code,
                    version,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    'fa',
                    ?,
                    ?,
                    ?,
                    'plain',
                    1,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

        foreach ($templates as $template) {
            $statement->execute($template);
        }
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         *
         * Templates become administrator-managed content
         * after creation and must not be deleted by rollback.
         */
    }
}
