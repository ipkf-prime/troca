<?php

namespace IPKF\Core;

class ProviderRepository
{
    protected Application $app;

    protected array $providers = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function load(array $providers): void
    {
        foreach ($providers as $provider) {

            $object = new $provider($this->app);

            $object->register();

            $this->providers[] = $object;
        }

        foreach ($this->providers as $provider) {

            $provider->boot();

        }
    }
}