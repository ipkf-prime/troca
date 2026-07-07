<?php

namespace IPKF\Http\Middleware;

use IPKF\Http\Request;
use IPKF\Http\Response;

class AuthMiddleware
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        return $next($request, $response);
    }
}
