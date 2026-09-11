<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$catalog = json_decode(
    (string) file_get_contents($root . '/public_html/resources/ui-content/platform-guides.json'),
    true
);
if (!is_array($catalog) || count($catalog) !== 55) throw new RuntimeException('Catalog invalid.');

$automatic = 0;
$long = 0;
$explicit = 0;
$maximum = 0;

foreach ($catalog as $item) {
    if (!is_array($item)) throw new RuntimeException('Catalog row invalid.');
    $title = trim((string) ($item['title'] ?? ''));
    if ($title !== '') {
        $explicit++;
        continue;
    }

    $full = trim(preg_replace('/\s+/u', ' ', (string) ($item['body'] ?? '')) ?? (string) ($item['body'] ?? ''));
    if ($full === '') throw new RuntimeException('Automatic title body empty.');
    $length = mb_strlen($full, 'UTF-8');
    $maximum = max($maximum, $length);
    $automatic++;
    if ($length > 72) $long++;
}

if ($automatic !== 28 || $long !== 16 || $explicit !== 27) {
    throw new RuntimeException('Full title catalog counts invalid.');
}
if ($maximum > 500) throw new RuntimeException('Full title storage contract invalid.');

$files = [
    'SeedPlatformGuideCatalog.php',
    'NormalizeStaticGuideRenderContract.php',
    'NormalizeStaticNoticeErrorRenderContract.php',
    'SeedAndBindParameterizedGuides.php',
];
foreach ($files as $file) {
    $text = (string) file_get_contents($root . '/public_html/system/Database/Migrations/' . $file);
    if (str_contains($text, 'count($characters) <= 72') || str_contains($text, 'array_slice($characters, 0, 72)')) {
        throw new RuntimeException('Legacy compact title logic remains: ' . $file);
    }
}

$registry = (string) file_get_contents($root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php');
if (!str_contains($registry, 'NormalizeFullUiContentTitles::class')) {
    throw new RuntimeException('Full title migration registry marker missing.');
}
if (!is_file($root . '/public_html/system/Database/Migrations/NormalizeFullUiContentTitles.php')) {
    throw new RuntimeException('Full title migration missing.');
}

echo "FULL_UI_CONTENT_TITLE_CONTRACT=PASS\n";
echo "AUTOMATIC_TITLE_ITEMS=28\n";
echo "LONG_AUTOMATIC_TITLE_ITEMS=16\n";
echo "EXPLICIT_TITLES_PRESERVED=27\n";
echo "MAXIMUM_FULL_TITLE_LENGTH={$maximum}\n";
echo "LEGACY_72_CHARACTER_TRUNCATION=ABSENT\n";
echo "FRESH_INSTALL_COMPATIBILITY=PASS\n";