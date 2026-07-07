<?php

namespace IPKF\Http\Middleware;

use IPKF\Http\Request;
use IPKF\Http\Response;

class LogMiddleware
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        file_put_contents(
            BASE_PATH . '/storage/logs/request.log',
            date('Y-m-d H:i:s') . " " . $request->uri() . PHP_EOL,
            FILE_APPEND
        );

        return $next($request, $response);
    }
}
