<?php

namespace IPKF\Database\Migrations;

class CreateCommunicationCenterFoundationTables extends Migration
{
    public function up(): void
    {
        $userIdType = $this->referenceColumnType(
            'users',
            'id',
            'BIGINT UNSIGNED'
        );

        foreach ($this->statements($userIdType) as $statement) {
            $this->db->exec($statement);
        }

        $this->addForeignKeys();
    }

    public function down(): void
    {
    }

    private function statements(string $userIdType): array
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci';

        return [
            "CREATE TABLE IF NOT EXISTS admin_navigation_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id BIGINT UNSIGNED NULL,
                shell_key VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'core',
                item_key VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                item_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'link',
                placement_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'sidebar',
                hide_when_badge_empty TINYINT(1) NOT NULL DEFAULT 0,
                title VARCHAR(190) NOT NULL,
                description VARCHAR(500) NULL,
                route_path VARCHAR(500) NOT NULL,
                target_application VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'core',
                icon_code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'dashboard',
                color_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
                permission_mode VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'any',
                permission_codes_json LONGTEXT NULL,
                badge_source VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                active_paths_json LONGTEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY admin_navigation_shell_key_unique (shell_key, item_key),
                INDEX admin_navigation_parent_sort_index (parent_id, is_active, sort_order, id),
                INDEX admin_navigation_shell_sort_index (shell_key, is_active, sort_order, id)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS admin_route_permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                route_pattern VARCHAR(500) NOT NULL,
                http_method VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'GET',
                permission_mode VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'any',
                permission_codes_json LONGTEXT NOT NULL,
                priority INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY admin_route_permissions_unique (route_pattern, http_method),
                INDEX admin_route_permissions_lookup_index (http_method, is_active, priority, id)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS message_recipient_policies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(190) NOT NULL,
                description VARCHAR(1000) NULL,
                evaluator_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                config_json LONGTEXT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                priority INT NOT NULL DEFAULT 0,
                status_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY message_recipient_policies_code_unique (code),
                INDEX message_recipient_policies_active_index (status_code, is_default, priority, id)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS message_conversations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                conversation_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'direct',
                subject VARCHAR(300) NULL,
                created_by_user_id {$userIdType} NOT NULL,
                related_module VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
                related_entity_type VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
                related_entity_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NULL,
                status_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                last_message_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY message_conversations_reference_unique (public_reference),
                INDEX message_conversations_last_message_index (status_code, last_message_at, id)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS message_conversation_participants (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversation_id BIGINT UNSIGNED NOT NULL,
                user_id {$userIdType} NOT NULL,
                participant_role VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'member',
                joined_at DATETIME NOT NULL,
                left_at DATETIME NULL,
                last_read_message_id BIGINT UNSIGNED NULL,
                archived_at DATETIME NULL,
                muted_until DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY message_participants_conversation_user_unique (conversation_id, user_id),
                INDEX message_participants_user_inbox_index (user_id, left_at, archived_at, conversation_id)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS message_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                conversation_id BIGINT UNSIGNED NOT NULL,
                sender_user_id {$userIdType} NULL,
                message_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'text',
                body LONGTEXT NOT NULL,
                reply_to_message_id BIGINT UNSIGNED NULL,
                sent_at DATETIME NOT NULL,
                edited_at DATETIME NULL,
                deleted_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY message_messages_reference_unique (public_reference),
                INDEX message_messages_conversation_time_index (conversation_id, sent_at, id),
                INDEX message_messages_sender_time_index (sender_user_id, sent_at, id)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_provider_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                channel_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(190) NOT NULL,
                driver_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                supports_balance TINYINT(1) NOT NULL DEFAULT 0,
                config_schema_json LONGTEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_provider_types_code_unique (code),
                INDEX notification_provider_types_channel_index (channel_code, is_active, sort_order)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_provider_instances (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                provider_type_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(190) NOT NULL,
                status_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'inactive',
                priority INT NOT NULL DEFAULT 0,
                configuration_json LONGTEXT NULL,
                secret_reference VARCHAR(255) NULL,
                balance_amount DECIMAL(18,4) NULL,
                balance_currency VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL,
                balance_checked_at DATETIME NULL,
                daily_limit INT UNSIGNED NULL,
                monthly_limit INT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_provider_instances_code_unique (code),
                INDEX notification_provider_instances_type_status_index (provider_type_id, status_code, priority, id)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_provider_defaults (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                scope_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'global',
                scope_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '*',
                channel_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                provider_instance_id BIGINT UNSIGNED NOT NULL,
                priority INT NOT NULL DEFAULT 10,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_provider_defaults_unique (scope_type, scope_reference, channel_code, priority),
                INDEX notification_provider_defaults_lookup_index (scope_type, scope_reference, channel_code, is_active, priority)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_provider_balance_snapshots (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                provider_instance_id BIGINT UNSIGNED NOT NULL,
                balance_amount DECIMAL(18,4) NOT NULL,
                balance_currency VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                checked_at DATETIME NOT NULL,
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX notification_provider_balance_time_index (provider_instance_id, checked_at, id)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_event_catalog (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(190) NOT NULL,
                source_module VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description VARCHAR(1000) NULL,
                default_priority VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'normal',
                is_mandatory TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_event_catalog_type_unique (event_type),
                INDEX notification_event_catalog_module_index (source_module, is_active, sort_order)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_routing_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                scope_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'global',
                scope_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '*',
                channel_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                provider_instance_id BIGINT UNSIGNED NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                is_mandatory TINYINT(1) NOT NULL DEFAULT 0,
                priority INT NOT NULL DEFAULT 0,
                conditions_json LONGTEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_routing_rules_unique (
                    event_type, scope_type, scope_reference, channel_code, priority
                ),
                INDEX notification_routing_rules_lookup_index (
                    event_type, scope_type, scope_reference, is_enabled, sort_order
                )
            ) {$options}",
        ];
    }

    private function addForeignKeys(): void
    {
        foreach ([
            ['admin_navigation_items', 'admin_navigation_items_parent_fk', 'parent_id', 'admin_navigation_items', 'id', 'CASCADE'],
            ['message_conversations', 'message_conversations_creator_fk', 'created_by_user_id', 'users', 'id', 'RESTRICT'],
            ['message_conversation_participants', 'message_participants_conversation_fk', 'conversation_id', 'message_conversations', 'id', 'CASCADE'],
            ['message_conversation_participants', 'message_participants_user_fk', 'user_id', 'users', 'id', 'CASCADE'],
            ['message_messages', 'message_messages_conversation_fk', 'conversation_id', 'message_conversations', 'id', 'CASCADE'],
            ['message_messages', 'message_messages_sender_fk', 'sender_user_id', 'users', 'id', 'SET NULL'],
            ['message_messages', 'message_messages_reply_fk', 'reply_to_message_id', 'message_messages', 'id', 'SET NULL'],
            ['notification_provider_instances', 'notification_provider_instances_type_fk', 'provider_type_id', 'notification_provider_types', 'id', 'CASCADE'],
            ['notification_provider_defaults', 'notification_provider_defaults_instance_fk', 'provider_instance_id', 'notification_provider_instances', 'id', 'CASCADE'],
            ['notification_provider_balance_snapshots', 'notification_provider_balance_instance_fk', 'provider_instance_id', 'notification_provider_instances', 'id', 'CASCADE'],
            ['notification_routing_rules', 'notification_routing_rules_provider_fk', 'provider_instance_id', 'notification_provider_instances', 'id', 'SET NULL'],
        ] as $foreignKey) {
            $this->addForeignKeyIfPossible(...$foreignKey);
        }
    }

    private function referenceColumnType(
        string $table,
        string $column,
        string $default
    ): string {
        if (
            !$this->tableExists($table)
            || !$this->columnExists($table, $column)
        ) {
            return $default;
        }

        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);
        $type = strtoupper(trim((string) $statement->fetchColumn()));

        return preg_match(
            '/^(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)(\\(\\d+\\))?( UNSIGNED)?$/',
            $type
        ) === 1 ? $type : $default;
    }

    private function addForeignKeyIfPossible(
        string $table,
        string $constraint,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete
    ): void {
        if (
            !$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || !$this->columnExists($table, $column)
            || !$this->columnExists($referenceTable, $referenceColumn)
            || $this->foreignKeyExists($table, $constraint)
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys($referenceTable)
            || $this->columnType($table, $column)
                !== $this->columnType($referenceTable, $referenceColumn)
        ) {
            return;
        }

        $onDelete = in_array(
            $onDelete,
            ['CASCADE', 'SET NULL', 'RESTRICT'],
            true
        ) ? $onDelete : 'RESTRICT';

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column})
            REFERENCES {$referenceTable} ({$referenceColumn})
            ON UPDATE CASCADE
            ON DELETE {$onDelete}
        ");
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

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnType(string $table, string $column): string
    {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);

        return strtolower(trim((string) $statement->fetchColumn()));
    }

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower(trim((string) $statement->fetchColumn()))
            === 'innodb';
    }

    private function foreignKeyExists(
        string $table,
        string $constraint
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type = 'FOREIGN KEY'
        ");
        $statement->execute([$table, $constraint]);

        return (int) $statement->fetchColumn() > 0;
    }
}
