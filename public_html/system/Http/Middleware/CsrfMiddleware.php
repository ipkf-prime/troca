<?php

namespace App\Http\Middleware;

use IPKF\Contracts\MiddlewareInterface;
use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Security\Csrf;

class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {

            $token = $_POST['_token'] ?? '';

            $csrf = new Csrf();

            if (!$csrf->check($token)) {

                http_response_code(419);

                $response->send("CSRF Token Mismatch");

                return;
            }
        }

        return $next($request, $response);
    }
}