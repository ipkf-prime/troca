<?php

$root = dirname(__DIR__);
$panel = file_get_contents($root . '/public_html/app/Services/AdminPanelService.php');
$layout = file_get_contents($root . '/public_html/resources/views/admin/layout.php');
$css = $root . '/public_html/public/assets/admin/css/automation.css';

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(str_contains($panel, 'isAutomationHost') && str_contains($panel, 'automationNavigation'), 'Automation navigation must be selected by the request host.');
$expect(str_contains($panel, 'بازگشت به پنل اصلی') && str_contains($panel, "'url' => '/admin/logout'"), 'Automation account menu must only provide the central-panel return and logout actions.');
$expect(str_contains($layout, 'data-admin-shell-kind') && str_contains($layout, '/assets/admin/css/automation.css'), 'The layout must expose and load the Automation-specific shell.');
$expect(is_file($css) && filesize($css) > 300, 'Automation shell stylesheet must exist.');

echo "Automation module shell checks passed.\n";
