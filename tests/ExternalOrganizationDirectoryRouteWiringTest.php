<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$routeSource =
    file_get_contents(
        $root
        . '/public_html/routes/web.php'
    );

$rbacSource =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );

$serviceSource =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/Correspondence/'
        . 'ExternalOrganizationDirectoryService.php'
    );

foreach ([
    'routes' => $routeSource,
    'rbac' => $rbacSource,
    'service' => $serviceSource,
] as $name => $source) {
    if (!is_string($source)) {
        fwrite(
            STDERR,
            "FAIL: {$name} source unavailable.\n"
        );
        exit(1);
    }
}

$routes = [
    '/admin/automation/external-organizations',
    '/admin/automation/external-organizations/save',
    '/admin/automation/external-organizations/deactivate',
    '/admin/automation/external-organizations/contact-points/save',
    '/admin/automation/external-organizations/contact-points/deactivate',
    '/admin/automation/external-organizations/contact-methods/save',
    '/admin/automation/external-organizations/contact-methods/deactivate',
    '/admin/automation/external-organizations/addresses/save',
    '/admin/automation/external-organizations/addresses/deactivate',
];

foreach ($routes as $route) {
    if (
        !str_contains(
            $routeSource,
            $route
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Route missing: {$route}\n"
        );
        exit(1);
    }

    if (
        !str_contains(
            $rbacSource,
            "'{$route}' => 'automation.external_directory.manage'"
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: RBAC mapping missing: {$route}\n"
        );
        exit(1);
    }
}

foreach ([
    'ExternalOrganizationDirectoryService',
    'new \\IPKF\\Security\\Csrf()',
    '$request->all()',
    '$adminGuard',
    'http_build_query',
] as $required) {
    if (
        !str_contains(
            $routeSource,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Route contract missing: {$required}\n"
        );
        exit(1);
    }
}

foreach ([
    'deactivateOrganization',
    'deactivateContactPoint',
    'deactivateContactMethod',
    'deactivateAddress',
] as $required) {
    if (
        !str_contains(
            $serviceSource,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Service contract missing: {$required}\n"
        );
        exit(1);
    }
}

foreach ([
    '{organization_reference}',
    '{contact_point_reference}',
    '{method_reference}',
    '{address_reference}',
] as $forbidden) {
    if (
        str_contains(
            $routeSource,
            '/external-organizations/'
            . $forbidden
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Dynamic directory path detected: {$forbidden}\n"
        );
        exit(1);
    }
}

echo "External organization directory route wiring checks passed.\n";
