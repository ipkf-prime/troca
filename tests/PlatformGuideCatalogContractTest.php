<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$catalog = json_decode(
    (string) file_get_contents($root . '/public_html/resources/ui-content/platform-guides.json'),
    true
);

if (!is_array($catalog)) throw new RuntimeException('Catalog invalid.');
if (count($catalog) !== 53) throw new RuntimeException('Catalog count invalid.');

$counts = ['guide' => 0, 'notice' => 0, 'error' => 0];
$bound = ['guide' => 0, 'notice' => 0, 'error' => 0];
$keys = [];

foreach ($catalog as $item) {
    if (!is_array($item)) throw new RuntimeException('Catalog row invalid.');

    $key = (string) ($item['key'] ?? '');
    $type = (string) ($item['content_type'] ?? '');
    $body = trim((string) ($item['body'] ?? ''));

    if (preg_match('/^[a-z0-9][a-z0-9._-]{2,189}$/D', $key) !== 1) {
        throw new RuntimeException('Invalid key: ' . $key);
    }

    if (!isset($counts[$type])) throw new RuntimeException('Invalid type: ' . $key);
    if ($body === '') throw new RuntimeException('Empty body: ' . $key);
    if (str_contains($body, '[DYNAMIC]')) throw new RuntimeException('Dynamic placeholder leaked: ' . $key);

    if (($item['consumer_bound'] ?? null) !== true) {
        throw new RuntimeException('Catalog item is not consumer-bound: ' . $key);
    }

    $counts[$type]++;
    $bound[$type]++;
    $keys[] = $key;
}

if (count(array_unique($keys)) !== 53) throw new RuntimeException('Catalog keys not unique.');
if ($counts !== ['guide' => 47, 'notice' => 5, 'error' => 1]) throw new RuntimeException('Type counts invalid.');
if ($bound !== ['guide' => 47, 'notice' => 5, 'error' => 1]) throw new RuntimeException('Binding counts invalid.');

foreach ([
    'core.coming-soon.guide.01',
    'ticketing.ticketing-sla-management.guide.01',
    'core.register-verify.guide.03',
    'core.register.guide.02',
] as $excluded) {
    if (in_array($excluded, $keys, true)) throw new RuntimeException('Excluded/deferred key leaked: ' . $excluded);
}

$registry = (string) file_get_contents($root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php');
foreach ([
    'SeedPlatformGuideCatalog::class',
    'NormalizeStaticGuideRenderContract::class',
    'MarkStaticGuideConsumersBound::class',
    'NormalizeStaticNoticeErrorRenderContract::class',
    'MarkStaticNoticeErrorConsumersBound::class',
] as $marker) {
    if (!str_contains($registry, $marker)) throw new RuntimeException('Registry marker missing: ' . $marker);
}

echo "PLATFORM_GUIDE_CATALOG_CONTRACT=PASS\n";
echo "CATALOG_COUNT=53\n";
echo "GUIDE_COUNT=47\n";
echo "NOTICE_COUNT=5\n";
echo "ERROR_COUNT=1\n";
echo "STATIC_GUIDES_BOUND=47\n";
echo "NOTICE_BOUND=5\n";
echo "ERROR_BOUND=1\n";
echo "STATIC_CATALOG_BOUND=53\n";
echo "DYNAMIC_GUIDES_DEFERRED=2\n";
echo "FALSE_POSITIVE_GUIDES_EXCLUDED=2\n";
