<?php

namespace IPKF\Core;

class ProviderLoader
{
    protected array $providers = [];

    public function load(array $providers, Container $app): void
    {
        foreach ($providers as $provider) {

            $instance = new $provider($app);

            $instance->register();

        }
    }
}