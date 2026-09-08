<?php

namespace IPKF\Http\Middleware;

use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Support\ApplicationUrlRegistry;

class ModuleHostMiddleware
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        /*
         * TICKETING_PORTAL_ROOT_HOST_BYPASS_V1
         *
         * An explicitly configured active Portal Host may render
         * its public root page before module-host redirection.
         *
         * The bypass is deliberately limited to GET/HEAD root.
         * Every other request keeps the existing Module Host policy.
         */
        if (
            $request->uri() === '/'
            &&
            in_array(
                strtoupper(
                    $request->method()
                ),
                ['GET', 'HEAD'],
                true
            )
            &&
            class_exists(
                \App\Services\Ticketing\PortalContextResolverService::class
            )
        ) {
            try {

                if (
                    (
                        new \App\Services\Ticketing\PortalContextResolverService()
                    )->isPortalHost(
                        $request->host()
                    )
                ) {
                    return $next(
                        $request,
                        $response
                    );
                }

            } catch (\Throwable) {
                /*
                 * Fail closed to the existing Module Host policy.
                 */
            }
        }

        $urls = new ApplicationUrlRegistry();

        if ($urls->guardEnabled() && !$urls->allowed($request->host())) {
            return $response->status(421)->send('421 - Misdirected Request');
        }

        $target = $urls->redirectTarget($request->host(), (string) ($_SERVER['REQUEST_URI'] ?? $request->uri()));
        if ($target !== null) {
            return $response->redirect($target);
        }

        return $next($request, $response);
    }
}
