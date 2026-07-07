<?php

namespace IPKF\Http;

class Pipeline
{
    protected array $middlewares = [];

    protected Request $request;

    protected Response $response;

    public function send(Request $request, Response $response): self
    {
        $this->request = $request;
        $this->response = $response;

        return $this;
    }

    public function through(array $middlewares): self
    {
        $this->middlewares = $middlewares;
        return $this;
    }

    public function then(callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn (callable $next, string $middleware): callable => function (Request $request, Response $response) use ($next, $middleware) {
                return (new $middleware())->handle($request, $response, $next);
            },
            $destination
        );

        $result = $pipeline($this->request, $this->response);

        return $result instanceof Response ? $result : $this->response;
    }
}
