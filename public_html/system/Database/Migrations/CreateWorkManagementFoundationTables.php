<?php

namespace IPKF\Database\Migrations;

class CreateWorkManagementFoundationTables extends Migration
{
    public function up(): void
    {
        foreach ($this->statements() as $statement) {
            $this->db->exec($statement);
        }
    }

    public function down(): void
    {
    }

    private function statements(): array
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return [
            "CREATE TABLE IF NOT EXISTS work_statuses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(190) NOT NULL,
                category VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                color VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_closed TINYINT(1) NOT NULL DEFAULT 0,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY work_statuses_code_unique (code),
                INDEX work_statuses_active_sort_index (is_active, sort_order)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_projects (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT NULL,
                owner_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                organization_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                organization_snapshot VARCHAR(255) NULL,
                start_date DATE NULL,
                target_date DATE NULL,
                status_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                visibility_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'private',
                created_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                archived_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY work_projects_reference_unique (public_reference),
                UNIQUE KEY work_projects_code_unique (code),
                INDEX work_projects_org_status_index (organization_reference, status_code),
                INDEX work_projects_target_date_index (target_date)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_project_members (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                person_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                display_name_snapshot VARCHAR(255) NOT NULL,
                role_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'member',
                joined_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                left_at TIMESTAMP NULL,
                UNIQUE KEY work_project_members_unique (project_id, user_reference),
                INDEX work_project_members_user_index (user_reference, left_at),
                CONSTRAINT work_project_members_project_fk FOREIGN KEY (project_id)
                    REFERENCES work_projects (id) ON DELETE CASCADE ON UPDATE RESTRICT
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                parent_id BIGINT UNSIGNED NULL,
                status_id BIGINT UNSIGNED NOT NULL,
                item_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                sequence_number BIGINT UNSIGNED NOT NULL,
                title VARCHAR(500) NOT NULL,
                description LONGTEXT NULL,
                priority_code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'normal',
                progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
                start_at DATETIME NULL,
                due_at DATETIME NULL,
                completed_at DATETIME NULL,
                estimate_minutes INT UNSIGNED NULL,
                sort_order BIGINT NOT NULL DEFAULT 0,
                created_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                updated_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                archived_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY work_items_reference_unique (public_reference),
                UNIQUE KEY work_items_project_sequence_unique (project_id, sequence_number),
                INDEX work_items_parent_sort_index (parent_id, sort_order),
                INDEX work_items_project_status_due_index (project_id, status_id, due_at),
                INDEX work_items_type_index (item_type),
                CONSTRAINT work_items_project_fk FOREIGN KEY (project_id)
                    REFERENCES work_projects (id) ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT work_items_parent_fk FOREIGN KEY (parent_id)
                    REFERENCES work_items (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT work_items_status_fk FOREIGN KEY (status_id)
                    REFERENCES work_statuses (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT work_items_progress_check CHECK (progress_percent <= 100),
                CONSTRAINT work_items_type_check CHECK (item_type IN ('work','milestone','task','subtask'))
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_item_assignees (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                work_item_id BIGINT UNSIGNED NOT NULL,
                user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                person_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                display_name_snapshot VARCHAR(255) NOT NULL,
                assignment_role VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'responsible',
                assigned_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                assigned_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                unassigned_at TIMESTAMP NULL,
                UNIQUE KEY work_item_assignees_unique (work_item_id, user_reference, assignment_role),
                INDEX work_item_assignees_user_index (user_reference, unassigned_at),
                CONSTRAINT work_item_assignees_item_fk FOREIGN KEY (work_item_id)
                    REFERENCES work_items (id) ON DELETE CASCADE ON UPDATE RESTRICT
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_item_dependencies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                work_item_id BIGINT UNSIGNED NOT NULL,
                depends_on_item_id BIGINT UNSIGNED NOT NULL,
                dependency_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'finish_to_start',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY work_item_dependencies_unique (work_item_id, depends_on_item_id, dependency_type),
                CONSTRAINT work_item_dependencies_item_fk FOREIGN KEY (work_item_id)
                    REFERENCES work_items (id) ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT work_item_dependencies_target_fk FOREIGN KEY (depends_on_item_id)
                    REFERENCES work_items (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT work_item_dependencies_self_check CHECK (work_item_id <> depends_on_item_id)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_checklist_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                work_item_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(500) NOT NULL,
                is_completed TINYINT(1) NOT NULL DEFAULT 0,
                completed_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                completed_at TIMESTAMP NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX work_checklist_item_sort_index (work_item_id, sort_order),
                CONSTRAINT work_checklist_item_fk FOREIGN KEY (work_item_id)
                    REFERENCES work_items (id) ON DELETE CASCADE ON UPDATE RESTRICT
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_labels (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(190) NOT NULL,
                color VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY work_labels_project_code_unique (project_id, code),
                INDEX work_labels_project_sort_index (project_id, sort_order),
                CONSTRAINT work_labels_project_fk FOREIGN KEY (project_id)
                    REFERENCES work_projects (id) ON DELETE CASCADE ON UPDATE RESTRICT
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_item_labels (
                work_item_id BIGINT UNSIGNED NOT NULL,
                label_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (work_item_id, label_id),
                CONSTRAINT work_item_labels_item_fk FOREIGN KEY (work_item_id)
                    REFERENCES work_items (id) ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT work_item_labels_label_fk FOREIGN KEY (label_id)
                    REFERENCES work_labels (id) ON DELETE CASCADE ON UPDATE RESTRICT
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_attachments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                work_item_id BIGINT UNSIGNED NOT NULL,
                storage_disk VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                storage_key VARCHAR(1000) NOT NULL,
                original_name VARCHAR(500) NOT NULL,
                mime_type VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL,
                checksum_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                scan_status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
                uploaded_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                deleted_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY work_attachments_reference_unique (public_reference),
                INDEX work_attachments_item_created_index (work_item_id, created_at),
                CONSTRAINT work_attachments_item_fk FOREIGN KEY (work_item_id)
                    REFERENCES work_items (id) ON DELETE CASCADE ON UPDATE RESTRICT
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_comments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                work_item_id BIGINT UNSIGNED NOT NULL,
                parent_comment_id BIGINT UNSIGNED NULL,
                body LONGTEXT NOT NULL,
                author_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                author_display_name_snapshot VARCHAR(255) NOT NULL,
                edited_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY work_comments_reference_unique (public_reference),
                INDEX work_comments_item_created_index (work_item_id, created_at),
                CONSTRAINT work_comments_item_fk FOREIGN KEY (work_item_id)
                    REFERENCES work_items (id) ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT work_comments_parent_fk FOREIGN KEY (parent_comment_id)
                    REFERENCES work_comments (id) ON DELETE RESTRICT ON UPDATE RESTRICT
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS work_activity_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                work_item_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                actor_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                actor_display_name_snapshot VARCHAR(255) NOT NULL,
                payload_json LONGTEXT NULL,
                occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX work_activity_project_time_index (project_id, occurred_at),
                INDEX work_activity_item_time_index (work_item_id, occurred_at),
                CONSTRAINT work_activity_project_fk FOREIGN KEY (project_id)
                    REFERENCES work_projects (id) ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT work_activity_item_fk FOREIGN KEY (work_item_id)
                    REFERENCES work_items (id) ON DELETE CASCADE ON UPDATE RESTRICT
            ) {$options}",
        ];
    }
}
