<?php

namespace IPKF\Database\Migrations;

class CreateWorkManagementFoundationTables extends Migration
{
    public function up(): void
    {
        $statements = [
            "CREATE TABLE IF NOT EXISTS work_projects (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                owner_organization_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                manager_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                starts_on DATE NULL, due_on DATE NULL,
                created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
                UNIQUE KEY work_projects_reference_unique (public_reference),
                UNIQUE KEY work_projects_code_unique (code),
                INDEX work_projects_status_index (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS work_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                parent_work_id BIGINT UNSIGNED NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'planned',
                progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
                starts_on DATE NULL, due_on DATE NULL,
                closed_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
                UNIQUE KEY work_items_reference_unique (public_reference),
                INDEX work_items_project_index (project_id),
                INDEX work_items_parent_index (parent_work_id),
                INDEX work_items_status_index (status),
                CONSTRAINT work_items_project_fk FOREIGN KEY (project_id) REFERENCES work_projects(id),
                CONSTRAINT work_items_parent_fk FOREIGN KEY (parent_work_id) REFERENCES work_items(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS work_milestones (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                work_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                due_on DATE NULL,
                status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'open',
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
                UNIQUE KEY work_milestones_reference_unique (public_reference),
                INDEX work_milestones_work_index (work_id),
                CONSTRAINT work_milestones_work_fk FOREIGN KEY (work_id) REFERENCES work_items(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS work_tasks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                work_id BIGINT UNSIGNED NOT NULL,
                milestone_id BIGINT UNSIGNED NULL,
                parent_task_id BIGINT UNSIGNED NULL,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT NULL,
                task_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'task',
                priority VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'normal',
                status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'backlog',
                progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
                reporter_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                starts_at TIMESTAMP NULL, due_at TIMESTAMP NULL, completed_at TIMESTAMP NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
                UNIQUE KEY work_tasks_reference_unique (public_reference),
                INDEX work_tasks_work_index (work_id),
                INDEX work_tasks_milestone_index (milestone_id),
                INDEX work_tasks_parent_index (parent_task_id),
                INDEX work_tasks_status_priority_index (status, priority),
                CONSTRAINT work_tasks_work_fk FOREIGN KEY (work_id) REFERENCES work_items(id),
                CONSTRAINT work_tasks_milestone_fk FOREIGN KEY (milestone_id) REFERENCES work_milestones(id),
                CONSTRAINT work_tasks_parent_fk FOREIGN KEY (parent_task_id) REFERENCES work_tasks(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS work_members (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                role_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'viewer',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                joined_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
                UNIQUE KEY work_members_project_user_unique (project_id, user_reference),
                INDEX work_members_user_index (user_reference),
                CONSTRAINT work_members_project_fk FOREIGN KEY (project_id) REFERENCES work_projects(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS work_task_assignees (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                task_id BIGINT UNSIGNED NOT NULL,
                user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                assignment_role VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'assignee',
                assigned_at TIMESTAMP NULL, created_at TIMESTAMP NULL,
                UNIQUE KEY work_task_assignees_unique (task_id, user_reference, assignment_role),
                INDEX work_task_assignees_user_index (user_reference),
                CONSTRAINT work_task_assignees_task_fk FOREIGN KEY (task_id) REFERENCES work_tasks(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS work_task_checklist_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                task_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(500) NOT NULL,
                is_completed TINYINT(1) NOT NULL DEFAULT 0,
                completed_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                completed_at TIMESTAMP NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
                INDEX work_checklist_task_index (task_id),
                CONSTRAINT work_checklist_task_fk FOREIGN KEY (task_id) REFERENCES work_tasks(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS work_task_comments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                task_id BIGINT UNSIGNED NOT NULL,
                author_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                body LONGTEXT NOT NULL,
                created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
                INDEX work_comments_task_index (task_id),
                CONSTRAINT work_comments_task_fk FOREIGN KEY (task_id) REFERENCES work_tasks(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS work_attachments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                attachable_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                attachable_id BIGINT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                storage_disk VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'private',
                storage_path VARCHAR(1000) NOT NULL,
                mime_type VARCHAR(150) NULL,
                size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                uploaded_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                created_at TIMESTAMP NULL,
                INDEX work_attachments_owner_index (attachable_type, attachable_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS work_activity_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                actor_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                subject_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                subject_id BIGINT UNSIGNED NOT NULL,
                event_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                before_json LONGTEXT NULL, after_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                INDEX work_activity_project_time_index (project_id, created_at),
                INDEX work_activity_subject_index (subject_type, subject_id),
                CONSTRAINT work_activity_project_fk FOREIGN KEY (project_id) REFERENCES work_projects(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $statement) {
            $this->db->exec($statement);
        }
    }

    public function down(): void
    {
    }
}
