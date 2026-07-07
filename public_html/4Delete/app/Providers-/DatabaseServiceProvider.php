<?php

namespace IPKF\Providers;

use IPKF\Core\ServiceProvider;
use IPKF\Database\DatabaseManager;
use IPKF\Database\DB;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $manager = new DatabaseManager();

        DB::setManager($manager);

        $this->app->container()->singleton(DatabaseManager::class, $manager);
    }
}