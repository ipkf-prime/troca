<?php

namespace IPKF\Providers;

use IPKF\Core\ServiceProvider;
use IPKF\Core\Logger;
use IPKF\Core\Session;
use IPKF\Core\Cookie;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app
            ->container()
            ->singleton(Logger::class,new Logger());

        $this->app
            ->container()
            ->singleton(Session::class,new Session());

        $this->app
            ->container()
            ->singleton(Cookie::class,new Cookie());
    }

    public function boot(): void
    {
    }
}