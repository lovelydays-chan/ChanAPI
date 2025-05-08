<?php

namespace App\Controllers;

use App\Service\UserService;
use App\Repository\UserRepository;
use App\Requests\UserStoreRequest;
use App\Requests\UserUpdateRequest;

class UserController
{
    protected $model;
    protected $userService;
    protected $userRepository;


    public function __construct(UserRepository $userRepository, UserService $userService)
    {
        // เชื่อมต่อกับ UserModel
        $this->userRepository = $userRepository;
        $this->userService = $userService;
    }


    public function index()
    {
        $perPage = $_GET['per_page'] ?? 10;
        $currentPage = $_GET['page'] ?? 1;

        $result = $this->userRepository->paginate($perPage, $currentPage);
        $pagination = [
            'total' => $result['pagination']['total'],
            'current_page' => $result['pagination']['current_page'],
            'per_page' => $result['pagination']['per_page'],
            'last_page' => $result['pagination']['last_page'],
        ];

        return response()->paginate($result['data'], $pagination, 200)->send();
    }

    public function show($id)
    {
        $user = $this->userRepository->find($id);
        if ($user) {
            return response()->json(['msg' => 'User found', 'status' => true, 'data' => $user->toArray()], 200)->send();
        } else {
            return response()->json(['msg' => 'User not found', 'status' => false], 404)->send();
        }
    }

    public function store(UserStoreRequest $request)
    {
        $data = $request->all();
        // เข้ารหัสรหัสผ่านก่อนบันทึก
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $user = $this->userRepository->create($data); // สร้างผู้ใช้และรับข้อมูลผู้ใช้ที่เพิ่งสร้าง

        if ($user) {
            return response()->json([
                'msg' => 'User created successfully',
                'data' => $user->toArray(), // ส่งข้อมูลผู้ใช้กลับไป
            ], 201)->send();
        } else {
            return response()->json(['msg' => 'Failed to create user'], 500)->send();
        }
    }

    public function update($id, UserUpdateRequest $request)
    {
        $data = $request->all();

        if ($this->userRepository->update($id, $data)) {
            return response()->json(['msg' => "User with ID: $id updated successfully"],200)->send();
        } else {
            return response()->json(['msg' => "Failed to update user with ID: $id"], 500)->send();
        }
    }

    public function delete($id)
    {
        if ($this->userRepository->delete($id)) {
            return response()->json(['msg' => "User with ID: $id deleted successfully"],200)->send();
        } else {
            return response()->json(['msg' => "Failed to delete user with ID: $id"], 500)->send();
        }
    }

    public function test($id)
    {

        // ใช้งาน UserService ในการดึงข้อมูลผู้ใช้
        $user = $this->userService->getUserById($id);
        return response()->json($user,200)->send();
    }
}