<?php

namespace App\Providers;

use App\Core\DatabaseManager;

class DatabaseServiceProvider
{
    public function register()
    {
        // ใช้ singleton เพื่อให้มี instance เดียวตลอดการทำงาน
        app()->singleton(DatabaseManager::class, function () {
            return new DatabaseManager();
        });
    }

    public function boot()
    {
        // ใช้สำหรับบูตบางอย่างหากต้องการ เช่นการเชื่อมต่อฐานข้อมูล
    }
}
