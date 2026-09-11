<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$catalog = json_decode((string) file_get_contents($root . '/public_html/resources/ui-content/platform-guides.json'), true);
if (!is_array($catalog)) throw new RuntimeException('Catalog invalid.');

$items = array_values(array_filter(
    $catalog,
    static fn (mixed $item): bool => is_array($item) && in_array(($item['content_type'] ?? null), ['notice', 'error'], true)
));

$notices = 0;
$errors = 0;
$body = 0;
$titleBody = 0;

foreach ($items as $item) {
    if (($item['content_type'] ?? null) === 'notice') $notices++;
    if (($item['content_type'] ?? null) === 'error') $errors++;
    if (($item['consumer_bound'] ?? null) !== false) throw new RuntimeException('Consumer bound prematurely: ' . ($item['key'] ?? ''));

    $mode = (string) ($item['render_mode'] ?? '');
    if ($mode === 'body') {
        $body++;
        if (array_key_exists('legacy_body', $item)) throw new RuntimeException('Body-only item has legacy_body.');
    } elseif ($mode === 'title_body') {
        $titleBody++;
        foreach (['title', 'body', 'legacy_body'] as $field) {
            if (trim((string) ($item[$field] ?? '')) === '') throw new RuntimeException('Missing field ' . $field);
        }
    } else {
        throw new RuntimeException('Invalid render mode.');
    }
}

if (count($items) !== 6 || $notices !== 5 || $errors !== 1 || $body !== 3 || $titleBody !== 3) {
    throw new RuntimeException('Notice/error render counts invalid.');
}

$registry = (string) file_get_contents($root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php');
if (!str_contains($registry, 'NormalizeStaticNoticeErrorRenderContract::class')) throw new RuntimeException('Normalization migration not registered.');
if (!is_file($root . '/public_html/system/Database/Migrations/NormalizeStaticNoticeErrorRenderContract.php')) throw new RuntimeException('Normalization migration missing.');

echo "STATIC_NOTICE_ERROR_RENDER_CONTRACT=PASS\n";
echo "NOTICE_COUNT=5\n";
echo "ERROR_COUNT=1\n";
echo "BODY_ONLY=3\n";
echo "TITLE_BODY=3\n";
echo "CONSUMER_BOUND=NO_NOT_YET\n";
