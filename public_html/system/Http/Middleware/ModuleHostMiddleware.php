<?php

namespace IPKF\Http\Middleware;

use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Support\ApplicationUrlRegistry;

class ModuleHostMiddleware
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
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
