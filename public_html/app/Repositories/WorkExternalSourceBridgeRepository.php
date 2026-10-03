<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;

class WorkExternalSourceBridgeRepository
{
    private \PDO $db;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->db = ($connections ?? new ConnectionResolver())->resolve('work.primary');
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->db->inTransaction()) {
            return $callback($this);
        }

        $this->db->beginTransaction();

        try {
            $result = $callback($this);
            $this->db->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function projectByReference(string $publicReference): ?array
    {
        $statement = $this->db->prepare(
            "SELECT
                id,
                public_reference,
                code,
                title,
                status_code,
                visibility_code,
                archived_at
             FROM work_projects
             WHERE public_reference = ?
             LIMIT 1"
        );
        $statement->execute([$publicReference]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function itemByReference(int $projectId, string $publicReference): ?array
    {
        $statement = $this->db->prepare(
            "SELECT
                id,
                public_reference,
                project_id,
                parent_id,
                status_id,
                item_type,
                sequence_number,
                title,
                archived_at
             FROM work_items
             WHERE project_id = ?
               AND public_reference = ?
             LIMIT 1"
        );
        $statement->execute([$projectId, $publicReference]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function bindingsForSource(
        string $moduleCode,
        string $resourceType,
        string $sourceReference,
        string $bindingRoleCode = 'default'
    ): array {
        $statement = $this->db->prepare(
            "SELECT
                b.id,
                b.public_reference,
                b.work_project_id,
                p.public_reference AS work_project_reference,
                p.code AS work_project_code,
                p.title AS work_project_title,
                b.source_module_code,
                b.source_resource_type,
                b.source_reference,
                b.binding_role_code,
                b.is_primary,
                b.status,
                b.metadata_json,
                b.created_by_user_reference,
                b.created_at,
                b.updated_at
             FROM work_project_source_bindings b
             INNER JOIN work_projects p
                ON p.id = b.work_project_id
             WHERE b.source_module_code = ?
               AND b.source_resource_type = ?
               AND b.source_reference = ?
               AND b.binding_role_code = ?
               AND b.status = 'active'
               AND p.archived_at IS NULL
             ORDER BY b.is_primary DESC, b.id"
        );
        $statement->execute([
            $moduleCode,
            $resourceType,
            $sourceReference,
            $bindingRoleCode,
        ]);

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function createProjectBinding(
        int $projectId,
        string $publicReference,
        string $moduleCode,
        string $resourceType,
        string $sourceReference,
        string $bindingRoleCode,
        bool $isPrimary,
        ?string $actorUserReference,
        ?string $metadataJson
    ): int {
        if ($isPrimary) {
            $statement = $this->db->prepare(
                "UPDATE work_project_source_bindings
                 SET is_primary = 0,
                     updated_at = UTC_TIMESTAMP()
                 WHERE source_module_code = ?
                   AND source_resource_type = ?
                   AND source_reference = ?
                   AND binding_role_code = ?
                   AND status = 'active'
                   AND is_primary = 1"
            );
            $statement->execute([
                $moduleCode,
                $resourceType,
                $sourceReference,
                $bindingRoleCode,
            ]);
        }

        $statement = $this->db->prepare(
            "INSERT INTO work_project_source_bindings
            (
                public_reference,
                work_project_id,
                source_module_code,
                source_resource_type,
                source_reference,
                binding_role_code,
                is_primary,
                status,
                metadata_json,
                created_by_user_reference,
                created_at,
                updated_at
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?,
                'active', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )"
        );
        $statement->execute([
            $publicReference,
            $projectId,
            $moduleCode,
            $resourceType,
            $sourceReference,
            $bindingRoleCode,
            $isPrimary ? 1 : 0,
            $metadataJson,
            $actorUserReference,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createItemSourceLink(
        int $workItemId,
        string $publicReference,
        string $moduleCode,
        string $resourceType,
        string $sourceReference,
        string $relationTypeCode,
        ?string $actorUserReference,
        ?string $metadataJson
    ): int {
        $statement = $this->db->prepare(
            "INSERT INTO work_item_source_links
            (
                public_reference,
                work_item_id,
                source_module_code,
                source_resource_type,
                source_reference,
                relation_type_code,
                metadata_json,
                created_by_user_reference,
                created_at,
                updated_at
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?,
                UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )"
        );
        $statement->execute([
            $publicReference,
            $workItemId,
            $moduleCode,
            $resourceType,
            $sourceReference,
            $relationTypeCode,
            $metadataJson,
            $actorUserReference,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function sourceLinksForItem(int $workItemId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                id,
                public_reference,
                work_item_id,
                source_module_code,
                source_resource_type,
                source_reference,
                relation_type_code,
                metadata_json,
                created_by_user_reference,
                created_at,
                updated_at
             FROM work_item_source_links
             WHERE work_item_id = ?
             ORDER BY id"
        );
        $statement->execute([$workItemId]);

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function linkedItemsForSource(
        string $moduleCode,
        string $resourceType,
        string $sourceReference
    ): array {
        $statement = $this->db->prepare(
            "SELECT
                l.id AS link_id,
                l.public_reference AS link_reference,
                l.relation_type_code,
                l.metadata_json AS link_metadata_json,
                l.created_by_user_reference,
                l.created_at AS linked_at,
                wi.id AS work_item_id,
                wi.public_reference AS work_item_reference,
                wi.project_id,
                wp.public_reference AS work_project_reference,
                wp.code AS work_project_code,
                wp.title AS work_project_title,
                wi.item_type,
                wi.sequence_number,
                wi.title,
                wi.priority_code,
                wi.progress_percent,
                wi.due_at,
                ws.code AS status_code,
                ws.title AS status_title,
                ws.is_closed AS status_is_closed,
                wi.archived_at
             FROM work_item_source_links l
             INNER JOIN work_items wi
                ON wi.id = l.work_item_id
             INNER JOIN work_projects wp
                ON wp.id = wi.project_id
             INNER JOIN work_statuses ws
                ON ws.id = wi.status_id
             WHERE l.source_module_code = ?
               AND l.source_resource_type = ?
               AND l.source_reference = ?
               AND wi.archived_at IS NULL
               AND wp.archived_at IS NULL
             ORDER BY wi.id DESC, l.id DESC"
        );
        $statement->execute([
            $moduleCode,
            $resourceType,
            $sourceReference,
        ]);

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}
