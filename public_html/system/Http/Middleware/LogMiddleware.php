<?php

declare(strict_types=1);

namespace IPKF\Http\Middleware;

use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Logging\RequestContext;
use IPKF\Logging\SystemLogger;

final class LogMiddleware
{
    public function handle(
        Request $request,
        Response $response,
        callable $next
    ): Response {
        RequestContext::initializeFromGlobals();

        $response
            ->header(
                'X-Request-ID',
                RequestContext::requestId()
            )
            ->header(
                'X-Correlation-ID',
                RequestContext::correlationId()
            );

        $startedAt =
            hrtime(true);

        $logger =
            new SystemLogger();

        try {
            $result =
                $next(
                    $request,
                    $response
                );

            $resolved =
                $result instanceof Response
                    ? $result
                    : $response;

            $durationMs =
                (int) round(
                    (
                        hrtime(true)
                        - $startedAt
                    )
                    / 1_000_000
                );

            $status =
                $resolved->statusCode();

            $logger->application(
                $status >= 500
                    ? 'ERROR'
                    : (
                        $status >= 400
                            ? 'WARNING'
                            : 'INFO'
                    ),
                'Http.Request.Completed',
                [
                    'module' =>
                        'http',

                    'action' =>
                        $request->method()
                        . ' '
                        . $request->uri(),

                    'result' =>
                        (string) $status,

                    'duration_ms' =>
                        $durationMs,

                    'metadata' => [
                        'method' =>
                            $request->method(),

                        'path' =>
                            $request->uri(),

                        'host' =>
                            $request->host(),

                        'status_code' =>
                            $status,
                    ],
                ]
            );

            return $resolved;

        } catch (\Throwable $exception) {
            $durationMs =
                (int) round(
                    (
                        hrtime(true)
                        - $startedAt
                    )
                    / 1_000_000
                );

            $logger->application(
                'ERROR',
                'Http.Request.Failed',
                [
                    'module' =>
                        'http',

                    'action' =>
                        $request->method()
                        . ' '
                        . $request->uri(),

                    'result' =>
                        'exception',

                    'duration_ms' =>
                        $durationMs,

                    'metadata' => [
                        'method' =>
                            $request->method(),

                        'path' =>
                            $request->uri(),

                        'host' =>
                            $request->host(),

                        'exception_class' =>
                            get_class(
                                $exception
                            ),
                    ],
                ]
            );

            throw $exception;
        }
    }
}
