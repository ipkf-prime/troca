<?php

namespace IPKF\Http;

class Pipeline
{
    protected array $middlewares = [];

    public function send(Request $request, Response $response)
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),

            function ($next, $middleware) {

                return function ($request, $response) use ($next, $middleware) {

                    return (new $middleware)->handle($request, $response, $next);

                };

            },

            function ($request, $response) {
                return null;
            }
        );

        return $pipeline($request, $response);
    }

    public function through(array $middlewares): self
    {
        $this->middlewares = $middlewares;
        return $this;
    }
}