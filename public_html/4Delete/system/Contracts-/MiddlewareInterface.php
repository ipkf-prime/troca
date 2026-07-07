<?php

namespace IPKF\Contracts;

use IPKF\Http\Request;
use IPKF\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next);
}