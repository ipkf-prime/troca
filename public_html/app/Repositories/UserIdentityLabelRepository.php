<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class UserIdentityLabelRepository
{
    private PDO $core;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->core = ($connections ?? new ConnectionResolver())->resolve('core.primary');
    }

    /**
     * @param array<int> $userIds
     * @return array<int,array<string,mixed>>
     */
    public function contactsByUserIds(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->core->prepare("
            SELECT
                users.id,
                users.username,
                users.email AS user_email,
                users.mobile AS user_mobile,
                persons.email AS person_email,
                persons.mobile AS person_mobile,
                persons.full_name
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.id IN ({$placeholders})
        ");
        $statement->execute($ids);

        $contacts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $contacts[(int) $row['id']] = $row;
        }

        return $contacts;
    }
}
