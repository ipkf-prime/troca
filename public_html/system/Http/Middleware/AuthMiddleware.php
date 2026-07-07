<?php

namespace App\Http\Middleware;

use IPKF\Contracts\MiddlewareInterface;
use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Auth\Auth;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next)
    {
        $auth = new Auth();

        if (!$auth->check()) {

            http_response_code(401);

            $response->send("Unauthorized");

            return;
        }

        return $next($request, $response);
    }
}