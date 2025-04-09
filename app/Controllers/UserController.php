<?php

namespace App\Controllers;

use App\Models\User;
use App\Requests\UserStoreRequest;
use App\Requests\UserUpdateRequest;

class UserController
{
    protected $model;

    public function __construct()
    {
        // เชื่อมต่อกับ UserModel
        $this->model = new User();
    }

    public function index()
    {
        $perPage = $_GET['per_page'] ?? 10;
        $currentPage = $_GET['page'] ?? 1;

        $result = $this->model->paginate($perPage, $currentPage);

        $pagination = [
            'total' => $result['pagination']['total'],
            'current_page' => $result['pagination']['current_page'],
            'per_page' => $result['pagination']['per_page'],
            'last_page' => $result['pagination']['last_page'],
        ];

        return response()->paginate($result['data'], $pagination, 200);
    }

    public function show($id)
    {
        $user = $this->model->find($id);

        if ($user) {
            return response()->json(['msg' => 'User found', 'status' => true, 'data' => $user], 200);
        } else {
            return response()->json(['msg' => 'User not found', 'status' => false], 404);
        }
    }

    public function store(UserStoreRequest $request)
    {
        $data = $request->all();

        // เข้ารหัสรหัสผ่านก่อนบันทึก
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $user = $this->model->create($data); // สร้างผู้ใช้และรับข้อมูลผู้ใช้ที่เพิ่งสร้าง

        if ($user) {
            return response()->json([
                'msg' => 'User created successfully',
                'data' => $user, // ส่งข้อมูลผู้ใช้กลับไป
            ], 201);
        } else {
            return response()->json(['msg' => 'Failed to create user'], 500);
        }
    }

    public function update($id, UserUpdateRequest $request)
    {
        $data = $request->all();

        if ($this->model->update($id, $data)) {
            return response()->json(['msg' => "User with ID: $id updated successfully"]);
        } else {
            return response()->json(['msg' => "Failed to update user with ID: $id"], 500);
        }
    }

    public function delete($id)
    {
        if ($this->model->delete($id)) {
            return response()->json(['msg' => "User with ID: $id deleted successfully"]);
        } else {
            return response()->json(['msg' => "Failed to delete user with ID: $id"], 500);
        }
    }
}