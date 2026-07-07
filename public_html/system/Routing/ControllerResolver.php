<?php

namespace IPKF\Routing;

use IPKF\Core\Container;

class ControllerResolver
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function resolve(callable|array $action)
    {
        if (is_callable($action)) {
            return $action;
        }

        if (is_array($action)) {

            [$class, $method] = $action;

            $instance = $this->container->make($class);

            return [$instance, $method];
        }

        return $action;
    }
}
