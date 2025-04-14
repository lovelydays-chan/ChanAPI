<?php

namespace App\Core;

use PDO;
use PDOException;
use InvalidArgumentException;

class DatabaseManager
{
    protected array $connections = [];

    public function __construct()
    {
        $this->loadConnections();
    }

    protected function loadConnections(): void
    {
        $config = config('database');
        $connections = $config['connections'] ?? [];

        foreach ($connections as $name => $settings) {
            $this->connections[$name] = $this->createConnection($settings, $name);
        }
        // fallback สำหรับ testing กรณีไม่มี sqlite
        if (!isset($this->connections['sqlite']) && getenv('APP_ENV') === 'testing') {
            $this->connections['sqlite'] = $this->createConnection([
                'driver' => 'sqlite',
                'database' => ':memory:',
            ], 'sqlite');
        }
    }

    protected function createConnection(array $config, string $name): PDO
    {
        $driver = $config['driver'] ?? 'mysql';
        // สำหรับ testing ใช้ sqlite in-memory เสมอ
        if (getenv('APP_ENV') === 'testing') {
            $driver = 'sqlite';
            $config = [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ];
        }

        $dsn = $this->buildDsn($driver, $config);
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // ตั้งค่าให้คง connection ไว้
                PDO::ATTR_PERSISTENT => true
            ]);

            // สำหรับ SQLite in-memory ตั้งค่าให้ใช้ connection เดียว
            if ($driver === 'sqlite' && $config['database'] === ':memory:') {
                $pdo->exec('PRAGMA foreign_keys = ON;');
            }

            return $pdo;
        } catch (PDOException $e) {
            throw new InvalidArgumentException("Failed to connect to [$name] database: " . $e->getMessage());
        }
    }


    protected function buildDsn(string $driver, array $config): string
    {
        return match ($driver) {
            'mysql'  => sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                $config['dbname'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            ),
            'sqlite' => 'sqlite:' . ($config['database'] ?? ':memory:'),
            default  => throw new InvalidArgumentException("Unsupported driver: $driver"),
        };
    }

    protected function isMysqlConfigValid(array $config): bool
    {
        return !empty($config['host']) &&
            !empty($config['dbname']) &&
            !empty($config['username']) &&
            array_key_exists('password', $config);
    }

    public function getConnection(?string $name = null): ?PDO
    {
        $name = $name ?? config('database.default', 'mysql');
        return $this->connections[$name] ?? null;
    }
}
