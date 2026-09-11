<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$catalog = json_decode((string) file_get_contents($root . '/public_html/resources/ui-content/platform-guides.json'), true);
if (!is_array($catalog)) throw new RuntimeException('Catalog invalid.');

$items = [];
foreach ($catalog as $item) {
    if (is_array($item) && ($item['content_type'] ?? null) === 'guide' && ($item['parameterized'] ?? false) === true) {
        $items[(string) $item['key']] = $item;
    }
}

if (count($items) !== 2) throw new RuntimeException('Parameterized guide count invalid.');

$expected = [
    'core.register.guide.02' => ['mode' => 'title_body', 'parameter' => 'mobile', 'file' => 'public_html/resources/views/site/register.php'],
    'core.register-verify.guide.03' => ['mode' => 'body', 'parameter' => 'seconds', 'file' => 'public_html/resources/views/site/register-verify.php'],
];

foreach ($expected as $key => $contract) {
    $item = $items[$key] ?? null;
    if (!is_array($item)) throw new RuntimeException('Parameterized catalog item missing: ' . $key);
    if (($item['consumer_bound'] ?? false) !== true) throw new RuntimeException('Parameterized item not bound: ' . $key);
    if (($item['render_mode'] ?? null) !== $contract['mode']) throw new RuntimeException('Parameterized render mode mismatch: ' . $key);
    if (($item['template_parameters'] ?? null) !== [$contract['parameter']]) throw new RuntimeException('Parameterized allowlist mismatch: ' . $key);
    if (substr_count((string) $item['body'], '{{' . $contract['parameter'] . '}}') !== 1) throw new RuntimeException('Template placeholder mismatch: ' . $key);
    if ((string) ($item['source_file'] ?? '') !== $contract['file']) throw new RuntimeException('Consumer file mismatch: ' . $key);
}

$helper = (string) file_get_contents($root . '/public_html/app/Services/UiContent/UiContentInlineGuide.php');
foreach (['guideTemplateText(', 'guideTemplateHtml(', 'renderGuideTemplate(', 'renderTemplateForKey(', 'templateParameters('] as $marker) {
    if (!str_contains($helper, $marker)) throw new RuntimeException('Template helper marker missing: ' . $marker);
}

$register = (string) file_get_contents($root . '/public_html/resources/views/site/register.php');
if (substr_count($register, "UiContentInlineGuide::titleHtml(\n                            'core.register.guide.02'") !== 1) {
    throw new RuntimeException('Register dynamic title binding invalid.');
}
if (substr_count($register, "UiContentInlineGuide::guideTemplateHtml(\n                            'core.register.guide.02'") !== 1) {
    throw new RuntimeException('Register dynamic body binding invalid.');
}
if (substr_count($register, "'mobile' =>") !== 1) throw new RuntimeException('Register mobile parameter binding invalid.');
if (str_contains($register, 'این دعوت برای')) throw new RuntimeException('Register hardcoded dynamic body remains.');

$verify = (string) file_get_contents($root . '/public_html/resources/views/site/register-verify.php');
if (substr_count($verify, "UiContentInlineGuide::guideTemplateHtml(\n                            'core.register-verify.guide.03'") !== 1) {
    throw new RuntimeException('Register-verify dynamic body binding invalid.');
}
if (substr_count($verify, "'seconds' =>") !== 1) throw new RuntimeException('Resend seconds parameter binding invalid.');
if (str_contains($verify, 'ارسال مجدد پس از')) throw new RuntimeException('Resend hardcoded dynamic body remains.');

$normalize = (string) file_get_contents($root . '/public_html/system/Database/Migrations/NormalizeStaticGuideRenderContract.php');
$mark = (string) file_get_contents($root . '/public_html/system/Database/Migrations/MarkStaticGuideConsumersBound.php');
$seed = (string) file_get_contents($root . '/public_html/system/Database/Migrations/SeedPlatformGuideCatalog.php');

if (!str_contains($normalize, "\$item['parameterized']")) throw new RuntimeException('Static normalization compatibility filter missing.');
if (!str_contains($mark, "\$item['parameterized']")) throw new RuntimeException('Static binding compatibility filter missing.');
if (!str_contains($seed, 'count($catalog) !== 55')) throw new RuntimeException('Fresh-install catalog count not updated.');
if (substr_count($seed, "'template_parameters'") < 2) throw new RuntimeException('Fresh-install template metadata missing.');

$registry = (string) file_get_contents($root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php');
if (!str_contains($registry, 'SeedAndBindParameterizedGuides::class')) throw new RuntimeException('A2.3 migration registry marker missing.');

if (!is_file($root . '/public_html/system/Database/Migrations/SeedAndBindParameterizedGuides.php')) {
    throw new RuntimeException('A2.3 migration missing.');
}

echo "PARAMETERIZED_GUIDE_CONSUMER_BINDING_CONTRACT=PASS\n";
echo "PARAMETERIZED_GUIDES=2\n";
echo "TEMPLATE_PARAMETERS=2\n";
echo "BOUND_FIELDS=3\n";
echo "REGISTER_TITLE_BOUND=1\n";
echo "REGISTER_TEMPLATE_BOUND=1\n";
echo "RESEND_TEMPLATE_BOUND=1\n";
echo "STATIC_MIGRATION_COMPATIBILITY=PASS\n";
echo "FRESH_INSTALL_COMPATIBILITY=PASS\n";
