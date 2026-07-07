<?php

namespace IPKF\Http;

class Kernel
{
    protected array $middleware = [
        \IPKF\Http\Middleware\LogMiddleware::class,
        \IPKF\Http\Middleware\CsrfMiddleware::class,
    ];

    public function middleware(): array
    {
        return $this->middleware;
    }

    public function handle(Request $request, Response $response, callable $destination): Response
    {
        return (new Pipeline())
            ->through($this->middleware)
            ->send($request, $response)
            ->then($destination);
    }
}
