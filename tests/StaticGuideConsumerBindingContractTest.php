<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$catalog = json_decode(
    (string) file_get_contents(
        $root . '/public_html/resources/ui-content/platform-guides.json'
    ),
    true
);

if (!is_array($catalog)) {
    throw new RuntimeException('Catalog invalid.');
}

$helperPath = $root . '/public_html/app/Services/UiContent/UiContentInlineGuide.php';
$migrationPath = $root . '/public_html/system/Database/Migrations/MarkStaticGuideConsumersBound.php';

if (!is_file($helperPath) || !is_file($migrationPath)) {
    throw new RuntimeException('S2 infrastructure missing.');
}

$helper = (string) file_get_contents($helperPath);

foreach (
    [
        'titleText(',
        'bodyText(',
        'titleHtml(',
        'bodyHtml(',
        'resolveGuide(',
        'fallbackGuide(',
        'surfaceOverrideState(',
        'overrideEligible(',
        'catalog_fallback',
    ]
    as $marker
) {
    if (!str_contains($helper, $marker)) {
        throw new RuntimeException('Helper marker missing: ' . $marker);
    }
}

$guides = 0;
$files = [];
$bodyHtmlTotal = 0;
$bodyTextTotal = 0;
$titleHtmlTotal = 0;
$titleTextTotal = 0;

foreach ($catalog as $item) {
    if (!is_array($item) || ($item['content_type'] ?? null) !== 'guide') {
        continue;
    }

    if (($item['parameterized'] ?? false) === true) {
        continue;
    }

    if (($item['consumer_bound'] ?? false) !== true) {
        throw new RuntimeException('Guide not marked bound: ' . ($item['key'] ?? ''));
    }

    $guides++;

    $sourceFile = $root . '/' . (string) $item['source_file'];
    if (!is_file($sourceFile)) {
        throw new RuntimeException('Consumer source missing.');
    }

    $source = (string) file_get_contents($sourceFile);
    $key = (string) $item['key'];

    $bodyHtml = substr_count(
        $source,
        "UiContentInlineGuide::bodyHtml('" . $key . "'"
    );
    $bodyText = substr_count(
        $source,
        "UiContentInlineGuide::bodyText('" . $key . "'"
    );

    if (($bodyHtml + $bodyText) !== 1) {
        throw new RuntimeException('Body binding marker invalid: ' . $key);
    }

    $bodyHtmlTotal += $bodyHtml;
    $bodyTextTotal += $bodyText;

    $titleHtml = substr_count(
        $source,
        "UiContentInlineGuide::titleHtml('" . $key . "'"
    );
    $titleText = substr_count(
        $source,
        "UiContentInlineGuide::titleText('" . $key . "'"
    );

    if (($item['render_mode'] ?? null) === 'title_body') {
        if (($titleHtml + $titleText) !== 1) {
            throw new RuntimeException('Title binding marker invalid: ' . $key);
        }
    } elseif (($titleHtml + $titleText) !== 0) {
        throw new RuntimeException('Unexpected title binding: ' . $key);
    }

    $titleHtmlTotal += $titleHtml;
    $titleTextTotal += $titleText;
    $files[(string) $item['source_file']] = true;
}

if (
    $guides !== 47
    || count($files) !== 24
    || $bodyHtmlTotal !== 43
    || $bodyTextTotal !== 4
    || $titleHtmlTotal !== 22
    || $titleTextTotal !== 0
) {
    throw new RuntimeException(
        'Mixed binding counts invalid: '
        . 'guides=' . $guides
        . ', files=' . count($files)
        . ', bodyHtml=' . $bodyHtmlTotal
        . ', bodyText=' . $bodyTextTotal
        . ', titleHtml=' . $titleHtmlTotal
        . ', titleText=' . $titleTextTotal
    );
}

$registry = (string) file_get_contents(
    $root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php'
);

if (!str_contains($registry, 'MarkStaticGuideConsumersBound::class')) {
    throw new RuntimeException('Binding migration registry marker missing.');
}

echo "STATIC_GUIDE_CONSUMER_BINDING_CONTRACT=PASS\n";
echo "STATIC_GUIDES=47\n";
echo "BOUND_CONSUMER_FILES=24\n";
echo "BODY_HTML=43\n";
echo "BODY_TEXT=4\n";
echo "TITLE_HTML=22\n";
echo "TITLE_TEXT=0\n";
echo "TOTAL_BINDABLE_FIELDS=69\n";
