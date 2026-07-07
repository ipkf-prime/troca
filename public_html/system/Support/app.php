<?php

use IPKF\Core\Application;

function app(?string $key = null)
{
    $app = new Application();

    if ($key) {
        return $app->container()->make($key);
    }

    return $app;
}