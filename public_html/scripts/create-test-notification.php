<?php

declare(strict_types=1);

use App\Services\NotificationOutboxProcessorService;
use App\Services\NotificationPublisherService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

$options = getopt('', [
    'confirm:',
    'user-id:',
]);

if (
    ($options['confirm'] ?? '')
        !== 'CREATE-TEST-NOTIFICATION'
) {
    fwrite(
        STDERR,
        "Usage:\n"
        . "php scripts/create-test-notification.php "
        . "--confirm=CREATE-TEST-NOTIFICATION "
        . "--user-id=1\n"
    );
    exit(2);
}

$userId = filter_var(
    $options['user-id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($userId === false) {
    fwrite(STDERR, "A valid --user-id is required.\n");
    exit(2);
}

$token = bin2hex(random_bytes(8));

$published = (new NotificationPublisherService())
    ->publish([
        'event_type' => 'system.test',
        'source_module' => 'core',
        'source_entity_type' => 'notification_test',
        'source_entity_reference' => $token,
        'source_event_reference' => $token,
        'actor_user_reference' => (string) $userId,
        'recipient_user_references' => [
            (string) $userId,
        ],
        'template_code' => 'system.test',
        'template_data' => [
            'title' => 'اعلان آزمایشی IPKF',
            'body' => 'زیرساخت Notification Core با موفقیت فعال شد.',
            'action_url' => '/admin/notifications',
        ],
        'channels' => ['in_app'],
        'priority_code' => 'normal',
        'category_code' => 'system',
        'idempotency_key' =>
            'system.test:' . $token,
    ]);

$processed = (new NotificationOutboxProcessorService())
    ->process(10, 'manual-test');

echo json_encode(
    [
        'published' => $published,
        'processed' => $processed,
    ],
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_PRETTY_PRINT
) . PHP_EOL;
