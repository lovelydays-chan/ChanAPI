<?php

namespace App\Core;

use App\Core\Validator;

abstract class BaseRequest
{
    protected $data = [];
    protected $files = [];
    protected $rules = [];
    protected $messages = [];

    public function __construct($data = [],$files=[])
    {
        // รวมข้อมูลจาก GET, POST, และ JSON
        $this->data = !empty($data) ? $data : array_merge(
            $_GET,
            $_POST,
            $this->parseJsonInput()
        );

        // เก็บข้อมูลไฟล์จาก $_FILES
        $this->files = !empty($files) ? $files : $_FILES;

        // เรียก validate() อัตโนมัติเมื่อสร้าง instance
        $this->validate();
    }

    /**
     * ดึงข้อมูลทั้งหมดจากคำขอ
     */
    public function all()
    {
        return $this->data;
    }

    /**
     * ดึงค่าจากคำขอโดยใช้คีย์
     */
    public function input($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * ดึงข้อมูลไฟล์จากคำขอ
     */
    public function file($key)
    {
        return $this->files[$key] ?? null;
    }

    /**
     * รองรับการเข้าถึงข้อมูลผ่าน property
     */
    public function __get($key)
    {
        return $this->input($key);
    }

    /**
     * ตรวจสอบและ validate ข้อมูล
     */
    protected function validate()
    {
        if (!empty($this->rules)) {
            Validator::validate($this->data, $this->rules, $this->messages);
        }
    }

    /**
     * แปลงข้อมูล JSON จาก request body
     */
    protected function parseJsonInput()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }

        return [];
    }
}