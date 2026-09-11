<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$catalog = json_decode((string) file_get_contents($root . '/public_html/resources/ui-content/platform-guides.json'), true);
if (!is_array($catalog)) throw new RuntimeException('Catalog invalid.');

$helperPath = $root . '/public_html/app/Services/UiContent/UiContentInlineGuide.php';
$migrationPath = $root . '/public_html/system/Database/Migrations/MarkStaticNoticeErrorConsumersBound.php';
if (!is_file($helperPath) || !is_file($migrationPath)) throw new RuntimeException('A2.2-S2 infrastructure missing.');

$helper = (string) file_get_contents($helperPath);
foreach ([
    'resolveGuide(',
    'resolveNotice(',
    'resolveError(',
    'fallbackGuide(',
    'fallbackNotice(',
    'fallbackError(',
    'noticeTitleHtml(',
    'noticeBodyHtml(',
    'errorTitleHtml(',
    'errorBodyHtml(',
    'surfaceOverrideState(',
    'overrideEligible(',
] as $marker) {
    if (!str_contains($helper, $marker)) throw new RuntimeException('Helper marker missing: ' . $marker);
}

$items = 0;
$files = [];
$noticeTitles = 0;
$noticeBodies = 0;
$errorTitles = 0;
$errorBodies = 0;

foreach ($catalog as $item) {
    if (!is_array($item) || !in_array(($item['content_type'] ?? null), ['notice', 'error'], true)) continue;

    if (($item['consumer_bound'] ?? false) !== true) {
        throw new RuntimeException('Item not marked bound: ' . ($item['key'] ?? ''));
    }

    $items++;
    $sourceFile = $root . '/' . (string) $item['source_file'];
    if (!is_file($sourceFile)) throw new RuntimeException('Consumer file missing.');

    $source = (string) file_get_contents($sourceFile);
    $key = (string) $item['key'];
    $prefix = ($item['content_type'] ?? null) === 'notice' ? 'notice' : 'error';

    $body = substr_count($source, 'UiContentInlineGuide::' . $prefix . "BodyHtml('" . $key . "'");
    if ($body !== 1) throw new RuntimeException('Body marker invalid: ' . $key);

    $title = substr_count($source, 'UiContentInlineGuide::' . $prefix . "TitleHtml('" . $key . "'");
    if (($item['render_mode'] ?? null) === 'title_body') {
        if ($title !== 1) throw new RuntimeException('Title marker invalid: ' . $key);
    } elseif ($title !== 0) {
        throw new RuntimeException('Unexpected title marker: ' . $key);
    }

    if ($prefix === 'notice') {
        $noticeBodies += $body;
        $noticeTitles += $title;
    } else {
        $errorBodies += $body;
        $errorTitles += $title;
    }

    $files[(string) $item['source_file']] = true;
}

if (
    $items !== 6
    || count($files) !== 6
    || $noticeBodies !== 5
    || $noticeTitles !== 3
    || $errorBodies !== 1
    || $errorTitles !== 0
) {
    throw new RuntimeException('Notice/error binding counts invalid.');
}

echo "STATIC_NOTICE_ERROR_CONSUMER_BINDING_CONTRACT=PASS\n";
echo "BOUND_ITEMS=6\n";
echo "BOUND_CONSUMER_FILES=6\n";
echo "NOTICE_BODY_HTML=5\n";
echo "NOTICE_TITLE_HTML=3\n";
echo "ERROR_BODY_HTML=1\n";
echo "ERROR_TITLE_HTML=0\n";
echo "TOTAL_BINDABLE_FIELDS=9\n";
