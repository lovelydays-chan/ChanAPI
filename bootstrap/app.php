<?php

use App\Core\App;
use Dotenv\Dotenv;

// โหลดไฟล์ .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// เริ่มต้นแอปพลิเคชัน
$app = new App();

$config = require __DIR__ . '/../config/app.php';

// ลงทะเบียน Providers (แบบ Singleton) และ บูต Providers
foreach ($config['providers'] as $alias => $providerClass) {
    // ลงทะเบียน Provider
    $app->singleton($alias, function () use ($app, $providerClass) {
        return new $providerClass($app);
    });

    // บูต Provider (ถ้ามีเมธอด boot)
    $provider = $app->make($alias);
    if (method_exists($provider, 'boot')) {
        $provider->boot($app);
    }
}

// โหมดทดสอบ
if (getenv('APP_ENV') === 'testing') {
    $app->getResponse()->asTest();
}

return $app;