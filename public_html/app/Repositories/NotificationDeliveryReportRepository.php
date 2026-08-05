<?php

namespace App\Repositories;

use PDO;

class NotificationDeliveryReportRepository extends BaseRepository
{
    public function page(array $filters): array
    {
        [$where, $parameters] = $this->whereClause($filters);

        $totalStatement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM notification_deliveries AS deliveries
            INNER JOIN notification_recipients AS recipients
              ON recipients.id = deliveries.recipient_id
            INNER JOIN notifications
              ON notifications.id = recipients.notification_id
            LEFT JOIN users
              ON users.id = recipients.user_id
            LEFT JOIN persons
              ON persons.id = users.person_id
            LEFT JOIN notification_provider_instances AS instances
              ON instances.id = deliveries.provider_instance_id
            LEFT JOIN notification_provider_types AS provider_types
              ON provider_types.id = instances.provider_type_id
            {$where}
        ");
        $totalStatement->execute($parameters);
        $total = (int) $totalStatement->fetchColumn();

        $perPage = (int) $filters['per_page'];
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, (int) $filters['page']), $pages);
        $offset = ($page - 1) * $perPage;
        $order = $this->orderClause((string) $filters['sort']);

        $statement = $this->connection()->prepare("
            SELECT
                deliveries.id,
                deliveries.public_reference,
                deliveries.channel_code,
                deliveries.purpose_code,
                deliveries.status_code,
                deliveries.destination_snapshot,
                deliveries.provider_code,
                deliveries.provider_instance_id,
                deliveries.provider_type_code,
                deliveries.provider_message_reference,
                deliveries.request_reference,
                deliveries.available_at,
                deliveries.attempt_count,
                deliveries.max_attempts,
                deliveries.last_attempt_at,
                deliveries.sent_at,
                deliveries.delivered_at,
                deliveries.failed_at,
                deliveries.last_error,
                deliveries.last_response_code,
                deliveries.created_at,
                deliveries.updated_at,
                notifications.public_reference AS notification_reference,
                notifications.title,
                notifications.body,
                notifications.priority_code,
                notifications.category_code,
                recipients.user_id,
                recipients.user_reference,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    NULLIF(users.email, ''),
                    recipients.user_reference
                ) AS user_title,
                instances.public_reference AS provider_instance_reference,
                instances.title AS provider_title,
                COALESCE(
                    NULLIF(provider_types.code, ''),
                    deliveries.provider_type_code,
                    deliveries.provider_code
                ) AS resolved_provider_type_code,
                COALESCE(
                    NULLIF(provider_types.title, ''),
                    deliveries.provider_type_code,
                    deliveries.provider_code
                ) AS provider_type_title
            FROM notification_deliveries AS deliveries
            INNER JOIN notification_recipients AS recipients
              ON recipients.id = deliveries.recipient_id
            INNER JOIN notifications
              ON notifications.id = recipients.notification_id
            LEFT JOIN users
              ON users.id = recipients.user_id
            LEFT JOIN persons
              ON persons.id = users.person_id
            LEFT JOIN notification_provider_instances AS instances
              ON instances.id = deliveries.provider_instance_id
            LEFT JOIN notification_provider_types AS provider_types
              ON provider_types.id = instances.provider_type_id
            {$where}
            ORDER BY {$order}
            LIMIT {$perPage}
            OFFSET {$offset}
        ");
        $statement->execute($parameters);
        $items = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $attempts = $this->attemptsFor(array_map(
            static fn (array $item): int => (int) $item['id'],
            $items
        ));

        foreach ($items as &$item) {
            $deliveryId = (int) $item['id'];
            $item['attempts'] = $attempts[$deliveryId] ?? [];
            $item['fallback_used'] = (int) $item['attempt_count'] > 1;
        }
        unset($item);

        return [
            'items' => $items,
            'summary' => $this->summary($where, $parameters),
            'providers' => $this->providerOptions(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    private function whereClause(array $filters): array
    {
        $clauses = [];
        $parameters = [];
        $query = trim((string) $filters['q']);

        if ($query !== '') {
            $needle = '%' . $query . '%';
            $userTitle = "
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    NULLIF(users.email, ''),
                    recipients.user_reference
                )
            ";

            $clauses[] = "(
                notifications.title LIKE ?
                OR notifications.body LIKE ?
                OR {$userTitle} LIKE ?
                OR deliveries.destination_snapshot LIKE ?
                OR deliveries.public_reference LIKE ?
                OR deliveries.request_reference LIKE ?
                OR deliveries.provider_message_reference LIKE ?
                OR deliveries.provider_type_code LIKE ?
                OR deliveries.provider_code LIKE ?
                OR instances.title LIKE ?
                OR provider_types.title LIKE ?
            )";

            for ($index = 0; $index < 11; $index++) {
                $parameters[] = $needle;
            }
        }

        if ((string) $filters['channel'] !== '') {
            $clauses[] = 'deliveries.channel_code = ?';
            $parameters[] = $filters['channel'];
        }

        if ((string) $filters['status'] !== '') {
            $clauses[] = 'deliveries.status_code = ?';
            $parameters[] = $filters['status'];
        }

        if ((string) $filters['provider'] !== '') {
            $clauses[] = "COALESCE(
                NULLIF(provider_types.code, ''),
                deliveries.provider_type_code,
                deliveries.provider_code
            ) = ?";
            $parameters[] = $filters['provider'];
        }

        if ((string) $filters['from'] !== '') {
            $clauses[] = 'deliveries.created_at >= ?';
            $parameters[] = $filters['from'] . ' 00:00:00';
        }

        if ((string) $filters['to'] !== '') {
            $clauses[] = 'deliveries.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $parameters[] = $filters['to'] . ' 00:00:00';
        }

        return [
            $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses),
            $parameters,
        ];
    }

    private function orderClause(string $sort): string
    {
        return match ($sort) {
            'created_asc' => 'deliveries.id ASC',
            'status_asc' => 'deliveries.status_code ASC, deliveries.id DESC',
            'status_desc' => 'deliveries.status_code DESC, deliveries.id DESC',
            'channel_asc' => 'deliveries.channel_code ASC, deliveries.id DESC',
            'attempts_desc' => 'deliveries.attempt_count DESC, deliveries.id DESC',
            'attempts_asc' => 'deliveries.attempt_count ASC, deliveries.id DESC',
            default => 'deliveries.id DESC',
        };
    }

    private function summary(string $where, array $parameters): array
    {
        $statement = $this->connection()->prepare("
            SELECT
                COUNT(*) AS total_count,
                SUM(deliveries.status_code IN ('sent', 'delivered')) AS success_count,
                SUM(deliveries.status_code = 'failed') AS failed_count,
                SUM(
                    deliveries.status_code IN (
                        'pending',
                        'queued',
                        'processing'
                    )
                ) AS pending_count,
                SUM(deliveries.attempt_count > 1) AS fallback_count
            FROM notification_deliveries AS deliveries
            INNER JOIN notification_recipients AS recipients
              ON recipients.id = deliveries.recipient_id
            INNER JOIN notifications
              ON notifications.id = recipients.notification_id
            LEFT JOIN users
              ON users.id = recipients.user_id
            LEFT JOIN persons
              ON persons.id = users.person_id
            LEFT JOIN notification_provider_instances AS instances
              ON instances.id = deliveries.provider_instance_id
            LEFT JOIN notification_provider_types AS provider_types
              ON provider_types.id = instances.provider_type_id
            {$where}
        ");
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total_count'] ?? 0),
            'success' => (int) ($row['success_count'] ?? 0),
            'failed' => (int) ($row['failed_count'] ?? 0),
            'pending' => (int) ($row['pending_count'] ?? 0),
            'fallback' => (int) ($row['fallback_count'] ?? 0),
        ];
    }

    private function attemptsFor(array $deliveryIds): array
    {
        $deliveryIds = array_values(array_filter(
            array_map('intval', $deliveryIds),
            static fn (int $id): bool => $id > 0
        ));

        if ($deliveryIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($deliveryIds), '?'));

        $statement = $this->connection()->prepare("
            SELECT
                attempts.id,
                attempts.delivery_id,
                attempts.attempt_number,
                attempts.status_code,
                attempts.provider_instance_id,
                attempts.provider_type_code,
                attempts.provider_message_reference,
                attempts.provider_response_code,
                attempts.provider_response_message,
                attempts.duration_ms,
                attempts.response_metadata_json,
                attempts.attempted_at,
                attempts.created_at,
                instances.public_reference AS provider_instance_reference,
                instances.title AS provider_title,
                provider_types.title AS provider_type_title
            FROM notification_delivery_attempts AS attempts
            LEFT JOIN notification_provider_instances AS instances
              ON instances.id = attempts.provider_instance_id
            LEFT JOIN notification_provider_types AS provider_types
              ON provider_types.id = instances.provider_type_id
            WHERE attempts.delivery_id IN ({$placeholders})
            ORDER BY
                attempts.delivery_id ASC,
                attempts.attempt_number ASC,
                attempts.id ASC
        ");
        $statement->execute($deliveryIds);

        $grouped = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $attempt) {
            $deliveryId = (int) $attempt['delivery_id'];
            $grouped[$deliveryId][] = $attempt;
        }

        return $grouped;
    }

    private function providerOptions(): array
    {
        $statement = $this->connection()->query("
            SELECT DISTINCT
                COALESCE(
                    NULLIF(provider_types.code, ''),
                    deliveries.provider_type_code,
                    deliveries.provider_code
                ) AS code,
                COALESCE(
                    NULLIF(provider_types.title, ''),
                    deliveries.provider_type_code,
                    deliveries.provider_code
                ) AS title
            FROM notification_deliveries AS deliveries
            LEFT JOIN notification_provider_instances AS instances
              ON instances.id = deliveries.provider_instance_id
            LEFT JOIN notification_provider_types AS provider_types
              ON provider_types.id = instances.provider_type_id
            WHERE COALESCE(
                NULLIF(provider_types.code, ''),
                deliveries.provider_type_code,
                deliveries.provider_code
            ) IS NOT NULL
            ORDER BY title ASC
        ");

        return array_values(array_filter(
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: [],
            static fn (array $item): bool =>
                trim((string) ($item['code'] ?? '')) !== ''
        ));
    }
}
