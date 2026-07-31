<?php

namespace IPKF\Database\Seeds;

class PlatformCommercialFoundationSeeder extends Seeder
{
    private const APPLICATIONS = [
        ['core', 'IPKF Core', 'platform_core', 'Platform-owned identity, access, organization, geography, registry, and licensing foundation.', 10, 1],
        ['automation', 'Automation', 'specialized', 'Specialized automation application for correspondence and workflow modules.', 20, 1],
        ['work', 'IPKF Work Management', 'specialized', 'Project, work and task management application.', 30, 1],
    ];

    private const MODULES = [
        ['core', 'core.identity', 'Identity', 'core', 10],
        ['core', 'core.access', 'Access and RBAC', 'core', 20],
        ['core', 'core.organization', 'Organization Foundation', 'core', 30],
        ['core', 'core.geography', 'Geography Foundation', 'core', 40],
        ['core', 'core.platform_registry', 'Platform Registry', 'core', 50],
        ['core', 'core.licensing', 'Licensing Foundation', 'core', 60],
        ['automation', 'automation.core', 'Automation Core', 'core', 100],
        ['automation', 'automation.correspondence', 'Correspondence', 'feature', 110],
        ['automation', 'automation.secretariat', 'Secretariat', 'feature', 120],
        ['automation', 'automation.cartable', 'Cartable', 'feature', 130],
        ['automation', 'automation.workflow', 'Workflow', 'feature', 140],
        ['automation', 'automation.forms', 'Forms', 'feature', 150],
        ['automation', 'automation.leave', 'Leave Requests', 'feature', 160],
        ['automation', 'automation.mission', 'Mission Requests', 'feature', 170],
        ['automation', 'automation.procurement_requests', 'Procurement Requests', 'feature', 180],
        ['automation', 'automation.payment_requests', 'Payment Requests', 'feature', 190],
        ['automation', 'automation.check_requests', 'Check Requests', 'feature', 200],
        ['automation', 'automation.document_generation', 'Document Generation', 'feature', 210],
        ['automation', 'automation.archive', 'Archive', 'feature', 220],
        ['automation', 'automation.qr_verification', 'QR Verification', 'feature', 230],
        ['automation', 'automation.digital_signature', 'Digital Signature', 'feature', 240],
        ['automation', 'automation.notifications', 'Notifications', 'feature', 250],
        ['work', 'work.core', 'Work Management Core', 'core', 300],
        ['work', 'work.projects', 'Projects and Works', 'feature', 310],
        ['work', 'work.tasks', 'Tasks and Checklists', 'feature', 320],
        ['work', 'work.collaboration', 'Comments and Attachments', 'feature', 330],
    ];

    private const DEPENDENCIES = [
        ['automation.correspondence', 'automation.core'],
        ['automation.secretariat', 'automation.correspondence'],
        ['automation.cartable', 'automation.correspondence'],
        ['automation.workflow', 'automation.core'],
        ['automation.forms', 'automation.workflow'],
        ['automation.leave', 'automation.forms'],
        ['automation.mission', 'automation.forms'],
        ['automation.procurement_requests', 'automation.forms'],
        ['automation.payment_requests', 'automation.forms'],
        ['automation.check_requests', 'automation.forms'],
        ['automation.document_generation', 'automation.correspondence'],
        ['automation.qr_verification', 'automation.document_generation'],
        ['automation.digital_signature', 'automation.document_generation'],
        ['work.projects', 'work.core'],
        ['work.tasks', 'work.projects'],
        ['work.collaboration', 'work.tasks'],
    ];

    public function run(): void
    {
        if (!$this->tableExists('platform_applications')
            || !$this->tableExists('platform_modules')
            || !$this->tableExists('platform_module_dependencies')
        ) {
            return;
        }

        $this->seedApplications();
        $this->seedModules();
        $this->seedDependencies();
    }

    private function seedApplications(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO platform_applications (
                code, title, owner_scope, description, status, is_system, sort_order, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'active', ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                owner_scope = VALUES(owner_scope),
                description = VALUES(description),
                status = 'active',
                is_system = VALUES(is_system),
                sort_order = VALUES(sort_order),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach (self::APPLICATIONS as $application) {
            $statement->execute($application);
        }
    }

    private function seedModules(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO platform_modules (
                application_id, code, title, module_type, description, status, is_system, sort_order,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, NULL, 'active', 1, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                application_id = VALUES(application_id),
                title = VALUES(title),
                module_type = VALUES(module_type),
                status = 'active',
                is_system = 1,
                sort_order = VALUES(sort_order),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach (self::MODULES as [$applicationCode, $code, $title, $type, $sortOrder]) {
            $applicationId = $this->idFor('platform_applications', $applicationCode);

            if ($applicationId !== null) {
                $statement->execute([$applicationId, $code, $title, $type, $sortOrder]);
            }
        }
    }

    private function seedDependencies(): void
    {
        $statement = $this->db->prepare("
            INSERT IGNORE INTO platform_module_dependencies (
                module_id, depends_on_module_id, dependency_type, status, created_at, updated_at
            ) VALUES (?, ?, 'required', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        foreach (self::DEPENDENCIES as [$moduleCode, $dependencyCode]) {
            $moduleId = $this->idFor('platform_modules', $moduleCode);
            $dependencyId = $this->idFor('platform_modules', $dependencyCode);

            if ($moduleId !== null && $dependencyId !== null && $moduleId !== $dependencyId) {
                $statement->execute([$moduleId, $dependencyId]);
            }
        }
    }

    private function idFor(string $table, string $code): ?int
    {
        $statement = $this->db->prepare("SELECT id FROM {$table} WHERE code = ? LIMIT 1");
        $statement->execute([$code]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
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
