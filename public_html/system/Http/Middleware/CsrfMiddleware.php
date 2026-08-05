<?php

namespace IPKF\Http\Middleware;

use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Security\Csrf;

class CsrfMiddleware
{
    public function handle(
        Request $request,
        Response $response,
        callable $next
    ): Response {
        if ($this->isExempt($request)) {
            return $next($request, $response);
        }

        if (in_array(
            $request->method(),
            ['POST', 'PUT', 'DELETE'],
            true
        )) {
            $token =
                $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $request->input('_token', '');

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

    private function isExempt(Request $request): bool
    {
        return strtoupper($request->method()) === 'POST'
            && preg_match(
                '#^/webhooks/notifications/bale/'
                . 'npi_[a-f0-9]{24}/'
                . '[a-f0-9]{64}/?$#D',
                $request->uri()
            ) === 1;
    }
}
