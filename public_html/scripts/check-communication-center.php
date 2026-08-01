<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

$db = \IPKF\Database\Database::connect();

$tableExists = static function (
    string $table
) use ($db): bool {
    $statement = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $statement->execute([$table]);

    return (int) $statement->fetchColumn() > 0;
};

$count = static function (
    string $table,
    string $where = '1=1'
) use ($db, $tableExists): ?int {
    if (!$tableExists($table)) {
        return null;
    }

    $statement = $db->query(
        "SELECT COUNT(*) FROM {$table} WHERE {$where}"
    );

    return (int) $statement->fetchColumn();
};

$requiredTables = [
    'admin_navigation_items',
    'admin_route_permissions',
    'message_recipient_policies',
    'message_conversations',
    'message_messages',
    'notification_provider_types',
    'notification_event_catalog',
    'notification_routing_rules',
];

$missing = array_values(array_filter(
    $requiredTables,
    static fn (string $table): bool =>
        !$tableExists($table)
));

$communicationSubmenus = null;

if ($tableExists('admin_navigation_items')) {
    $communicationSubmenus = (int) $db->query("
        SELECT COUNT(*)
        FROM admin_navigation_items AS children
        INNER JOIN admin_navigation_items AS parent
          ON parent.id = children.parent_id
        WHERE parent.item_key = 'communications'
          AND children.is_active = 1
    ")->fetchColumn();
}

$result = [
    'status' => $missing === []
        ? 'ready'
        : 'migration_required',
    'missing_tables' => $missing,
    'navigation_items' =>
        $count(
            'admin_navigation_items',
            'is_active = 1'
        ),
    'communication_submenus' =>
        $communicationSubmenus,
    'route_permissions' =>
        $count(
            'admin_route_permissions',
            'is_active = 1'
        ),
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
