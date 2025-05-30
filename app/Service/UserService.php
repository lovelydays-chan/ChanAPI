<?php

namespace App\Service;

use App\Repository\UserServiceRepository;

class UserService
{
    protected $userRepository;

    public function __construct(UserServiceRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    // ดึงข้อมูลผู้ใช้ทั้งหมด
    public function getAllUsers()
    {
        return $this->userRepository->getAllUsers();
    }

    // ดึงข้อมูลผู้ใช้จาก ID
    public function getUserById(int $id)
    {
        return $this->userRepository->getUserById($id);
    }
}
