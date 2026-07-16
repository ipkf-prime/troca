<?php

namespace IPKF\Database\Connections;

use PDO;

class ApplicationConnectionResolver
{
    public function __construct(private ?ConnectionResolver $resolver = null)
    {
        $this->resolver ??= new ConnectionResolver();
    }

    public function primary(string $applicationCode): PDO
    {
        $connectionName = $applicationCode === 'core'
            ? 'core.primary'
            : "{$applicationCode}.primary";

        return $this->resolver->resolve($connectionName);
    }
}
