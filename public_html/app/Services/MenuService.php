<?php

namespace IPKF\Services;

use IPKF\Core\Database;
use PDO;

class MenuService
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function getSidebar(string $role): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM panel_sidebar_menus
            WHERE is_active = 1
            AND min_role IN ('user', ?)
            ORDER BY sort_order ASC
        ");

        $stmt->execute([$role]);

        $menus = $stmt->fetchAll();

        return $this->buildTree($menus);
    }

    private function buildTree(array $items, ?int $parentId = null): array
    {
        $branch = [];

        foreach ($items as $item) {
            if ((int)$item['parent_id'] === (int)$parentId) {

                $children = $this->buildTree($items, $item['id']);

                if ($children) {
                    $item['children'] = $children;
                }

                $branch[] = $item;
            }
        }

        return $branch;
    }
}