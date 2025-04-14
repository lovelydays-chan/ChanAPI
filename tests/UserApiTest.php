<?php

use PHPUnit\Framework\TestCase;
use App\Controllers\UserController;
use App\Core\DatabaseManager;

class UserApiTest extends TestCase
{
    protected $app;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('APP_ENV=testing');

        // ตั้งค่า App
        $this->app = require __DIR__ . '/../bootstrap/app.php';

        // เปิดโหมดทดสอบสำหรับ Response
        $this->app->getResponse()->asTest();

        // กำหนดเส้นทาง (routes)
        $this->app->addRoute('GET', '/api/users', UserController::class, 'index');
        $this->app->addRoute('POST', '/api/users', UserController::class, 'store');
        $this->app->addRoute('GET', '/api/users/{id}', UserController::class, 'show');
        $this->app->addRoute('PUT', '/api/users/{id}', UserController::class, 'update');
        $this->app->addRoute('DELETE', '/api/users/{id}', UserController::class, 'delete');

        // สร้างฐานข้อมูลในหน่วยความจำ (SQLite)
        $dbManager = $this->app->get(DatabaseManager::class);
        $pdo = $dbManager->getConnection('sqlite');
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // เพิ่มข้อมูลตัวอย่าง
        $pdo->exec("
            INSERT INTO users (name, email, password) VALUES
            ('John Doe', 'john.doe@example.com', 'password123'),
            ('Jane Doe', 'jane.doe@example.com', 'password123')
        ");
    }

    protected function tearDown(): void
    {
        // ลบตารางหลังจากทดสอบ
        $pdo = $this->app->get(DatabaseManager::class)->getConnection('sqlite');
        $pdo->exec("DROP TABLE IF EXISTS users");

        parent::tearDown();
    }

    public function testIndex()
    {
        // ส่งคำขอ GET ไปยัง API
        $response = $this->app->test('GET', '/api/users');

        // ตรวจสอบผลลัพธ์ที่ได้รับจาก response
        $this->assertEquals(200, $response['status']);
        $this->assertIsArray($response['body']);
        $this->assertNotEmpty($response['body']);
    }

    public function testShow()
    {
        $response = $this->app->test('GET', "/api/users/1");
        $this->assertEquals(200, $response['status']);
        $this->assertIsArray($response['body']['data']);
        $this->assertEquals(1, $response['body']['data']['id']);
    }

    public function testStore()
    {
        $postData = [
            'name' => 'Test User',
            'email' => 'test001@exa77mple.com',
            'password' => 'password123',
        ];

        $response = $this->app->test('POST', '/api/users', $postData);
        $this->assertEquals(201, $response['status']);
        $this->assertArrayHasKey('data', $response['body']);
        $this->assertEquals('Test User', $response['body']['data']['name']);
        return $response['body']['data']['id'];
    }


    /**
     * @depends testStore
     */
    public function testUpdate($userId)
    {
        $putData = [
            'name' => 'Updated User',
            'email' => 'updated@example.com',
        ];

        $response = $this->app->test('PUT', "/api/users/{$userId}", $putData);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('msg', $response['body']);
        $this->assertStringContainsString("User with ID: $userId updated successfully", $response['body']['msg']);

    }

    /**
     * @depends testStore
     */
    public function testDelete($userId)
    {
        $response = $this->app->test('DELETE', "/api/users/{$userId}");

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('msg', $response['body']);
        $this->assertStringContainsString("User with ID: $userId deleted successfully", $response['body']['msg']);

        // ตรวจสอบว่าผู้ใช้ถูกลบจริง
        $response = $this->app->test('GET', "/api/users/{$userId}");
        $this->assertEquals(404, $response['status']);
    }

    public function testStoreValidationError()
    {
        $invalidData = [
            'name' => '', // ไม่กรอกชื่อ
            'email' => 'invalid-email',
            'password' => '123'
        ];

        $response = $this->app->test('POST', '/api/users', $invalidData);
        $this->assertEquals(422, $response['status']);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('name', $response['errors']);
    }


}
