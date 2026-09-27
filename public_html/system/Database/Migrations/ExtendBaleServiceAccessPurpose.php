<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

/** Add one select choice to the existing Bale provider definition. */
class ExtendBaleServiceAccessPurpose extends Migration
{
    public function up(): void
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare("
                SELECT config_schema_json
                FROM notification_provider_types
                WHERE code = 'bale_bot'
                LIMIT 1
                FOR UPDATE
            ");
            $statement->execute();
            $stored = $statement->fetchColumn();
            if (!is_string($stored)) {
                throw new RuntimeException('bale_provider_type_missing');
            }
            $schema = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($schema) || !array_is_list($schema)) {
                throw new RuntimeException('bale_provider_schema_invalid');
            }

            $purposeIndexes = [];
            foreach ($schema as $index => $field) {
                if (is_array($field)
                    && ($field['key'] ?? null) === 'bot_purpose_code'
                ) {
                    $purposeIndexes[] = $index;
                }
            }
            if (count($purposeIndexes) !== 1) {
                throw new RuntimeException('bale_purpose_schema_ambiguous');
            }
            $index = $purposeIndexes[0];
            $field = $schema[$index];
            if (($field['type'] ?? null) !== 'select'
                || !is_array($field['options'] ?? null)
                || !in_array('notifications', $field['options'], true)
                || !in_array('membership_auth', $field['options'], true)
            ) {
                throw new RuntimeException('bale_purpose_schema_unexpected');
            }
            $changed = false;
            if (!in_array('service_access', $field['options'], true)) {
                $field['options'][] = 'service_access';
                $changed = true;
            }
            if (!is_array($field['option_labels'] ?? null)) {
                $field['option_labels'] = [];
            }
            if (($field['option_labels']['service_access'] ?? null)
                !== 'دسترسی به خدمات'
            ) {
                $field['option_labels']['service_access'] =
                    'دسترسی به خدمات';
                $changed = true;
            }
            if ($changed) {
                $schema[$index] = $field;
                $update = $this->db->prepare("
                    UPDATE notification_provider_types
                    SET config_schema_json = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE code = 'bale_bot'
                ");
                $update->execute([json_encode(
                    $schema,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                )]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('bale_schema_update_failed');
                }
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function down(): void
    {
        // Deliberate no-op: never remove a configured purpose from live instances.
    }
}
