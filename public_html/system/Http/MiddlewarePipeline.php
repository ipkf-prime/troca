<?php

namespace IPKF\Http;

class MiddlewarePipeline
{
    protected array $middlewares = [];

    public function pipe(array $middlewares): self
    {
        $this->middlewares = $middlewares;

        return $this;
    }

    public function handle(Request $request, callable $destination)
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),

            function ($next, $middleware) {

                return function ($request) use ($next, $middleware) {

                    return (new $middleware)->handle($request, $next);

                };

            },

            $destination
        );

        return $pipeline($request);
    }
}