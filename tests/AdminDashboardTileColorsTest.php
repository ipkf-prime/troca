<?php

$root = dirname(__DIR__);
$panel = file_get_contents($root . '/public_html/app/Services/AdminPanelService.php');
$css = file_get_contents($root . '/public_html/public/assets/admin/css/admin.css');

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expected = [
    'users' => 'blue',
    'organization' => 'teal',
    'system' => 'purple',
    'work' => 'green',
    'automation' => 'indigo',
    'reports' => 'amber',
    'support' => 'rose',
];

foreach ($expected as $module => $color) {
    $pattern = "/'key' => '{$module}'.*?'color' => '{$color}'/s";
    $expect(preg_match($pattern, $panel) === 1, "Module {$module} must use fixed color {$color}.");
    $expect(str_contains($css, ".admin-module-launcher__tile--{$color}"), "Launcher CSS class for {$color} is missing.");
    $expect(str_contains($css, ".admin-module-hub--{$color}"), "Hub CSS class for {$color} is missing.");
}

$expect(count(array_unique(array_values($expected))) === 7, 'The seven homepage module tile colors must be non-repeating.');
$expect(!preg_match('/\b(?:rand|mt_rand|random_int|shuffle)\s*\(/i', $panel . $css), 'Dashboard tile colors must not be random.');
$expect(str_contains($panel, "'work' => fn (string \$path): string => \$urls->workLaunch(\$path)"), 'Work module launch URL must use the Work SSO launcher.');

foreach (['blue', 'teal', 'purple', 'green', 'indigo', 'amber', 'rose'] as $color) {
    $expect(substr_count($css, ".admin-module-launcher__tile--{$color}") >= 1, "Deployable admin.css must define {$color} launcher color.");
}

echo "Admin dashboard tile color checks passed.\n";
