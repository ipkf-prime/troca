<?php

namespace App\Services;

use RuntimeException;

class NotificationGatewayAdapterRegistry extends BaseService
{
    /** @var NotificationGatewayAdapterInterface[] */
    private array $adapters;

    public function __construct(?array $adapters = null)
    {
        $this->adapters = $adapters ?? [
            new NotificationSmtpGatewayAdapter(),
            new NotificationKavenegarGatewayAdapter(),
            new NotificationBaleGatewayAdapter(),
        ];
    }

    public function adapter(
        array $instance
    ): NotificationGatewayAdapterInterface {
        foreach ($this->adapters as $adapter) {
            if (
                $adapter
                    instanceof NotificationGatewayAdapterInterface
                && $adapter->supports($instance)
            ) {
                return $adapter;
            }
        }

        throw new RuntimeException(
            'notification_gateway_adapter_unsupported'
        );
    }
}
