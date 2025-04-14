<?php
// ในไฟล์ที่ใช้ register Service Providers
$app->register(App\Service\UserService::class);
$app->register(App\Providers\DatabaseServiceProvider::class);