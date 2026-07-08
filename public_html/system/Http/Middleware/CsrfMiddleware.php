<?php

namespace IPKF\Http\Middleware;

use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Security\Csrf;

class CsrfMiddleware
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {

            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_token', '');

            $csrf = new Csrf();

            if (!$csrf->check($token)) {
                return $response->status(419)->json([
                    'status' => 'error',
                    'message' => 'CSRF Token Mismatch',
                ]);
            }
        }

        return $next($request, $response);
    }
}
