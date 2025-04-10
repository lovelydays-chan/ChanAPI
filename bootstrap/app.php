<?php

use Dotenv\Dotenv;
use App\Core\App;

// โหลดไฟล์ .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// เริ่มต้นแอปพลิเคชัน
$app = new App();

require_once __DIR__ . '/../app/Providers/service.php';

// ตรวจสอบว่ากำลังใช้งานในโหมดทดสอบหรือไม่
if (getenv('APP_ENV') === 'testing') {
    // ถ้าเป็นโหมดทดสอบ ก็ไม่ต้องให้ header หรือ response จริง
    $app->getResponse()->asTest();
}

// รวมเส้นทาง API
require_once __DIR__ . '/../app/Routes/api.php';

// ส่งคืนแอปพลิเคชัน
return $app;
