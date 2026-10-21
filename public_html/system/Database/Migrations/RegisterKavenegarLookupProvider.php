<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

class RegisterKavenegarLookupProvider extends Migration
{
    public function up(): void
    {
        $exists = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'notification_provider_types'
        ");
        $exists->execute();
        if ((int) $exists->fetchColumn() !== 1) {
            return;
        }

        $schema = [
            ['key' => 'register_template', 'type' => 'text', 'required' => true],
            ['key' => 'login_template', 'type' => 'text', 'required' => true],
            ['key' => 'approval_template', 'type' => 'text', 'required' => true],
        ];

        $statement = $this->db->prepare("
            INSERT INTO notification_provider_types (
                code, channel_code, title, driver_code,
                supports_balance, config_schema_json,
                sort_order, is_active, created_at, updated_at
            )
            VALUES (
                'kavenegar_lookup', 'sms', ?, 'kavenegar_lookup',
                0, ?, 105, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                channel_code = VALUES(channel_code),
                driver_code = VALUES(driver_code),
                supports_balance = VALUES(supports_balance),
                config_schema_json = VALUES(config_schema_json),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute([
            'کاوه‌نگار - Lookup خدماتی',
            json_encode(
                $schema,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    public function down(): void
    {
        // Additive catalog registration; existing accounts are preserved.
    }
}
