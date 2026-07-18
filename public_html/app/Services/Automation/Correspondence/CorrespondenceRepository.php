<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceRepository
{
    public function __construct(private ?AutomationOperationalRuntime $runtime = null)
    {
        $this->runtime ??= new AutomationOperationalRuntime();
    }

    public function insert(array $data): int
    {
        $statement = $this->connection()->prepare('
            INSERT INTO correspondences (
                public_reference, organization_id, org_unit_id, fiscal_year_id, direction_code,
                status_code, subject, summary, document_template_version_id, priority_code, confidentiality_code, channel_code,
                external_number, external_date, received_at, dispatched_at, created_by_user_id,
                updated_by_user_id, lock_version, created_at, updated_at
            ) VALUES (
                :public_reference, :organization_id, :org_unit_id, NULL, :direction_code,
                :status_code, :subject, :summary, :document_template_version_id, :priority_code, :confidentiality_code, :channel_code,
                :external_number, :external_date, :received_at, :dispatched_at, :created_by_user_id,
                :updated_by_user_id, 0, :created_at, :updated_at
            )
        ');
        $statement->execute($data);

        return (int) $this->connection()->lastInsertId();
    }

    public function updateCurrentVersion(int $id, int $versionId, int $versionNumber, int $userId, string $now, bool $incrementLock = true): void
    {
        $lockSql = $incrementLock ? ', lock_version = lock_version + 1' : '';
        $statement = $this->connection()->prepare("
            UPDATE correspondences
            SET current_version_id = ?, current_version_number = ?, updated_by_user_id = ?, updated_at = ?{$lockSql}
            WHERE id = ?
        ");
        $statement->execute([$versionId, $versionNumber, $userId, $now, $id]);
    }

    public function updateDraft(int $id, array $data, int $expectedLockVersion): bool
    {
        $statement = $this->connection()->prepare('
            UPDATE correspondences
            SET direction_code = :direction_code,
                subject = :subject,
                summary = :summary,
                document_template_version_id = :document_template_version_id,
                priority_code = :priority_code,
                confidentiality_code = :confidentiality_code,
                channel_code = :channel_code,
                external_number = :external_number,
                external_date = :external_date,
                received_at = :received_at,
                dispatched_at = :dispatched_at,
                updated_by_user_id = :updated_by_user_id,
                updated_at = :updated_at,
                lock_version = lock_version + 1
            WHERE id = :id
              AND status_code = :status_code
              AND lock_version = :lock_version
        ');
        $statement->execute($data + [
            'id' => $id,
            'status_code' => 'draft',
            'lock_version' => $expectedLockVersion,
        ]);

        return $statement->rowCount() === 1;
    }

    public function findByPublicReference(string $publicReference): ?array
    {
        $statement = $this->connection()->prepare('SELECT c.*, t.public_reference AS document_template_reference, t.title_fa AS document_template_title FROM correspondences c LEFT JOIN correspondence_document_template_versions tv ON tv.id = c.document_template_version_id LEFT JOIN correspondence_document_templates t ON t.id = tv.template_id WHERE c.public_reference = ? LIMIT 1');
        $statement->execute([$publicReference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByPublicReferenceForUpdate(string $publicReference): ?array
    {
        $statement = $this->connection()->prepare('SELECT * FROM correspondences WHERE public_reference = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$publicReference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->where($filters);
        $offset = max(0, ($page - 1) * $perPage);
        $count = $this->connection()->prepare("SELECT COUNT(*) FROM correspondences c {$where}");
        $count->execute($params);

        $statement = $this->connection()->prepare("
            SELECT c.*, v.content_snapshot, v.created_at AS version_created_at,
                   (
                       SELECT COALESCE(cp.external_display_name, cp.external_organization_name, '')
                       FROM correspondence_parties cp
                       WHERE cp.correspondence_id = c.id
                       ORDER BY cp.sort_order ASC, cp.id ASC
                       LIMIT 1
                   ) AS correspondent_display
            FROM correspondences c
            LEFT JOIN correspondence_versions v ON v.id = c.current_version_id
            {$where}
            ORDER BY c.updated_at DESC, c.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $statement->execute($params);

        return [
            'total' => (int) $count->fetchColumn(),
            'items' => $statement->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    public function dashboardCounts(): array
    {
        $counts = ['all' => 0, 'drafts' => 0, 'incoming' => 0, 'outgoing' => 0, 'internal' => 0, 'recent' => 0];
        $counts['all'] = (int) $this->connection()->query('SELECT COUNT(*) FROM correspondences')->fetchColumn();
        $counts['drafts'] = (int) $this->connection()->query("SELECT COUNT(*) FROM correspondences WHERE status_code = 'draft'")->fetchColumn();
        $counts['incoming'] = (int) $this->connection()->query("SELECT COUNT(*) FROM correspondences WHERE direction_code = 'incoming'")->fetchColumn();
        $counts['outgoing'] = (int) $this->connection()->query("SELECT COUNT(*) FROM correspondences WHERE direction_code = 'outgoing'")->fetchColumn();
        $counts['internal'] = (int) $this->connection()->query("SELECT COUNT(*) FROM correspondences WHERE direction_code = 'internal'")->fetchColumn();
        $counts['recent'] = (int) $this->connection()->query("SELECT COUNT(*) FROM correspondences WHERE updated_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)")->fetchColumn();

        return $counts;
    }

    private function where(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $clauses[] = '(c.subject LIKE ? OR c.public_reference LIKE ? OR c.external_number LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }

        foreach (['status' => 'status_code', 'direction' => 'direction_code', 'priority' => 'priority_code'] as $filter => $column) {
            if (($filters[$filter] ?? '') !== '') {
                $clauses[] = "c.{$column} = ?";
                $params[] = $filters[$filter];
            }
        }

        if (($filters['date_from'] ?? '') !== '') {
            $clauses[] = 'DATE(c.updated_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $clauses[] = 'DATE(c.updated_at) <= ?';
            $params[] = $filters['date_to'];
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function connection(): PDO
    {
        return $this->runtime->connection();
    }
}
