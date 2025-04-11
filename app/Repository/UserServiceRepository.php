<?php
namespace App\Repository;

use PDO;
use App\Core\Database;

class UserServiceRepository
{
    protected $pdo;

    public function __construct()
    {

        $this->pdo = Database::getInstance();
    }

    // ดึงข้อมูลผู้ใช้ทั้งหมด
    public function getAllUsers()
    {
        $stmt = $this->pdo->query("SELECT * FROM users");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ดึงข้อมูลผู้ใช้จาก ID
    public function getUserById(int $id)
    {

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
