<?php

namespace IPKF\Providers;

use IPKF\Core\Container;

abstract class ServiceProvider
{
    protected Container $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    abstract public function register(): void;
}