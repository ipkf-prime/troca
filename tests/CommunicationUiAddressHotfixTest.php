<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$web = $read('public_html/routes/web.php');
$layout = $read(
    'public_html/resources/views/admin/layout.php'
);
$form = $read(
    'public_html/resources/views/admin/admin-user-form.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'AdminUserManagementService.php'
);
$repository = $read(
    'public_html/app/Repositories/'
    . 'AdminUserManagementRepository.php'
);

$expect(
    str_contains($web, 'partials/view-helpers.php'),
    'Admin helpers are not loaded before view rendering.'
);

$expect(
    str_contains($layout, 'data-admin-nav-group-toggle')
    && str_contains($layout, 'data-admin-nav-children')
    && str_contains($layout, 'hidden'),
    'Sidebar child navigation is not collapsible.'
);

$expect(
    str_contains($service, "'address_records' => []")
    && str_contains($repository, 'addressRecordsForPerson')
    && str_contains(
        $repository,
        'AND address_type_id = ?'
    ),
    'Address records are not separated by address type.'
);

$expect(
    str_contains($form, 'data-address-records')
    && str_contains(
        $form,
        'loadSelectedAddressType'
    )
    && str_contains(
        $form,
        "addressLine.value ="
    ),
    'Address type selector does not load or clear form state.'
);

echo "Communication UI and address type hotfix checks passed.\n";
