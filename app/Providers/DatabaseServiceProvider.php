<?php

namespace App\Providers;

use App\Core\App;
use App\Core\DatabaseManager;

class DatabaseServiceProvider
{
    public function boot(App $app)
    {
        // ใช้ singleton เพื่อให้มี instance เดียวตลอดการทำงาน
        $app->singleton('db', function () {
            return new DatabaseManager();
        });
    }
}
