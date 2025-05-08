<?php
namespace App\Providers;

use App\Core\App;

class AppServiceProvider
{
    public function boot(App $app): void
    {
        // ตัวอย่างการลงทะเบียน service
        $app->register(\App\Service\UserService::class);
    }
}
