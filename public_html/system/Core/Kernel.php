<?php

namespace IPKF\Core;

use IPKF\Events\EventBus;

class Kernel
{
    protected array $bootstrappers = [];

    public function handle($request): bool
    {
        foreach ($this->bootstrappers as $bootstrapper) {

            $instance = new $bootstrapper();

            if (!$instance->handle($request)) {
                return false;
            }
        }

        EventBus::dispatch('kernel.booted', $request);

        return true;
    }
}
