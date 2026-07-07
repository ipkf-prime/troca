<?php

namespace IPKF\Core;

use IPKF\Http\Kernel;
use IPKF\Routing\Router;
use IPKF\Http\Request;
use IPKF\Http\Response;

class Application
{
    protected Container $container;

    protected Router $router;

    protected Kernel $kernel;

    public function __construct()
    {
        $this->container = new Container();
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);

        $this->router = new Router($this->container);
        $this->kernel = new Kernel();

        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Kernel::class, $this->kernel);
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function run(): void
    {
        $request = Request::capture();
        $response = new Response();

        $response = $this->kernel->handle(
            $request,
            $response,
            fn (Request $request, Response $response): Response => $this->router->dispatch($request, $response)
        );

        $response->emit();
    }
}
