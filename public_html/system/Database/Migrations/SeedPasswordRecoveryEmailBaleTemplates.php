<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

class SeedPasswordRecoveryEmailBaleTemplates extends Migration
{
    private const TEMPLATES_JSON ='[{"code":"auth.password_reset.email_otp","event_type":"auth.password_reset.mobile_otp","channel_code":"email","locale":"fa","title_template":"کد تأیید {{brand_name}}","body_template":"{{brand_name}}\\nکد بازیابی کلمه عبور: {{code}}\\nاعتبار کد: {{expires_minutes}} دقیقه.","action_url_template":null,"format_code":"plain","version":1,"is_active":1},{"code":"auth.password_reset.bale_otp","event_type":"auth.password_reset.mobile_otp","channel_code":"messenger","locale":"fa","title_template":null,"body_template":"{{brand_name}}\\nکد بازیابی کلمه عبور: {{code}}\\nاعتبار کد: {{expires_minutes}} دقیقه.","action_url_template":null,"format_code":"plain","version":1,"is_active":1}]';

    public function up(): void
    {
        $templates =
            json_decode(
                self::TEMPLATES_JSON,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (!is_array($templates)) {
            throw new \RuntimeException(
                'password_recovery_template_payload_invalid'
            );
        }

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
                    is_active
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

        foreach (
            $templates
            as $template
        ) {
            $statement->execute([
                $template['code'],
                $template['event_type'],
                $template['channel_code'],
                $template['locale'],
                $template['title_template'],
                $template['body_template'],
                $template['action_url_template'],
                $template['format_code'],
                $template['version'],
                $template['is_active'],
            ]);
        }
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         * Password-recovery template history is preserved.
         */
    }
}
