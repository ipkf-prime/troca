<?php

namespace IPKF\Core;

use IPKF\Security\Firewall;
use IPKF\Foundation\Maintenance;
use IPKF\Foundation\License;
use IPKF\Events\EventBus;

class Kernel
{
    protected array $bootstrappers = [
        Maintenance::class,
        License::class,
        Firewall::class,
    ];

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