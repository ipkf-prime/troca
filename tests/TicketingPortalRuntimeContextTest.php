<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$resolverPath =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'PortalContextResolverService.php';

$brandingPath =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'PortalBrandingService.php';


$resolver =
    file_get_contents(
        $resolverPath
    );

$branding =
    file_get_contents(
        $brandingPath
    );


if (
    !is_string($resolver)
    || !is_string($branding)
) {
    throw new RuntimeException(
        'portal_runtime_source_unreadable'
    );
}


foreach ([
    'TICKETING_PORTAL_CONTEXT_RESOLVER_V1',
    'resolveByHost',
    'resolveDefaultForRealm',
    'normalizeHost',
    'ticketing_support_portal_hosts',
    'ticketing_support_portals',
    'ticketing_support_realms',
    'ticketing_support_projects',
    "h.status =",
    "p.status =",
    "r.status =",
    'sp.is_active = 1',
] as $needle) {

    if (
        !str_contains(
            $resolver,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_resolver_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'TICKETING_PORTAL_DYNAMIC_BRANDING_V1',
    'Global System Theme',
    'Project Brand Override',
    'Portal Brand Override',
    'ticketing_support_project_brand_settings',
    'ticketing_support_portal_brand_settings',
    'systemTheme',
    'presets',
    'branding_scope',
    'has_project_override',
    'has_portal_override',
] as $needle) {

    if (
        !str_contains(
            $branding,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_branding_marker_missing:'
            . $needle
        );
    }
}


/*
 * Both runtime services are read-only.
 */
foreach ([
    'resolver' => $resolver,
    'branding' => $branding,
] as $label => $text) {

    if (
        preg_match(
            '/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i',
            preg_replace(
                '#/\*.*?\*/|//[^\r\n]*#s',
                '',
                $text
            )
            ?? ''
        ) === 1
    ) {
        throw new RuntimeException(
            $label
            . '_service_write_sql_forbidden'
        );
    }
}


/*
 * Resolver must not contain an environment Host.
 */
foreach ([
    'ticketing-dev.troca.ir',
    'ticketing.troca.ir',
    'dev.troca.ir',
] as $forbiddenHost) {

    if (
        str_contains(
            $resolver,
            $forbiddenHost
        )
        || str_contains(
            $branding,
            $forbiddenHost
        )
    ) {
        throw new RuntimeException(
            'environment_host_hardcode_forbidden'
        );
    }
}


/*
 * Branding is deliberately sparse and allowlisted.
 */
foreach ([
    "'active_preset'",
    "'brand_name'",
    "'brand_subtitle'",
    "'logo_url'",
    "'footer_text'",
    "'footer_enabled'",
] as $key) {

    if (
        !str_contains(
            $branding,
            $key
        )
    ) {
        throw new RuntimeException(
            'branding_allowlist_missing:'
            . $key
        );
    }
}


echo
    'TICKETING_PORTAL_RUNTIME_CONTEXT_CONTRACT_PASS'
    . PHP_EOL;


/*
 * ============================================================================
 * TICKETING_PORTAL_HTTP_WIRING_CONTRACT_V1
 * ============================================================================
 */

$portalLandingPath =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingPortalLandingService.php';

$moduleHostMiddlewarePath =
    $root
    . '/public_html/system/Http/Middleware/'
    . 'ModuleHostMiddleware.php';

$webRoutePath =
    $root
    . '/public_html/routes/web.php';


$portalLanding =
    file_get_contents(
        $portalLandingPath
    );

$moduleHostMiddleware =
    file_get_contents(
        $moduleHostMiddlewarePath
    );

$webRoute =
    file_get_contents(
        $webRoutePath
    );


if (
    !is_string($portalLanding)
    || !is_string($moduleHostMiddleware)
    || !is_string($webRoute)
) {
    throw new RuntimeException(
        'portal_http_wiring_source_unreadable'
    );
}


foreach ([
    'TICKETING_PORTAL_LANDING_RUNTIME_V1',
    'public_page_settings',
    'public_page_items',
    'PortalBrandingService',
    'globalAssetUrl',
    'portal_context',
] as $needle) {

    if (
        !str_contains(
            $portalLanding,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_landing_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'TICKETING_PORTAL_ROOT_HOST_BYPASS_V1',
    "request->uri() === '/'",
    "['GET', 'HEAD']",
    'PortalContextResolverService',
    'isPortalHost',
] as $needle) {

    if (
        !str_contains(
            $moduleHostMiddleware,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_middleware_marker_missing:'
            . $needle
        );
    }
}


foreach ([
    'TICKETING_PORTAL_LANDING_DISPATCH_V1',
    'PortalContextResolverService',
    'TicketingPortalLandingService',
    'X-IPKF-Portal',
    '/resources/views/site/landing.php',
] as $needle) {

    if (
        !str_contains(
            $webRoute,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_route_marker_missing:'
            . $needle
        );
    }
}


/*
 * Portal landing runtime is read-only.
 */
$withoutComments =
    preg_replace(
        '#/\*.*?\*/|//[^\r\n]*#s',
        '',
        $portalLanding
    )
    ?? '';


if (
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i',
        $withoutComments
    ) === 1
) {
    throw new RuntimeException(
        'portal_landing_write_sql_forbidden'
    );
}


/*
 * No environment host may be hardcoded.
 */
foreach ([
    'ticketing-dev.troca.ir',
    'ticketing.troca.ir',
    'dev.troca.ir',
] as $host) {

    if (
        str_contains(
            $portalLanding,
            $host
        )
        || str_contains(
            $moduleHostMiddleware,
            $host
        )
        || str_contains(
            $webRoute,
            $host
        )
    ) {
        throw new RuntimeException(
            'portal_http_environment_host_hardcode_forbidden'
        );
    }
}


echo
    'TICKETING_PORTAL_HTTP_WIRING_CONTRACT_PASS'
    . PHP_EOL;


/*
 * TICKETING_PORTAL_CONNECTION_SEMANTIC_CONTRACT_V1
 *
 * ConnectionResolver calls are intentionally formatted
 * independently from whitespace/layout.
 */
foreach ([
    'core.primary',
    'ticketing.primary',
] as $connectionName) {

    $pattern =
        '/->resolve\s*\(\s*[\'"]'
        . preg_quote(
            $connectionName,
            '/'
        )
        . '[\'"]\s*\)/s';


    if (
        preg_match(
            $pattern,
            $portalLanding
        ) !== 1
    ) {
        throw new RuntimeException(
            'portal_landing_connection_resolution_missing:'
            . $connectionName
        );
    }
}


echo
    'TICKETING_PORTAL_CONNECTION_SEMANTIC_CONTRACT_PASS'
    . PHP_EOL;
