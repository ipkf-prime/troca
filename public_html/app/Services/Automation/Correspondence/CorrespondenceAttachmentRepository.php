<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceAttachmentRepository
{
    public function __construct(
        private ?AutomationOperationalRuntime $runtime = null,
        private ?CorrespondenceAttachmentPolicy $policy = null
    ) {
        $this->runtime ??=
            new AutomationOperationalRuntime();

        $this->policy ??=
            new CorrespondenceAttachmentPolicy();
    }

    public function add(
        int $correspondenceId,
        array $file,
        string $role,
        ?string $title,
        int $userId,
        string $now,
        ?array $actorContext = null
    ): void {
        $pdo =
            $this->runtime
                ->connection();

        $pdo->beginTransaction();

        try {
            /*
             * attachment-primary-uniqueness-v1
             *
             * Lock the correspondence row before checking the
             * current primary attachment. This serializes concurrent
             * upload and metadata-edit requests for the same draft.
             */
            $correspondenceLock =
                $pdo->prepare("
                    SELECT status_code
                    FROM correspondences
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

            $correspondenceLock->execute([
                $correspondenceId,
            ]);

            $correspondenceStatus =
                $correspondenceLock->fetchColumn();

            if ($correspondenceStatus !== 'draft') {
                throw new \DomainException(
                    'attachment_not_editable'
                );
            }

            /*
             * attachment-total-limit-v1
             *
             * Count active attachments while the correspondence row
             * remains locked. Concurrent uploads cannot exceed the
             * per-correspondence limit.
             */
            $attachmentCount =
                $pdo->prepare("
                    SELECT COUNT(*)
                    FROM correspondence_attachments a
                    INNER JOIN private_files f
                        ON f.id = a.file_id
                       AND f.status = 'active'
                    WHERE a.correspondence_id = ?
                ");

            $attachmentCount->execute([
                $correspondenceId,
            ]);

            if (
                (int) $attachmentCount->fetchColumn()
                >= $this->policy->maxFiles()
            ) {
                throw new \DomainException(
                    'attachment_limit_reached'
                );
            }

            if ($role === 'main') {
                $primaryCheck =
                    $pdo->prepare("
                        SELECT COUNT(*)
                        FROM correspondence_attachments a
                        INNER JOIN private_files f
                            ON f.id = a.file_id
                           AND f.status = 'active'
                        WHERE a.correspondence_id = ?
                          AND a.attachment_role_code = 'main'
                    ");

                $primaryCheck->execute([
                    $correspondenceId,
                ]);

                if ((int) $primaryCheck->fetchColumn() > 0) {
                    throw new \DomainException(
                        'primary_attachment_exists'
                    );
                }
            }

            $statement =
                $pdo->prepare("
                    INSERT INTO private_files (
                        public_reference,
                        storage_provider_code,
                        storage_key,
                        original_filename,
                        mime_type,
                        size_bytes,
                        sha256_checksum,
                        uploaded_by_user_id,
                        uploaded_at,
                        scan_status_code,
                        status,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        ?,
                        'local_private',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'not_required',
                        'active',
                        ?,
                        ?
                    )
                ");

            $statement->execute([
                $file[
                    'public_reference'
                ],
                $file[
                    'storage_key'
                ],
                $file[
                    'original_filename'
                ],
                $file[
                    'mime_type'
                ],
                $file[
                    'size_bytes'
                ],
                $file[
                    'sha256_checksum'
                ],
                $userId,
                $now,
                $now,
                $now,
            ]);

            $fileId =
                (int) $pdo
                    ->lastInsertId();

            $link =
                $pdo->prepare("
                    INSERT INTO correspondence_attachments (
                        correspondence_id,
                        correspondence_version_id,
                        file_id,
                        attachment_role_code,
                        title,
                        description,
                        display_order,
                        linked_by_user_id,
                        linked_at
                    )
                    VALUES (
                        ?,
                        NULL,
                        ?,
                        ?,
                        ?,
                        NULL,
                        0,
                        ?,
                        ?
                    )
                ");

            $link->execute([
                $correspondenceId,
                $fileId,
                $role,
                $title,
                $userId,
                $now,
            ]);

            $event =
                $pdo->prepare("
                    INSERT INTO correspondence_events (
                        correspondence_id,
                        referral_id,

                        root_organization_id,
                        organization_id,
                        secretariat_desk_id,

                        event_type_code,

                        actor_user_id,
                        actor_org_unit_id,
                        actor_appointment_reference,
                        actor_context_snapshot_json,

                        occurred_at,

                        previous_status_code,
                        resulting_status_code,

                        safe_metadata_json,

                        created_at
                    )
                    VALUES (
                        ?,
                        NULL,

                        ?,
                        ?,
                        ?,

                        'attachment_linked',

                        ?,
                        ?,
                        ?,
                        ?,

                        ?,

                        NULL,
                        NULL,

                        ?,

                        ?
                    )
                ");

            $event->execute([
                $correspondenceId,

                $actorContext[
                    'root_organization_id'
                ] ?? null,

                $actorContext[
                    'organization_id'
                ] ?? null,

                $actorContext[
                    'secretariat_desk_id'
                ] ?? null,

                $userId,

                $actorContext[
                    'org_unit_id'
                ] ?? null,

                $actorContext[
                    'appointment_reference'
                ] ?? null,

                $actorContext[
                    'snapshot_json'
                ] ?? null,

                $now,

                json_encode(
                    [
                        'file_reference' =>
                            $file[
                                'public_reference'
                            ],

                        'role' =>
                            $role,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

                $now,
            ]);

            $pdo->commit();

        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * attachment-metadata-edit-v1
     */
    public function updateMetadata(
        string $correspondenceReference,
        string $fileReference,
        string $role,
        ?string $title,
        int $userId,
        string $now,
        array $actorContext
    ): bool {
        $pdo = $this->runtime->connection();

        $scope =
            is_array($actorContext['repository_scope'] ?? null)
                ? $actorContext['repository_scope']
                : null;

        [$scopeSql, $scopeParams] =
            $this->scopeClause($scope);

        $pdo->beginTransaction();

        try {
            $sql = "
                SELECT
                    c.id AS correspondence_id,
                    c.status_code,
                    a.id AS attachment_id,
                    a.attachment_role_code,
                    a.title,
                    f.public_reference AS file_reference,
                    f.original_filename
                FROM correspondence_attachments a
                INNER JOIN private_files f
                    ON f.id = a.file_id
                INNER JOIN correspondences c
                    ON c.id = a.correspondence_id
                WHERE c.public_reference = ?
                  AND f.public_reference = ?
                  AND f.status = 'active'
            ";

            $params = [
                $correspondenceReference,
                $fileReference,
            ];

            if ($scopeSql !== '') {
                $sql .= ' AND ' . $scopeSql;

                $params = array_merge(
                    $params,
                    $scopeParams
                );
            }

            $sql .= ' LIMIT 1 FOR UPDATE';

            $select = $pdo->prepare($sql);
            $select->execute($params);

            $row = $select->fetch(PDO::FETCH_ASSOC);

            if (
                !is_array($row)
                || ($row['status_code'] ?? '') !== 'draft'
            ) {
                $pdo->rollBack();
                return false;
            }

            $oldRole =
                (string) $row['attachment_role_code'];

            if (
                $role === 'main'
                && $oldRole !== 'main'
            ) {
                /*
                 * attachment-primary-uniqueness-v1
                 *
                 * The correspondence row is locked by the preceding
                 * SELECT ... FOR UPDATE. Count only active files and
                 * exclude the attachment currently being edited.
                 */
                $primaryCheck =
                    $pdo->prepare("
                        SELECT COUNT(*)
                        FROM correspondence_attachments a
                        INNER JOIN private_files f
                            ON f.id = a.file_id
                           AND f.status = 'active'
                        WHERE a.correspondence_id = ?
                          AND a.attachment_role_code = 'main'
                          AND a.id <> ?
                    ");

                $primaryCheck->execute([
                    (int) $row['correspondence_id'],
                    (int) $row['attachment_id'],
                ]);

                if ((int) $primaryCheck->fetchColumn() > 0) {
                    throw new \DomainException(
                        'primary_attachment_exists'
                    );
                }
            }

            $oldTitle =
                $row['title'] === null
                    ? null
                    : (string) $row['title'];

            if (
                $oldRole === $role
                && $oldTitle === $title
            ) {
                $pdo->commit();
                return true;
            }

            $update = $pdo->prepare("
                UPDATE correspondence_attachments
                SET
                    attachment_role_code = ?,
                    title = ?
                WHERE id = ?
            ");

            $update->execute([
                $role,
                $title,
                (int) $row['attachment_id'],
            ]);

            $event = $pdo->prepare("
                INSERT INTO correspondence_events (
                    correspondence_id,
                    referral_id,
                    root_organization_id,
                    organization_id,
                    secretariat_desk_id,
                    event_type_code,
                    actor_user_id,
                    actor_org_unit_id,
                    actor_appointment_reference,
                    actor_context_snapshot_json,
                    occurred_at,
                    previous_status_code,
                    resulting_status_code,
                    safe_metadata_json,
                    created_at
                )
                VALUES (
                    ?, NULL, ?, ?, ?,
                    'attachment_metadata_updated',
                    ?, ?, ?, ?, ?,
                    'draft', 'draft',
                    ?, ?
                )
            ");

            $event->execute([
                (int) $row['correspondence_id'],
                $actorContext['root_organization_id'] ?? null,
                $actorContext['organization_id'] ?? null,
                $actorContext['secretariat_desk_id'] ?? null,
                $userId,
                $actorContext['org_unit_id'] ?? null,
                $actorContext['appointment_reference'] ?? null,
                $actorContext['snapshot_json'] ?? null,
                $now,
                json_encode(
                    [
                        'file_reference' =>
                            (string) $row['file_reference'],
                        'original_filename' =>
                            (string) $row['original_filename'],
                        'old_role' => $oldRole,
                        'new_role' => $role,
                        'old_title' => $oldTitle,
                        'new_title' => $title,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
                $now,
            ]);

            $pdo->commit();
            return true;

        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
    /**
     * attachment-soft-delete-v1
     */
    public function remove(
        string $correspondenceReference,
        string $fileReference,
        int $userId,
        string $now,
        array $actorContext
    ): bool {
        $pdo = $this->runtime->connection();

        $scope =
            is_array($actorContext['repository_scope'] ?? null)
                ? $actorContext['repository_scope']
                : null;

        [$scopeSql, $scopeParams] =
            $this->scopeClause($scope);

        $pdo->beginTransaction();

        try {
            $sql = "
                SELECT
                    c.id AS correspondence_id,
                    c.status_code,
                    f.id AS file_id,
                    f.public_reference AS file_reference,
                    f.original_filename,
                    a.attachment_role_code
                FROM private_files f
                INNER JOIN correspondence_attachments a
                    ON a.file_id = f.id
                INNER JOIN correspondences c
                    ON c.id = a.correspondence_id
                WHERE c.public_reference = ?
                  AND f.public_reference = ?
                  AND f.status = 'active'
            ";

            $params = [
                $correspondenceReference,
                $fileReference,
            ];

            if ($scopeSql !== '') {
                $sql .= ' AND ' . $scopeSql;
                $params = array_merge(
                    $params,
                    $scopeParams
                );
            }

            $sql .= ' LIMIT 1 FOR UPDATE';

            $select = $pdo->prepare($sql);
            $select->execute($params);

            $row = $select->fetch(PDO::FETCH_ASSOC);

            if (
                !is_array($row)
                || ($row['status_code'] ?? '') !== 'draft'
            ) {
                $pdo->rollBack();
                return false;
            }

            $deactivate = $pdo->prepare("
                UPDATE private_files
                SET
                    status = 'inactive',
                    updated_at = ?
                WHERE id = ?
                  AND status = 'active'
            ");

            $deactivate->execute([
                $now,
                (int) $row['file_id'],
            ]);

            if ($deactivate->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $event = $pdo->prepare("
                INSERT INTO correspondence_events (
                    correspondence_id,
                    referral_id,
                    root_organization_id,
                    organization_id,
                    secretariat_desk_id,
                    event_type_code,
                    actor_user_id,
                    actor_org_unit_id,
                    actor_appointment_reference,
                    actor_context_snapshot_json,
                    occurred_at,
                    previous_status_code,
                    resulting_status_code,
                    safe_metadata_json,
                    created_at
                )
                VALUES (
                    ?, NULL, ?, ?, ?,
                    'attachment_removed',
                    ?, ?, ?, ?, ?,
                    'draft', 'draft',
                    ?, ?
                )
            ");

            $event->execute([
                (int) $row['correspondence_id'],
                $actorContext['root_organization_id'] ?? null,
                $actorContext['organization_id'] ?? null,
                $actorContext['secretariat_desk_id'] ?? null,
                $userId,
                $actorContext['org_unit_id'] ?? null,
                $actorContext['appointment_reference'] ?? null,
                $actorContext['snapshot_json'] ?? null,
                $now,
                json_encode(
                    [
                        'file_reference' =>
                            (string) $row['file_reference'],
                        'original_filename' =>
                            (string) $row['original_filename'],
                        'role' =>
                            (string) $row[
                                'attachment_role_code'
                            ],
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
                $now,
            ]);

            $pdo->commit();
            return true;

        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
    public function listFor(
        int $correspondenceId
    ): array {
        $statement =
            $this->runtime
                ->connection()
                ->prepare("
                    SELECT
                        a.id,
                        a.attachment_role_code,
                        a.title,
                        a.description,
                        a.linked_at,

                        f.public_reference
                            AS file_reference,

                        f.original_filename,
                        f.mime_type,
                        f.size_bytes,
                        f.storage_key

                    FROM correspondence_attachments a

                    INNER JOIN private_files f
                        ON f.id =
                            a.file_id
                       AND f.status =
                            'active'

                    WHERE a.correspondence_id = ?

                    ORDER BY
                        a.display_order,
                        a.id
                ");

        $statement->execute([
            $correspondenceId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    public function findForCorrespondence(
        string $correspondenceReference,
        string $fileReference,
        ?array $scope = null
    ): ?array {
        [$scopeSql, $scopeParams] =
            $this->scopeClause(
                $scope
            );

        $sql = "
            SELECT f.*

            FROM private_files f

            INNER JOIN correspondence_attachments a
                ON a.file_id =
                    f.id

            INNER JOIN correspondences c
                ON c.id =
                    a.correspondence_id

            WHERE c.public_reference = ?
              AND f.public_reference = ?
              AND f.status = 'active'
        ";

        $params = [
            $correspondenceReference,
            $fileReference,
        ];

        if ($scopeSql !== '') {
            $sql .=
                ' AND '
                . $scopeSql;

            $params =
                array_merge(
                    $params,
                    $scopeParams
                );
        }

        $sql .= ' LIMIT 1';

        $statement =
            $this->runtime
                ->connection()
                ->prepare($sql);

        $statement->execute(
            $params
        );

        return
            $statement->fetch(
                PDO::FETCH_ASSOC
            ) ?: null;
    }

    private function scopeClause(
        ?array $scope
    ): array {
        if ($scope === null) {
            return ['', []];
        }

        $rootId =
            (int) (
                $scope[
                    'root_organization_id'
                ] ?? 0
            );

        $ids =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            $scope[
                                'organization_ids'
                            ] ?? []
                        ),
                        static fn (
                            int $id
                        ): bool => $id > 0
                    )
                )
            );

        if (
            $rootId < 1
            || $ids === []
        ) {
            return [
                '1 = 0',
                [],
            ];
        }

        $in =
            implode(
                ',',
                array_fill(
                    0,
                    count($ids),
                    '?'
                )
            );

        return [
            "
                (
                    (
                        c.root_organization_id = ?
                        AND c.organization_id
                            IN ({$in})
                    )
                    OR
                    (
                        c.root_organization_id IS NULL
                        AND c.organization_id
                            IN ({$in})
                    )
                )
            ",

            array_merge(
                [$rootId],
                $ids,
                $ids
            ),
        ];
    }
}
