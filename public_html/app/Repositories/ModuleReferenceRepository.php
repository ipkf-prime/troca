<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class ModuleReferenceRepository
{
    private PDO $db;

    public function __construct(
        string $connection = 'work.primary',
        ?ConnectionResolver $connections = null
    ) {
        $this->db = ($connections ?? new ConnectionResolver())->resolve($connection);
    }

    public function options(
        string $moduleCode,
        string $groupCode,
        bool $activeOnly = true
    ): array {
        $where = [
            'g.module_code = ?',
            'g.code = ?',
        ];
        $parameters = [$moduleCode, $groupCode];

        if ($activeOnly) {
            $where[] = 'g.is_active = 1';
            $where[] = 'i.is_active = 1';
        }

        $statement = $this->db->prepare("
            SELECT i.code, i.title_fa, i.title_en, i.color,
                   i.sort_order, i.is_active, i.is_system, i.is_locked
            FROM module_reference_items i
            INNER JOIN module_reference_groups g ON g.id = i.group_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.sort_order, i.id
        ");
        $statement->execute($parameters);

        $options = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $options[(string) $row['code']] = (string) $row['title_fa'];
        }

        return $options;
    }
}
