<?php

namespace IPKF\Core;

abstract class Middleware
{
    abstract public function handle(Request $request): bool;
}