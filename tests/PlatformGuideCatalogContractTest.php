<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$catalog = json_decode((string) file_get_contents($root . '/public_html/resources/ui-content/platform-guides.json'), true);
if (!is_array($catalog)) throw new RuntimeException('Catalog invalid.');
if (count($catalog) !== 55) throw new RuntimeException('Catalog count invalid.');

$counts = ['guide' => 0, 'notice' => 0, 'error' => 0];
$bound = ['guide' => 0, 'notice' => 0, 'error' => 0];
$parameterized = [];
$keys = [];

foreach ($catalog as $item) {
    if (!is_array($item)) throw new RuntimeException('Catalog row invalid.');

    $key = (string) ($item['key'] ?? '');
    $type = (string) ($item['content_type'] ?? '');
    $body = trim((string) ($item['body'] ?? ''));

    if (preg_match('/^[a-z0-9][a-z0-9._-]{2,189}$/D', $key) !== 1) throw new RuntimeException('Invalid key: ' . $key);
    if (!isset($counts[$type])) throw new RuntimeException('Invalid type: ' . $key);
    if ($body === '') throw new RuntimeException('Empty body: ' . $key);
    if (str_contains($body, '[DYNAMIC]')) throw new RuntimeException('Legacy dynamic placeholder leaked: ' . $key);
    if (($item['consumer_bound'] ?? null) !== true) throw new RuntimeException('Catalog item is not consumer-bound: ' . $key);

    $isParameterized = ($item['parameterized'] ?? false) === true;
    if ($isParameterized) {
        if ($type !== 'guide') throw new RuntimeException('Only guides may be parameterized: ' . $key);
        $params = $item['template_parameters'] ?? null;
        if (!is_array($params) || count($params) !== 1) throw new RuntimeException('Template parameter contract invalid: ' . $key);
        $name = (string) $params[0];
        if (substr_count($body, '{{' . $name . '}}') !== 1) throw new RuntimeException('Template placeholder contract invalid: ' . $key);
        $parameterized[$key] = $name;
    } elseif (str_contains($body, '{{') || str_contains($body, '}}')) {
        throw new RuntimeException('Unexpected template placeholder in static catalog row: ' . $key);
    }

    $counts[$type]++;
    $bound[$type]++;
    $keys[] = $key;
}

if (count(array_unique($keys)) !== 55) throw new RuntimeException('Catalog keys not unique.');
if ($counts !== ['guide' => 49, 'notice' => 5, 'error' => 1]) throw new RuntimeException('Type counts invalid.');
if ($bound !== ['guide' => 49, 'notice' => 5, 'error' => 1]) throw new RuntimeException('Binding counts invalid.');
if ($parameterized !== [
    'core.register-verify.guide.03' => 'seconds',
    'core.register.guide.02' => 'mobile',
]) throw new RuntimeException('Parameterized guide map invalid.');

foreach (['core.coming-soon.guide.01', 'ticketing.ticketing-sla-management.guide.01'] as $excluded) {
    if (in_array($excluded, $keys, true)) throw new RuntimeException('False-positive key leaked: ' . $excluded);
}

$registry = (string) file_get_contents($root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php');
foreach ([
    'SeedPlatformGuideCatalog::class',
    'NormalizeStaticGuideRenderContract::class',
    'MarkStaticGuideConsumersBound::class',
    'NormalizeStaticNoticeErrorRenderContract::class',
    'MarkStaticNoticeErrorConsumersBound::class',
    'SeedAndBindParameterizedGuides::class',
    'NormalizeFullUiContentTitles::class',
] as $marker) {
    if (!str_contains($registry, $marker)) throw new RuntimeException('Registry marker missing: ' . $marker);
}

echo "PLATFORM_GUIDE_CATALOG_CONTRACT=PASS\n";
echo "CATALOG_COUNT=55\n";
echo "GUIDE_COUNT=49\n";
echo "STATIC_GUIDES_BOUND=47\n";
echo "PARAMETERIZED_GUIDES_BOUND=2\n";
echo "NOTICE_BOUND=5\n";
echo "ERROR_BOUND=1\n";
echo "TOTAL_CATALOG_BOUND=55\n";
echo "DYNAMIC_GUIDES_DEFERRED=0\n";
echo "FALSE_POSITIVE_GUIDES_EXCLUDED=2\n";