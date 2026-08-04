<?php

namespace IPKF\Database\Migrations;

class ExpandNotificationProviderCatalog extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('notification_provider_types')) {
            return;
        }

        $this->updateChannelTitles();

        $statement = $this->db->prepare("
            INSERT INTO notification_provider_types (
                code,
                channel_code,
                title,
                driver_code,
                supports_balance,
                config_schema_json,
                sort_order,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, 1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                channel_code = VALUES(channel_code),
                title = VALUES(title),
                driver_code = VALUES(driver_code),
                supports_balance = VALUES(supports_balance),
                config_schema_json = VALUES(config_schema_json),
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($this->providers() as $provider) {
            [
                $code,
                $channel,
                $title,
                $driver,
                $supportsBalance,
                $schema,
                $sortOrder,
            ] = $provider;

            $statement->execute([
                $code,
                $channel,
                $title,
                $driver,
                $supportsBalance,
                json_encode(
                    $schema,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
                $sortOrder,
            ]);
        }
    }

    public function down(): void
    {
    }

    private function updateChannelTitles(): void
    {
        if (!$this->tableExists('notification_channels')) {
            return;
        }

        $statement = $this->db->prepare("
            UPDATE notification_channels
            SET
                title = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = 'sms'
        ");

        $statement->execute([
            'پیام کوتاه (SMS)',
        ]);
    }

    private function providers(): array
    {
        return [
            ['gmail_smtp', 'email', 'Gmail', 'smtp', 0, [
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select',
                    'options' => ['none', 'tls', 'ssl']],
                ['key' => 'username', 'type' => 'text', 'required' => true],
                ['key' => 'from_address', 'type' => 'email', 'required' => true],
            ], 10],
            ['yahoo_smtp', 'email', 'Yahoo Mail', 'smtp', 0, [
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select',
                    'options' => ['none', 'tls', 'ssl']],
                ['key' => 'username', 'type' => 'text', 'required' => true],
                ['key' => 'from_address', 'type' => 'email', 'required' => true],
            ], 20],
            ['microsoft365_smtp', 'email', 'Microsoft 365 / Outlook', 'smtp', 0, [
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select',
                    'options' => ['none', 'tls', 'ssl']],
                ['key' => 'username', 'type' => 'text', 'required' => true],
                ['key' => 'from_address', 'type' => 'email', 'required' => true],
            ], 30],
            ['smtp', 'email', 'SMTP سفارشی / سازمانی', 'smtp', 0, [
                ['key' => 'provider_name', 'type' => 'text'],
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select',
                    'options' => ['none', 'tls', 'ssl']],
                ['key' => 'username', 'type' => 'text'],
                ['key' => 'from_address', 'type' => 'email'],
            ], 40],
            ['kavenegar', 'sms', 'کاوه‌نگار', 'kavenegar', 1, [
                ['key' => 'sender', 'type' => 'text'],
                ['key' => 'balance_endpoint', 'type' => 'url'],
            ], 100],
            ['melipayamak', 'sms', 'ملی پیامک', 'melipayamak', 1, [
                ['key' => 'username', 'type' => 'text'],
                ['key' => 'sender', 'type' => 'text'],
                ['key' => 'endpoint', 'type' => 'url'],
                ['key' => 'balance_endpoint', 'type' => 'url'],
            ], 110],
            ['ippanel', 'sms', 'IPPanel / فراز اس‌ام‌اس', 'ippanel', 1, [
                ['key' => 'sender', 'type' => 'text'],
                ['key' => 'endpoint', 'type' => 'url'],
                ['key' => 'balance_endpoint', 'type' => 'url'],
            ], 120],
            ['generic_sms', 'sms', 'سرویس پیامک سفارشی', 'generic_sms', 1, [
                ['key' => 'provider_name', 'type' => 'text', 'required' => true],
                ['key' => 'endpoint', 'type' => 'url', 'required' => true],
                ['key' => 'username', 'type' => 'text'],
                ['key' => 'sender', 'type' => 'text'],
                ['key' => 'balance_endpoint', 'type' => 'url'],
            ], 130],
            ['bale_bot', 'messenger', 'پیام‌رسان بله', 'bale_bot', 0, [
                ['key' => 'api_base', 'type' => 'url'],
                ['key' => 'bot_username', 'type' => 'text'],
            ], 200],
            ['telegram_bot', 'messenger', 'تلگرام', 'telegram_bot', 0, [
                ['key' => 'api_base', 'type' => 'url'],
                ['key' => 'parse_mode', 'type' => 'select',
                    'options' => ['plain', 'HTML', 'MarkdownV2']],
            ], 210],
            ['eitaa_bot', 'messenger', 'ایتا', 'eitaa_bot', 0, [
                ['key' => 'api_base', 'type' => 'url'],
                ['key' => 'bot_username', 'type' => 'text'],
            ], 220],
            ['whatsapp_cloud', 'messenger', 'WhatsApp Cloud API', 'whatsapp_cloud', 0, [
                ['key' => 'phone_number_id', 'type' => 'text'],
                ['key' => 'business_account_id', 'type' => 'text'],
                ['key' => 'api_version', 'type' => 'text'],
            ], 230],
        ];
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }
}
