<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class UserInvitationRepository extends BaseRepository
{
    public function create(array $data): array
    {
        $db =
            $this->connection();

        $db->beginTransaction();

        try {
            $revoke =
                $db->prepare("
                    UPDATE user_invitations
                    SET status = 'revoked',
                        revoked_at =
                            CURRENT_TIMESTAMP,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE mobile_norm = ?
                      AND status = 'pending'
                      AND accepted_at IS NULL
                      AND revoked_at IS NULL
                ");

            $revoke->execute([
                $data['mobile_norm'],
            ]);

            $statement =
                $db->prepare("
                    INSERT INTO user_invitations (
                        public_reference,
                        token_hash,
                        full_name,
                        mobile,
                        mobile_norm,
                        email,
                        email_norm,
                        status,
                        expires_at,
                        created_by_user_id,
                        created_ip,
                        created_user_agent,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        ?, ?, ?, ?, ?, ?, ?,
                        'pending',
                        ?, ?, ?, ?,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");

            $statement->execute([
                $data['public_reference'],
                $data['token_hash'],
                $data['full_name'],
                $data['mobile'],
                $data['mobile_norm'],
                $data['email'],
                $data['email_norm'],
                $data['expires_at'],
                $data['created_by_user_id'],
                $data['created_ip'],
                $data['created_user_agent'],
            ]);

            $id =
                (int) $db->lastInsertId();

            if ($id < 1) {
                throw new \RuntimeException(
                    'user_invitation_insert_failed'
                );
            }

            $db->commit();

            return [
                'id' => $id,
                'public_reference' =>
                    $data['public_reference'],
            ];

        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function pendingByTokenHash(
        string $tokenHash
    ): ?array {
        $statement =
            $this->connection()->prepare("
                SELECT
                    id,
                    public_reference,
                    token_hash,
                    full_name,
                    mobile,
                    mobile_norm,
                    email,
                    email_norm,
                    status,
                    expires_at,
                    created_by_user_id,
                    accepted_user_id,
                    accepted_at,
                    revoked_at,
                    created_at
                FROM user_invitations
                WHERE token_hash = ?
                  AND status = 'pending'
                  AND accepted_at IS NULL
                  AND revoked_at IS NULL
                  AND expires_at >=
                      CURRENT_TIMESTAMP
                LIMIT 1
            ");

        $statement->execute([
            $tokenHash,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }

    public function markAccepted(
        string $tokenHash,
        int $userId
    ): bool {
        if ($userId < 1) {
            return false;
        }

        $statement =
            $this->connection()->prepare("
                UPDATE user_invitations
                SET status = 'accepted',
                    accepted_user_id = ?,
                    accepted_at =
                        CURRENT_TIMESTAMP,
                    updated_at =
                        CURRENT_TIMESTAMP
                WHERE token_hash = ?
                  AND status = 'pending'
                  AND accepted_at IS NULL
                  AND revoked_at IS NULL
                  AND expires_at >=
                      CURRENT_TIMESTAMP
            ");

        $statement->execute([
            $userId,
            $tokenHash,
        ]);

        return
            $statement->rowCount()
            === 1;
    }
}
