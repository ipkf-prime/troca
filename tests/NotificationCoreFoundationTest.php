<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$migration = $read(
    'public_html/system/Database/Migrations/CreateNotificationCoreFoundationTables.php'
);
$seeder = $read(
    'public_html/system/Database/Seeds/NotificationCoreSeeder.php'
);
$repository = $read(
    'public_html/app/Repositories/NotificationRepository.php'
);
$publisher = $read(
    'public_html/app/Services/NotificationPublisherService.php'
);
$processor = $read(
    'public_html/app/Services/NotificationOutboxProcessorService.php'
);
$inbox = $read(
    'public_html/app/Services/NotificationInboxService.php'
);
$route = $read(
    'public_html/routes/notifications.php'
);
$view = $read(
    'public_html/resources/views/admin/notifications.php'
);
$routeLoader = $read(
    'public_html/system/Routing/RouteLoader.php'
);
$migrate = $read('public_html/public/migrate.php');
$seed = $read('public_html/public/seed.php');

foreach ([
    'notification_channels',
    'notification_templates',
    'notification_events',
    'notification_outbox',
    'notifications',
    'notification_recipients',
    'notification_deliveries',
    'notification_delivery_attempts',
    'notification_preferences',
] as $table) {
    $expect(
        str_contains($migration, $table),
        "Missing notification table: {$table}"
    );
}

$expect(
    str_contains($migration, 'idempotency_key')
    && str_contains($migration, 'locked_at')
    && str_contains($migration, 'attempts_count'),
    'Outbox reliability fields are incomplete.'
);

$expect(
    str_contains($seeder, "'in_app'")
    && str_contains($seeder, "'email'")
    && str_contains($seeder, "'sms'")
    && str_contains($seeder, "'bale'"),
    'Notification channels are incomplete.'
);

$expect(
    str_contains($publisher, 'idempotency_key')
    && str_contains($publisher, 'recipient_user_references')
    && str_contains($publisher, 'channels'),
    'Publisher contract is incomplete.'
);

$expect(
    str_contains($repository, 'FOR UPDATE')
    && str_contains($repository, 'failOutbox')
    && str_contains($repository, 'materializeInApp'),
    'Outbox claim/retry/materialization is incomplete.'
);

$expect(
    str_contains($processor, 'claimNextOutbox')
    && str_contains($processor, 'completeOutbox')
    && str_contains($processor, 'failOutbox'),
    'Outbox processor lifecycle is incomplete.'
);

$expect(
    str_contains($inbox, 'markAllRead')
    && str_contains($inbox, 'unreadCount'),
    'Inbox read-state lifecycle is incomplete.'
);

$expect(
    str_contains($route, '/admin/notifications')
    && str_contains($route, '/read-all')
    && str_contains($view, 'صندوق اعلان‌ها'),
    'In-app notification routes or UI are incomplete.'
);

$expect(
    str_contains($routeLoader, 'notifications.php'),
    'Notification routes are not loaded.'
);

$expect(
    str_contains(
        $migrate,
        'CreateNotificationCoreFoundationTables'
    )
    && str_contains($seed, 'NotificationCoreSeeder'),
    'Core migrate/seed entry points are incomplete.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $migration . $repository
    ),
    'Destructive SQL is present.'
);

echo "Notification core foundation checks passed.\n";
