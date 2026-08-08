<?php

namespace App\Repositories;

use PDO;
use RuntimeException;
use Throwable;

class NotificationApprovalRepository extends BaseRepository
{
    public function createPendingRequest(
        array $request,
        array $targets,
        array $mediaAssets,
        array $step
    ): array {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $statement = $db->prepare("
                INSERT INTO notification_approval_requests (
                    public_reference,
                    idempotency_key,
                    requester_user_id,
                    requester_scope_type,
                    requester_scope_reference,
                    requester_context_json,
                    status_code,
                    approval_mode_code,
                    current_step_order,
                    total_steps,
                    message_type_code,
                    purpose_code,
                    priority_code,
                    subject,
                    body,
                    channels_json,
                    request_reason,
                    payload_checksum_sha256,
                    submitted_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?,
                    'pending', 'single', 1, 1,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $statement->execute([
                $request['public_reference'],
                $request['idempotency_key'],
                $request['requester_user_id'],
                $request['requester_scope_type'],
                $request['requester_scope_reference'],
                $request['requester_context_json'],
                $request['message_type_code'],
                $request['purpose_code'],
                $request['priority_code'],
                $request['subject'],
                $request['body'],
                $request['channels_json'],
                $request['request_reason'],
                $request['payload_checksum_sha256'],
            ]);

            $requestId = (int) $db->lastInsertId();

            $targetInsert = $db->prepare("
                INSERT INTO notification_approval_targets (
                    public_reference,
                    request_id,
                    source_type,
                    recipient_user_id,
                    recipient_user_reference,
                    recipient_title,
                    channel_code,
                    destination_snapshot,
                    destination_masked,
                    destination_hash,
                    status_code,
                    sort_order,
                    metadata_json,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'pending', ?, ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            foreach ($targets as $index => $target) {
                $targetInsert->execute([
                    $target['public_reference'],
                    $requestId,
                    $target['source_type'],
                    $target['recipient_user_id'],
                    $target['recipient_user_reference'],
                    $target['recipient_title'],
                    $target['channel_code'],
                    $target['destination_snapshot'],
                    $target['destination_masked'],
                    $target['destination_hash'],
                    (int) (
                        $target['sort_order']
                        ?? $index
                    ),
                    $target['metadata_json'] ?? null,
                ]);
            }

            $stepInsert = $db->prepare("
                INSERT INTO notification_approval_steps (
                    public_reference,
                    request_id,
                    step_order,
                    title,
                    approval_policy_code,
                    approver_rule_json,
                    required_decisions,
                    completed_decisions,
                    status_code,
                    activated_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, 1, ?, 'any', ?, 1, 0,
                    'active',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $stepInsert->execute([
                $step['public_reference'],
                $requestId,
                $step['title'],
                $step['approver_rule_json'],
            ]);

            if ($mediaAssets !== []) {
                $mediaInsert = $db->prepare("
                    INSERT INTO notification_approval_media_links (
                        request_id,
                        asset_id,
                        sort_order,
                        is_primary,
                        created_at
                    )
                    VALUES (
                        ?, ?, ?, ?, CURRENT_TIMESTAMP
                    )
                ");

                foreach ($mediaAssets as $index => $asset) {
                    $assetId = (int) (
                        $asset['id'] ?? 0
                    );

                    if ($assetId < 1) {
                        continue;
                    }

                    $mediaInsert->execute([
                        $requestId,
                        $assetId,
                        $index,
                        $index === 0 ? 1 : 0,
                    ]);
                }
            }

            $this->insertEvent(
                $requestId,
                (int) $request['requester_user_id'],
                'request_created',
                null,
                'draft',
                null,
                [
                    'origin' =>
                        'notification_send_center',
                ]
            );

            $this->insertEvent(
                $requestId,
                (int) $request['requester_user_id'],
                'request_submitted',
                'draft',
                'pending',
                $request['request_reason'],
                [
                    'approval_mode_code' => 'single',
                    'current_step_order' => 1,
                ]
            );

            $db->commit();

            return [
                'id' => $requestId,
                'public_reference' =>
                    (string) $request[
                        'public_reference'
                    ],
                'status_code' => 'pending',
                'current_step_order' => 1,
                'total_steps' => 1,
                'target_count' => count($targets),
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function findByReference(
        string $publicReference
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_approval_requests
            WHERE public_reference = ?
            LIMIT 1
        ");

        $statement->execute([
            $publicReference,
        ]);

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }


    public function queue(
        int $limit = 100
    ): array {
        $limit = max(
            1,
            min(200, $limit)
        );

        return $this->connection()->query("
            SELECT
                r.id,
                r.public_reference,
                r.requester_user_id,
                r.requester_scope_type,
                r.requester_scope_reference,
                r.status_code,
                r.message_type_code,
                r.purpose_code,
                r.priority_code,
                r.subject,
                r.body,
                r.channels_json,
                r.request_reason,
                r.current_step_order,
                r.total_steps,
                r.submitted_at,
                r.created_at,
                COALESCE(
                    NULLIF(p.full_name, ''),
                    NULLIF(u.username, ''),
                    CONCAT(
                        'کاربر ',
                        r.requester_user_id
                    )
                ) AS requester_title,
                (
                    SELECT COUNT(*)
                    FROM notification_approval_targets t
                    WHERE t.request_id = r.id
                ) AS target_count,
                (
                    SELECT COUNT(*)
                    FROM notification_approval_media_links ml
                    WHERE ml.request_id = r.id
                ) AS media_count
            FROM notification_approval_requests r
            INNER JOIN users u
                ON u.id = r.requester_user_id
            LEFT JOIN persons p
                ON p.id = u.person_id
            WHERE r.status_code = 'pending'
            ORDER BY
                r.submitted_at ASC,
                r.id ASC
            LIMIT {$limit}
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function historyPage(
        array $filters
    ): array {
        $query = trim(
            (string) ($filters['q'] ?? '')
        );

        $decision = trim(
            (string) (
                $filters['decision'] ?? ''
            )
        );

        $from = trim(
            (string) ($filters['from'] ?? '')
        );

        $to = trim(
            (string) ($filters['to'] ?? '')
        );

        $page = max(
            1,
            (int) ($filters['page'] ?? 1)
        );

        $perPage = max(
            1,
            min(
                100,
                (int) (
                    $filters['per_page'] ?? 20
                )
            )
        );

        $where = [
            "d.decision_code IN ('approve', 'reject')",
        ];

        $params = [];

        if ($query !== '') {
            $like = '%' . $query . '%';

            $where[] = "(
                CONVERT(
                    r.public_reference
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci LIKE ?
                OR COALESCE(
                    requester_person.full_name,
                    ''
                ) LIKE ?
                OR COALESCE(
                    requester.username,
                    ''
                ) LIKE ?
                OR COALESCE(
                    actor_person.full_name,
                    ''
                ) LIKE ?
                OR COALESCE(
                    actor.username,
                    ''
                ) LIKE ?
                OR COALESCE(
                    r.subject,
                    ''
                ) LIKE ?
                OR COALESCE(
                    r.request_reason,
                    ''
                ) LIKE ?
                OR COALESCE(
                    d.reason,
                    ''
                ) LIKE ?
            )";

            for ($i = 0; $i < 8; $i++) {
                $params[] = $like;
            }
        }

        if (in_array(
            $decision,
            ['approve', 'reject'],
            true
        )) {
            $where[] = 'd.decision_code = ?';
            $params[] = $decision;
        }

        if ($from !== '') {
            $where[] = 'DATE(d.decided_at) >= ?';
            $params[] = $from;
        }

        if ($to !== '') {
            $where[] = 'DATE(d.decided_at) <= ?';
            $params[] = $to;
        }

        $whereSql = implode(
            "\n AND ",
            $where
        );

        $countStatement =
            $this->connection()->prepare("
                SELECT COUNT(*)
                FROM notification_approval_decisions d
                INNER JOIN notification_approval_requests r
                    ON r.id = d.request_id
                INNER JOIN users requester
                    ON requester.id =
                        r.requester_user_id
                LEFT JOIN persons requester_person
                    ON requester_person.id =
                        requester.person_id
                INNER JOIN users actor
                    ON actor.id =
                        d.actor_user_id
                LEFT JOIN persons actor_person
                    ON actor_person.id =
                        actor.person_id
                WHERE {$whereSql}
            ");

        $countStatement->execute($params);

        $total =
            (int) $countStatement->fetchColumn();

        $pages = max(
            1,
            (int) ceil(
                $total / $perPage
            )
        );

        if ($page > $pages) {
            $page = $pages;
        }

        $offset =
            ($page - 1) * $perPage;

        $statement =
            $this->connection()->prepare("
                SELECT
                    r.id,
                    r.public_reference,
                    r.requester_user_id,
                    r.status_code,
                    r.message_type_code,
                    r.subject,
                    r.body,
                    r.channels_json,
                    r.request_reason,
                    r.submitted_at,
                    r.approved_at,
                    r.rejected_at,

                    COALESCE(
                        NULLIF(
                            requester_person.full_name,
                            ''
                        ),
                        NULLIF(
                            requester.username,
                            ''
                        ),
                        CONCAT(
                            'کاربر ',
                            r.requester_user_id
                        )
                    ) AS requester_title,

                    d.id AS decision_id,
                    d.actor_user_id,
                    d.decision_code,
                    d.reason AS decision_reason,
                    d.decided_at,

                    COALESCE(
                        NULLIF(
                            actor_person.full_name,
                            ''
                        ),
                        NULLIF(
                            actor.username,
                            ''
                        ),
                        CONCAT(
                            'کاربر ',
                            d.actor_user_id
                        )
                    ) AS actor_title,

                    (
                        SELECT COUNT(*)
                        FROM notification_approval_targets t
                        WHERE t.request_id = r.id
                    ) AS target_count,

                    (
                        SELECT COUNT(*)
                        FROM notification_approval_media_links ml
                        WHERE ml.request_id = r.id
                    ) AS media_count,

                    (
                        SELECT dr.public_reference
                        FROM notification_approval_dispatch_runs dr
                        WHERE dr.request_id = r.id
                        ORDER BY
                            dr.attempt_number DESC,
                            dr.id DESC
                        LIMIT 1
                    ) AS dispatch_run_reference,

                    (
                        SELECT dr.status_code
                        FROM notification_approval_dispatch_runs dr
                        WHERE dr.request_id = r.id
                        ORDER BY
                            dr.attempt_number DESC,
                            dr.id DESC
                        LIMIT 1
                    ) AS dispatch_status_code,

                    (
                        SELECT dr.sent_count
                        FROM notification_approval_dispatch_runs dr
                        WHERE dr.request_id = r.id
                        ORDER BY
                            dr.attempt_number DESC,
                            dr.id DESC
                        LIMIT 1
                    ) AS dispatch_sent_count,

                    (
                        SELECT dr.failed_count
                        FROM notification_approval_dispatch_runs dr
                        WHERE dr.request_id = r.id
                        ORDER BY
                            dr.attempt_number DESC,
                            dr.id DESC
                        LIMIT 1
                    ) AS dispatch_failed_count,

                    (
                        SELECT dr.skipped_count
                        FROM notification_approval_dispatch_runs dr
                        WHERE dr.request_id = r.id
                        ORDER BY
                            dr.attempt_number DESC,
                            dr.id DESC
                        LIMIT 1
                    ) AS dispatch_skipped_count,

                    (
                        SELECT dr.completed_at
                        FROM notification_approval_dispatch_runs dr
                        WHERE dr.request_id = r.id
                        ORDER BY
                            dr.attempt_number DESC,
                            dr.id DESC
                        LIMIT 1
                    ) AS dispatch_completed_at

                FROM notification_approval_decisions d

                INNER JOIN notification_approval_requests r
                    ON r.id = d.request_id

                INNER JOIN users requester
                    ON requester.id =
                        r.requester_user_id

                LEFT JOIN persons requester_person
                    ON requester_person.id =
                        requester.person_id

                INNER JOIN users actor
                    ON actor.id =
                        d.actor_user_id

                LEFT JOIN persons actor_person
                    ON actor_person.id =
                        actor.person_id

                WHERE {$whereSql}

                ORDER BY
                    d.decided_at DESC,
                    d.id DESC

                LIMIT {$perPage}
                OFFSET {$offset}
            ");

        $statement->execute($params);

        return [
            'items' =>
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [],
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public function approvalSummary(): array
    {
        $row = $this->connection()->query("
            SELECT
                (
                    SELECT COUNT(*)
                    FROM notification_approval_requests
                    WHERE status_code = 'pending'
                ) AS pending_count,

                (
                    SELECT COUNT(DISTINCT request_id)
                    FROM notification_approval_decisions
                    WHERE decision_code = 'approve'
                ) AS approved_count,

                (
                    SELECT COUNT(DISTINCT request_id)
                    FROM notification_approval_decisions
                    WHERE decision_code = 'reject'
                ) AS rejected_count
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'pending' =>
                (int) (
                    $row['pending_count'] ?? 0
                ),
            'approved' =>
                (int) (
                    $row['approved_count'] ?? 0
                ),
            'rejected' =>
                (int) (
                    $row['rejected_count'] ?? 0
                ),
        ];
    }

    public function targets(
        int $requestId
    ): array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_approval_targets
            WHERE request_id = ?
            ORDER BY sort_order, id
        ");

        $statement->execute([
            $requestId,
        ]);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    public function targetSummaries(
        array $requestIds
    ): array {
        $requestIds = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $requestIds
                    ),
                    static fn (
                        int $requestId
                    ): bool =>
                        $requestId > 0
                )
            )
        );

        if ($requestIds === []) {
            return [];
        }

        $idSql = implode(
            ',',
            $requestIds
        );

        $rows = $this->connection()->query("
            SELECT
                request_id,
                channel_code,
                status_code,
                COUNT(*) AS total
            FROM notification_approval_targets
            WHERE request_id IN ({$idSql})
            GROUP BY
                request_id,
                channel_code,
                status_code
            ORDER BY
                request_id,
                channel_code,
                status_code
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $summaries = [];

        foreach ($requestIds as $requestId) {
            $summaries[$requestId] = [
                'total' => 0,
                'channels' => [],
                'statuses' => [],
            ];
        }

        foreach ($rows as $row) {
            $requestId = (int) (
                $row['request_id'] ?? 0
            );

            if (
                $requestId < 1
                || !isset($summaries[$requestId])
            ) {
                continue;
            }

            $channel = trim(
                (string) (
                    $row['channel_code'] ?? ''
                )
            );

            $status = trim(
                (string) (
                    $row['status_code'] ?? ''
                )
            );

            $count = max(
                0,
                (int) (
                    $row['total'] ?? 0
                )
            );

            $summaries[$requestId]['total'] +=
                $count;

            if ($channel !== '') {
                $summaries[
                    $requestId
                ]['channels'][$channel] =
                    (
                        $summaries[
                            $requestId
                        ]['channels'][$channel]
                        ?? 0
                    )
                    + $count;
            }

            if ($status !== '') {
                $summaries[
                    $requestId
                ]['statuses'][$status] =
                    (
                        $summaries[
                            $requestId
                        ]['statuses'][$status]
                        ?? 0
                    )
                    + $count;
            }
        }

        return $summaries;
    }

    public function targetPage(
        int $requestId,
        array $filters
    ): array {
        $query = trim(
            (string) ($filters['q'] ?? '')
        );

        $channel = trim(
            (string) (
                $filters['channel'] ?? ''
            )
        );

        $status = trim(
            (string) (
                $filters['status'] ?? ''
            )
        );

        $page = max(
            1,
            (int) (
                $filters['page'] ?? 1
            )
        );

        $perPage = max(
            1,
            min(
                100,
                (int) (
                    $filters['per_page'] ?? 20
                )
            )
        );

        $where = [
            'request_id = ?',
        ];

        $params = [
            $requestId,
        ];

        if ($query !== '') {
            $like = '%' . $query . '%';

            $where[] = "(
                recipient_title LIKE ?
                OR destination_masked LIKE ?
                OR COALESCE(
                    provider_title_snapshot,
                    ''
                ) LIKE ?
                OR CONVERT(
                    public_reference
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci
                    LIKE ?
            )";

            for ($i = 0; $i < 4; $i++) {
                $params[] = $like;
            }
        }

        if ($channel !== '') {
            $where[] = 'channel_code = ?';
            $params[] = $channel;
        }

        if ($status !== '') {
            $where[] = 'status_code = ?';
            $params[] = $status;
        }

        $whereSql = implode(
            "\n AND ",
            $where
        );

        $countStatement =
            $this->connection()->prepare("
                SELECT COUNT(*)
                FROM notification_approval_targets
                WHERE {$whereSql}
            ");

        $countStatement->execute(
            $params
        );

        $total =
            (int) $countStatement
                ->fetchColumn();

        $pages = max(
            1,
            (int) ceil(
                $total / $perPage
            )
        );

        if ($page > $pages) {
            $page = $pages;
        }

        $offset =
            ($page - 1) * $perPage;

        /*
         * Safe projection only.
         * destination_snapshot must never leave here.
         */
        $statement =
            $this->connection()->prepare("
                SELECT
                    public_reference,
                    recipient_title,
                    channel_code,
                    destination_masked,
                    status_code,
                    provider_title_snapshot
                FROM notification_approval_targets
                WHERE {$whereSql}
                ORDER BY
                    sort_order,
                    id
                LIMIT {$perPage}
                OFFSET {$offset}
            ");

        $statement->execute(
            $params
        );

        return [
            'items' =>
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [],
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public function mediaAssets(
        int $requestId
    ): array {
        $statement = $this->connection()->prepare("
            SELECT
                a.id,
                a.public_reference,
                a.original_name,
                a.stored_name,
                a.storage_path,
                a.mime_type,
                a.extension,
                a.media_kind,
                a.size_bytes,
                a.checksum_sha256,
                ml.sort_order,
                ml.is_primary,
                ml.alt_text
            FROM notification_approval_media_links ml
            INNER JOIN notification_media_assets a
                ON a.id = ml.asset_id
            WHERE ml.request_id = ?
              AND a.status_code = 'active'
            ORDER BY
                ml.sort_order,
                ml.id
        ");

        $statement->execute([
            $requestId,
        ]);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    public function transaction(
        callable $callback
    ): mixed {
        $db = $this->connection();

        if ($db->inTransaction()) {
            throw new RuntimeException(
                'notification_approval_transaction_nested'
            );
        }

        $db->beginTransaction();

        try {
            $result = $callback($this);

            $db->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function lockByReference(
        string $publicReference
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_approval_requests
            WHERE public_reference = ?
            LIMIT 1
            FOR UPDATE
        ");

        $statement->execute([
            $publicReference,
        ]);

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    public function lockActiveStep(
        int $requestId,
        int $stepOrder
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_approval_steps
            WHERE request_id = ?
              AND step_order = ?
              AND status_code = 'active'
            LIMIT 1
            FOR UPDATE
        ");

        $statement->execute([
            $requestId,
            $stepOrder,
        ]);

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    public function recordDecision(
        array $request,
        array $step,
        int $actorUserId,
        string $decisionCode,
        ?string $reason,
        array $actorSnapshot,
        string $toStatus
    ): array {
        $requestId = (int) (
            $request['id'] ?? 0
        );

        $stepId = (int) (
            $step['id'] ?? 0
        );

        if (
            $requestId < 1
            || $stepId < 1
            || $actorUserId < 1
        ) {
            throw new RuntimeException(
                'notification_approval_decision_invalid'
            );
        }

        if (!in_array(
            $decisionCode,
            ['approve', 'reject'],
            true
        )) {
            throw new RuntimeException(
                'notification_approval_decision_invalid'
            );
        }

        if (!in_array(
            $toStatus,
            ['approved', 'rejected'],
            true
        )) {
            throw new RuntimeException(
                'notification_approval_status_invalid'
            );
        }

        $decision = $this->connection()->prepare("
            INSERT INTO notification_approval_decisions (
                request_id,
                step_id,
                actor_user_id,
                decision_code,
                reason,
                actor_snapshot_json,
                decided_at,
                created_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $decision->execute([
            $requestId,
            $stepId,
            $actorUserId,
            $decisionCode,
            $reason,
            json_encode(
                $actorSnapshot,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);

        $stepUpdate = $this->connection()->prepare("
            UPDATE notification_approval_steps
            SET
                completed_decisions =
                    completed_decisions + 1,
                status_code = 'completed',
                completed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND request_id = ?
              AND status_code = 'active'
        ");

        $stepUpdate->execute([
            $stepId,
            $requestId,
        ]);

        if ($stepUpdate->rowCount() !== 1) {
            throw new RuntimeException(
                'notification_approval_step_conflict'
            );
        }

        $timeColumn = $toStatus === 'approved'
            ? 'approved_at'
            : 'rejected_at';

        $requestUpdate = $this->connection()->prepare("
            UPDATE notification_approval_requests
            SET
                status_code = ?,
                current_step_order = NULL,
                {$timeColumn} = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND status_code = 'pending'
        ");

        $requestUpdate->execute([
            $toStatus,
            $requestId,
        ]);

        if ($requestUpdate->rowCount() !== 1) {
            throw new RuntimeException(
                'notification_approval_request_conflict'
            );
        }

        $this->insertEvent(
            $requestId,
            $actorUserId,
            $decisionCode === 'approve'
                ? 'request_approved'
                : 'request_rejected',
            'pending',
            $toStatus,
            $reason,
            [
                'step_id' => $stepId,
                'step_order' => (int) (
                    $step['step_order'] ?? 0
                ),
                'decision_code' =>
                    $decisionCode,
            ]
        );

        return [
            'request_id' => $requestId,
            'public_reference' => (string) (
                $request['public_reference']
                ?? ''
            ),
            'status_code' => $toStatus,
            'decision_code' => $decisionCode,
        ];
    }


    public function startDispatch(
        array $request,
        int $actorUserId,
        string $fromStatus
    ): array {
        $requestId = (int) (
            $request['id'] ?? 0
        );

        if (
            $requestId < 1
            || $actorUserId < 1
            || $fromStatus === ''
        ) {
            throw new RuntimeException(
                'notification_approval_dispatch_invalid'
            );
        }

        $targetCount = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM notification_approval_targets
            WHERE request_id = ?
              AND status_code IN (
                  'pending',
                  'failed'
              )
        ");

        $targetCount->execute([
            $requestId,
        ]);

        $totalCount =
            (int) $targetCount->fetchColumn();

        if ($totalCount < 1) {
            throw new RuntimeException(
                'notification_approval_dispatch_targets_empty'
            );
        }

        $attempt = $this->connection()->prepare("
            SELECT
                COALESCE(
                    MAX(attempt_number),
                    0
                ) + 1
            FROM notification_approval_dispatch_runs
            WHERE request_id = ?
        ");

        $attempt->execute([
            $requestId,
        ]);

        $attemptNumber =
            (int) $attempt->fetchColumn();

        if ($attemptNumber < 1) {
            $attemptNumber = 1;
        }

        $runReference =
            'ndr_' . bin2hex(
                random_bytes(12)
            );

        $run = $this->connection()->prepare("
            INSERT INTO notification_approval_dispatch_runs (
                public_reference,
                request_id,
                attempt_number,
                started_by_user_id,
                status_code,
                total_count,
                sent_count,
                failed_count,
                skipped_count,
                started_at,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, ?,
                'dispatching',
                ?,
                0,
                0,
                0,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $run->execute([
            $runReference,
            $requestId,
            $attemptNumber,
            $actorUserId,
            $totalCount,
        ]);

        $runId = (int) $this->connection()
            ->lastInsertId();

        $update = $this->connection()->prepare("
            UPDATE notification_approval_requests
            SET
                status_code = 'dispatching',
                dispatch_started_at =
                    CURRENT_TIMESTAMP,
                failed_at = NULL,
                last_error = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND status_code = ?
        ");

        $update->execute([
            $requestId,
            $fromStatus,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'notification_approval_dispatch_conflict'
            );
        }

        $this->insertEvent(
            $requestId,
            $actorUserId,
            'dispatch_started',
            $fromStatus,
            'dispatching',
            null,
            [
                'dispatch_run_id' =>
                    $runId,
                'dispatch_run_reference' =>
                    $runReference,
                'attempt_number' =>
                    $attemptNumber,
                'target_count' =>
                    $totalCount,
            ]
        );

        return [
            'run_id' => $runId,
            'run_reference' =>
                $runReference,
            'request_id' =>
                $requestId,
            'attempt_number' =>
                $attemptNumber,
            'total_count' =>
                $totalCount,
        ];
    }

    public function dispatchTargets(
        int $requestId
    ): array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_approval_targets
            WHERE request_id = ?
              AND status_code IN (
                  'pending',
                  'failed'
              )
            ORDER BY
                sort_order,
                id
        ");

        $statement->execute([
            $requestId,
        ]);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    public function markTargetSent(
        int $targetId,
        array $result
    ): void {
        if ($targetId < 1) {
            throw new RuntimeException(
                'notification_approval_target_invalid'
            );
        }

        $providerReference = trim(
            (string) (
                $result[
                    'provider_instance_reference'
                ] ?? ''
            )
        );

        $providerId = null;

        if ($providerReference !== '') {
            $provider = $this->connection()->prepare("
                SELECT id
                FROM notification_provider_instances
                WHERE public_reference = ?
                LIMIT 1
            ");

            $provider->execute([
                $providerReference,
            ]);

            $value =
                (int) $provider->fetchColumn();

            if ($value > 0) {
                $providerId = $value;
            }
        }

        $statement = $this->connection()->prepare("
            UPDATE notification_approval_targets
            SET
                status_code = 'sent',
                provider_instance_id = ?,
                provider_type_code = ?,
                provider_title_snapshot = ?,
                error_code = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND status_code IN (
                  'pending',
                  'failed'
              )
        ");

        $statement->execute([
            $providerId,
            trim((string) (
                $result[
                    'provider_type_code'
                ] ?? ''
            )) ?: null,
            trim((string) (
                $result[
                    'provider_title'
                ] ?? ''
            )) ?: null,
            $targetId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'notification_approval_target_conflict'
            );
        }
    }

    public function markTargetFailed(
        int $targetId,
        string $errorCode
    ): void {
        if ($targetId < 1) {
            throw new RuntimeException(
                'notification_approval_target_invalid'
            );
        }

        $errorCode = trim($errorCode);

        if ($errorCode === '') {
            $errorCode =
                'notification_gateway_provider_rejected';
        }

        $statement = $this->connection()->prepare("
            UPDATE notification_approval_targets
            SET
                status_code = 'failed',
                error_code = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND status_code IN (
                  'pending',
                  'failed'
              )
        ");

        $statement->execute([
            mb_substr(
                $errorCode,
                0,
                190,
                'UTF-8'
            ),
            $targetId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'notification_approval_target_conflict'
            );
        }
    }

    public function lockDispatchRun(
        int $runId,
        int $requestId
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM notification_approval_dispatch_runs
            WHERE id = ?
              AND request_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $statement->execute([
            $runId,
            $requestId,
        ]);

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    public function finishDispatch(
        array $request,
        array $run,
        int $actorUserId,
        string $toStatus,
        int $sentCount,
        int $failedCount,
        int $skippedCount,
        array $result,
        ?string $lastError
    ): array {
        $requestId = (int) (
            $request['id'] ?? 0
        );

        $runId = (int) (
            $run['id'] ?? 0
        );

        if (
            $requestId < 1
            || $runId < 1
            || $actorUserId < 1
        ) {
            throw new RuntimeException(
                'notification_approval_dispatch_invalid'
            );
        }

        if (!in_array(
            $toStatus,
            [
                'dispatched',
                'partially_dispatched',
                'failed',
            ],
            true
        )) {
            throw new RuntimeException(
                'notification_approval_status_invalid'
            );
        }

        $runUpdate = $this->connection()->prepare("
            UPDATE notification_approval_dispatch_runs
            SET
                status_code = ?,
                sent_count = ?,
                failed_count = ?,
                skipped_count = ?,
                result_json = ?,
                completed_at = CURRENT_TIMESTAMP,
                last_error = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND request_id = ?
              AND status_code = 'dispatching'
        ");

        $runUpdate->execute([
            $toStatus,
            max(0, $sentCount),
            max(0, $failedCount),
            max(0, $skippedCount),
            json_encode(
                $result,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            $lastError,
            $runId,
            $requestId,
        ]);

        if ($runUpdate->rowCount() !== 1) {
            throw new RuntimeException(
                'notification_approval_dispatch_run_conflict'
            );
        }

        if ($toStatus === 'dispatched') {
            $requestUpdate =
                $this->connection()->prepare("
                    UPDATE notification_approval_requests
                    SET
                        status_code = 'dispatched',
                        dispatched_at =
                            CURRENT_TIMESTAMP,
                        failed_at = NULL,
                        last_error = NULL,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE id = ?
                      AND status_code =
                          'dispatching'
                ");

            $requestUpdate->execute([
                $requestId,
            ]);
        } elseif ($toStatus === 'failed') {
            $requestUpdate =
                $this->connection()->prepare("
                    UPDATE notification_approval_requests
                    SET
                        status_code = 'failed',
                        failed_at =
                            CURRENT_TIMESTAMP,
                        last_error = ?,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE id = ?
                      AND status_code =
                          'dispatching'
                ");

            $requestUpdate->execute([
                $lastError,
                $requestId,
            ]);
        } else {
            $requestUpdate =
                $this->connection()->prepare("
                    UPDATE notification_approval_requests
                    SET
                        status_code =
                            'partially_dispatched',
                        failed_at = NULL,
                        last_error = ?,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE id = ?
                      AND status_code =
                          'dispatching'
                ");

            $requestUpdate->execute([
                $lastError,
                $requestId,
            ]);
        }

        if ($requestUpdate->rowCount() !== 1) {
            throw new RuntimeException(
                'notification_approval_dispatch_finalize_conflict'
            );
        }

        $this->insertEvent(
            $requestId,
            $actorUserId,
            'dispatch_finished',
            'dispatching',
            $toStatus,
            $lastError,
            [
                'dispatch_run_id' =>
                    $runId,
                'dispatch_run_reference' =>
                    (string) (
                        $run[
                            'public_reference'
                        ] ?? ''
                    ),
                'sent_count' =>
                    max(0, $sentCount),
                'failed_count' =>
                    max(0, $failedCount),
                'skipped_count' =>
                    max(0, $skippedCount),
            ]
        );

        return [
            'request_id' =>
                $requestId,
            'public_reference' =>
                (string) (
                    $request[
                        'public_reference'
                    ] ?? ''
                ),
            'run_id' =>
                $runId,
            'run_reference' =>
                (string) (
                    $run[
                        'public_reference'
                    ] ?? ''
                ),
            'status_code' =>
                $toStatus,
            'sent_count' =>
                max(0, $sentCount),
            'failed_count' =>
                max(0, $failedCount),
            'skipped_count' =>
                max(0, $skippedCount),
        ];
    }

    private function insertEvent(
        int $requestId,
        ?int $actorUserId,
        string $eventCode,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $reason,
        array $metadata
    ): void {
        $statement = $this->connection()->prepare("
            INSERT INTO notification_approval_events (
                public_reference,
                request_id,
                actor_user_id,
                event_code,
                from_status,
                to_status,
                reason,
                metadata_json,
                happened_at,
                created_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $statement->execute([
            'nae_' . bin2hex(
                random_bytes(12)
            ),
            $requestId,
            $actorUserId,
            $eventCode,
            $fromStatus,
            $toStatus,
            $reason,
            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }
}
