<?php

use PHPUnit\Framework\TestCase;

class UserApiTest extends TestCase
{
    protected $app;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('APP_ENV=testing');

        // ตั้งค่า App
        $this->app = app();


        // เปิดโหมดทดสอบสำหรับ Response
        $response  =  $this->app->getResponse();
        $response->asTest();
        // สร้างฐานข้อมูลในหน่วยความจำ (SQLite)
        $dbManager = app('db');

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
        $pdo = app('db')->getConnection('sqlite');
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
            'email' => 'test001@example.com',
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
        try {
            $this->app->test('POST', '/api/users', $invalidData);
        } catch (\Exception $e) {
            $errors = json_decode($e->getMessage(), true);
            $this->assertEquals(422, $errors['status']);
            $this->assertArrayHasKey('errors', $errors['body']);
            $this->assertArrayHasKey('name', $errors['body']['errors']);

        }

    }
}
