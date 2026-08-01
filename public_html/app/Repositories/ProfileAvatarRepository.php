<?php

namespace App\Repositories;

use PDO;

class ProfileAvatarRepository extends BaseRepository
{
    public function urlForUser(int $userId): string
    {
        if ($userId < 1) {
            return '';
        }

        $statement = $this->connection()->prepare("
            SELECT persons.avatar
            FROM users
            INNER JOIN persons
              ON persons.id = users.person_id
            WHERE users.id = ?
              AND users.deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $value = $statement->fetchColumn();

        return $value === false
            ? ''
            : trim((string) $value);
    }

    public function updateUrl(
        int $userId,
        ?string $url
    ): bool {
        $statement = $this->connection()->prepare("
            UPDATE persons
            INNER JOIN users
              ON users.person_id = persons.id
            SET persons.avatar = ?,
                persons.updated_at = CURRENT_TIMESTAMP
            WHERE users.id = ?
              AND users.deleted_at IS NULL
        ");
        $statement->execute([
            $url !== null && trim($url) !== ''
                ? trim($url)
                : null,
            $userId,
        ]);

        return $statement->rowCount() > 0;
    }
}
