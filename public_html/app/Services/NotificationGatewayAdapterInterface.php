<?php

namespace App\Services;

interface NotificationGatewayAdapterInterface
{
    public function supports(array $instance): bool;

    public function send(
        array $instance,
        array $message
    ): array;
}
