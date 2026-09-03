<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'migration' =>
        'public_html/system/Database/Migrations/'
        . 'CreatePublicLandingFoundation.php',
    'service' =>
        'public_html/app/Services/PublicLandingService.php',
    'upload' =>
        'public_html/app/Services/PublicLandingMediaUploadService.php',
];

foreach ($files as $key => $relative) {
    $content = file_get_contents($root . '/' . $relative);

    if (!is_string($content)) {
        throw new RuntimeException("Missing {$key}");
    }

    $files[$key] = $content;
}

$requiredMigration = [
    'public_page_settings',
    'public_page_items',
    'item_type',
    'mobile_image_url',
    'starts_at',
    'ends_at',
    'sort_order',
];

foreach ($requiredMigration as $marker) {
    if (!str_contains($files['migration'], $marker)) {
        throw new RuntimeException(
            'Migration marker missing: ' . $marker
        );
    }
}

$requiredService = [
    'publicPage',
    'adminPage',
    'saveSettings',
    'saveItem',
    'deleteItem',
    'Version::current()',
    'PersianDate::fromGregorianDate',
    'admin.settings.manage',
];

foreach ($requiredService as $marker) {
    if (!str_contains($files['service'], $marker)) {
        throw new RuntimeException(
            'Service marker missing: ' . $marker
        );
    }
}

foreach (
    ['image/jpeg', 'image/png', 'image/webp', 'getimagesize']
    as $marker
) {
    if (!str_contains($files['upload'], $marker)) {
        throw new RuntimeException(
            'Upload marker missing: ' . $marker
        );
    }
}

if (str_contains($files['upload'], 'image/svg+xml')) {
    throw new RuntimeException('Unsafe SVG upload enabled.');
}

echo "PUBLIC_LANDING_FOUNDATION_TEST=PASS\n";
