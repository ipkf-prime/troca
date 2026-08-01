<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

$db = \IPKF\Database\Database::connect();

$count = static function (
    string $table,
    string $where = '1=1'
) use ($db): int {
    $statement = $db->query(
        "SELECT COUNT(*) FROM {$table} WHERE {$where}"
    );

    return (int) $statement->fetchColumn();
};

$result = [
    'navigation_items' =>
        $count('admin_navigation_items', 'is_active = 1'),
    'communication_submenus' =>
        (int) $db->query("
            SELECT COUNT(*)
            FROM admin_navigation_items AS children
            INNER JOIN admin_navigation_items AS parent
              ON parent.id = children.parent_id
            WHERE parent.item_key = 'communications'
              AND children.is_active = 1
        ")->fetchColumn(),
    'route_permissions' =>
        $count('admin_route_permissions', 'is_active = 1'),
    'recipient_policies' =>
        $count(
            'message_recipient_policies',
            "status_code = 'active'"
        ),
    'provider_types' =>
        $count(
            'notification_provider_types',
            'is_active = 1'
        ),
    'event_catalog' =>
        $count(
            'notification_event_catalog',
            'is_active = 1'
        ),
    'routing_rules' =>
        $count(
            'notification_routing_rules',
            'is_enabled = 1'
        ),
    'conversations' =>
        $count('message_conversations'),
    'messages' =>
        $count('message_messages'),
];

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_PRETTY_PRINT
) . PHP_EOL;
