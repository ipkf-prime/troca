<?php
declare(strict_types=1);
/**
 * Safir UI v2.1 structural contract. Strictly static: no bootstrap, DB or API.
 */
$base = dirname(__DIR__) . '/public_html';
$view = file_get_contents($base . '/resources/views/admin/partials/bale-safir-campaign.php');
$menu = file_get_contents($base . '/app/Services/DynamicAdminNavigationService.php');
$route = file_get_contents($base . '/routes/bale-safir-campaigns.php');
if ($view === false || $menu === false || $route === false) {
    throw new RuntimeException('SAFIR_UI_SOURCE_MISSING');
}
$requirements = [
    'TEMPLATE_MANAGER_TITLE' => str_contains($view, 'مدیریت قالب‌های سفیر بله'),
    'NO_PROVIDER_SHORTCUT' => !str_contains($view, 'href="/admin/communications/settings?section=providers"'),
    'PERSIAN_SAFIR_MENU' => str_contains($menu, "'title' => 'قالب‌های سفیر بله'"),
    'SAME_SAFIR_ROUTE' => str_contains($route, "'/admin/communications/safir'"),
    'SAME_SAFIR_POST_ROUTE' => str_contains($route, "'/admin/communications/settings/safir'"),
    'PERSIAN_PAGE_TITLE' => str_contains($route, "'title' => 'مدیریت قالب‌های سفیر بله'"),
    'XLSX_PREVIEW_SECTION' => str_contains($view, 'پیش‌نمایش ارسال از فایل اکسل'),
    'NO_CAMPAIGN_MENU_TITLE' => !str_contains($view, 'مدیریت قالب‌ها و کمپین‌های سفیر بله'),
    'BOT_ID_HIDDEN_FROM_LIST' => !str_contains($view, '<th>شناسه بازو</th>'),
    'BOT_ID_RETAINED_IN_EDITOR' => str_contains($view, 'name="bot_id"'),
    'FILE_INPUT_STILL_NATIVE' => str_contains($view, 'type="file" name="recipients" accept=".xlsx" required'),
    'FILE_PICKER_ACCESSIBLE' => str_contains($view, 'data-safir-file-input') && str_contains($view, 'aria-label="انتخاب فایل اکسل مخاطبان"'),
    'FILE_NAME_LABEL' => str_contains($view, 'data-safir-file-name'),
    'THREE_EDITOR_TABS' => substr_count($view, 'data-safir-tab="') === 3,
    'CUSTOM_VARIABLE_ROWS_PRESERVED' => str_contains($view, 'variable_items[') && str_contains($view, 'data-safir-add'),
    'MAX_100_VARIABLES_PRESERVED' => str_contains($view, 'n >= 100'),
    'SAVE_ACTION_PRESERVED' => str_contains($view, 'name="action" value="save_template"'),
    'IMPORT_ACTION_PRESERVED' => str_contains($view, 'name="action" value="import"'),
    'SEND_GUARD_PRESENT' => str_contains($view, "empty(\$safir['send_enabled'])"),
    'DISPATCH_FORM_UNMODIFIED' => str_contains($view, 'id="safir-dispatch-form"'),
    'INLINE_RESPONSIVE_STYLE' => str_contains($view, '@media(max-width:760px)'),
];
preg_match('/<table class="safir-table">[\s\S]*?<thead><tr>(.*?)<\/tr><\/thead>/', $view, $head);
$requirements['SIX_COLUMN_CATALOG'] = isset($head[1]) && substr_count($head[1], '<th>') === 6;
foreach ($requirements as $code => $passed) {
    if (!$passed) {
        throw new RuntimeException('SAFIR_UI_V21_CONTRACT_FAILED:' . $code);
    }
    echo $code . "=PASS\n";
}
echo "SAFIR_UI_V21_STATIC_SMOKE=PASS\n";
