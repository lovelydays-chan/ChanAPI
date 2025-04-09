<?php

namespace App\Core;

use PDO;
use Exception;
use PDOException;

class Database
{
    private static $instances = [];
    private static $lastConnection = null; // เก็บชื่อการเชื่อมต่อล่าสุด
    private $pdo;

    private function __construct($config)
    {
        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            // ใช้ SQLite
            $dsn = "sqlite:{$config['database']}";
        } else {
            // ใช้ MySQL หรือฐานข้อมูลอื่น
            $host = $config['host'];
            $db = $config['dbname'];
            $user = $config['username'];
            $pass = $config['password'];
            $charset = $config['charset'];

            $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user ?? null, $pass ?? null, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance($connection = null)
    {
        // โหลดค่าคอนฟิกจาก config/database.php
        $config = require __DIR__ . '/../../config/database.php';

        // หากไม่ได้ระบุ connection ให้ใช้การเชื่อมต่อล่าสุด
        if ($connection === null) {
            $connection = self::$lastConnection ?? $config['default'];
        }

        // ตรวจสอบว่ามีการเชื่อมต่ออยู่แล้วหรือไม่
        if (!isset(self::$instances[$connection])) {
            if (!isset($config['connections'][$connection])) {
                throw new Exception("Database connection [{$connection}] not configured.");
            }

            // สร้าง Database instance และเก็บไว้ใน instances
            self::$instances[$connection] = new self($config['connections'][$connection]);
        }

        // บันทึกการเชื่อมต่อล่าสุด
        self::$lastConnection = $connection;

        // คืนค่า PDO instance
        return self::$instances[$connection]->getConnection();
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    public function table($table)
    {
        return new QueryBuilder($this->pdo, $table);
    }
}