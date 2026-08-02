<?php

namespace IPKF\Database\Migrations;

class CreateSecureMessageExtensionTables extends Migration
{
    public function up(): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->db->exec("CREATE TABLE IF NOT EXISTS message_settings (
            setting_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
            setting_value LONGTEXT NOT NULL,
            updated_by_user_id BIGINT UNSIGNED NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$options}");

        $this->db->exec("CREATE TABLE IF NOT EXISTS message_attachments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            message_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            storage_path VARCHAR(1000) NOT NULL,
            mime_type VARCHAR(150) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            extension VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL,
            checksum_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY message_attachments_reference_unique (public_reference),
            INDEX message_attachments_message_index (message_id, id)
        ) {$options}");

        $this->db->exec("CREATE TABLE IF NOT EXISTS message_audit_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            actor_user_id BIGINT UNSIGNED NOT NULL,
            conversation_id BIGINT UNSIGNED NULL,
            message_id BIGINT UNSIGNED NULL,
            attachment_id BIGINT UNSIGNED NULL,
            event_code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            reason VARCHAR(1000) NULL,
            ip_address VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            user_agent VARCHAR(500) NULL,
            occurred_at DATETIME NOT NULL,
            UNIQUE KEY message_audit_reference_unique (public_reference),
            INDEX message_audit_actor_time_index (actor_user_id, occurred_at, id),
            INDEX message_audit_conversation_index (conversation_id, occurred_at, id)
        ) {$options}");

        $defaults = [
            'enabled' => '1', 'attachments_enabled' => '1',
            'attachment_max_files' => '3', 'attachment_max_each_mb' => '10',
            'attachment_max_total_mb' => '20',
            'attachment_extensions' => 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,txt',
            'monitor_reason_required' => '1', 'audit_retention_days' => '3650',
            'login_summary_notification' => '0',
        ];
        $statement = $this->db->prepare('INSERT IGNORE INTO message_settings (setting_key, setting_value) VALUES (?, ?)');
        foreach ($defaults as $key => $value) {
            $statement->execute([$key, $value]);
        }
    }

    public function down(): void
    {
    }
}
