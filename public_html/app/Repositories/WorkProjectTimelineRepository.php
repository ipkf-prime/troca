<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkProjectTimelineRepository
{
    private PDO $work;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->work = ($connections ?? new ConnectionResolver())
            ->resolve('work.primary');
    }

    public function entries(int $projectId, int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));

        $statement = $this->work->prepare("
            SELECT
                activity.event_type,
                activity.actor_user_reference,
                activity.actor_display_name_snapshot,
                activity.payload_json,
                activity.occurred_at,
                item.public_reference AS item_reference,
                item.title AS item_title
            FROM work_activity_events activity
            LEFT JOIN work_items item ON item.id = activity.work_item_id
            WHERE activity.project_id = ?
            ORDER BY activity.occurred_at DESC, activity.id DESC
            LIMIT {$limit}
        ");
        $statement->execute([$projectId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
