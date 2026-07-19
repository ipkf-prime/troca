<?php

$root = dirname(__DIR__);
$css = file_get_contents($root . '/public_html/public/assets/admin/css/admin.css');
$assets = [
    'Vazirmatn-Arabic.woff2',
    'Vazirmatn-Latin.woff2',
];

foreach ($assets as $asset) {
    $path = $root . '/public_html/public/assets/admin/webfonts/' . $asset;
    if (!is_file($path) || filesize($path) < 10000) {
        throw new RuntimeException('Admin font asset is missing or incomplete: ' . $asset);
    }
    if (!str_contains($css, '/assets/admin/webfonts/' . $asset)) {
        throw new RuntimeException('Admin stylesheet does not load font asset: ' . $asset);
    }
}

if (!str_contains($css, 'font-family: "Vazirmatn"')) {
    throw new RuntimeException('Admin stylesheet must register Vazirmatn.');
}

echo "Admin font asset checks passed.\n";
