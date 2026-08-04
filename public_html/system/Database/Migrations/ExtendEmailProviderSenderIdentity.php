<?php

namespace IPKF\Database\Migrations;

class ExtendEmailProviderSenderIdentity extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('notification_provider_types')) {
            return;
        }

        $statement = $this->db->prepare("
            UPDATE notification_provider_types
            SET
                config_schema_json = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = ?
              AND channel_code = 'email'
        ");

        foreach ($this->schemas() as $code => $schema) {
            $statement->execute([
                json_encode(
                    $schema,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
                $code,
            ]);
        }
    }

    public function down(): void
    {
    }

    private function schemas(): array
    {
        $standard = [
            ['key' => 'host', 'type' => 'text', 'required' => true],
            ['key' => 'port', 'type' => 'number', 'required' => true],
            ['key' => 'encryption', 'type' => 'select',
                'options' => ['none', 'tls', 'ssl']],
            ['key' => 'username', 'type' => 'text', 'required' => true],
            ['key' => 'from_address', 'type' => 'email', 'required' => true],
            ['key' => 'from_name', 'type' => 'text'],
        ];

        return [
            'gmail_smtp' => $standard,
            'yahoo_smtp' => $standard,
            'microsoft365_smtp' => $standard,
            'smtp' => [
                ['key' => 'provider_name', 'type' => 'text'],
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select',
                    'options' => ['none', 'tls', 'ssl']],
                ['key' => 'username', 'type' => 'text'],
                ['key' => 'from_address', 'type' => 'email'],
                ['key' => 'from_name', 'type' => 'text'],
            ],
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
