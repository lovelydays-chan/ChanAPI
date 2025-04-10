<?php

namespace App\Core;

use App\Core\Validator;

abstract class BaseRequest
{
    protected $data = [];
    protected $files = [];
    protected $rules = [];
    protected $messages = [];
    protected $isTestMode = false;

    public function __construct($data = [], $files = [])
    {
        // ใช้ตรวจสอบการทดสอบจากการมีข้อมูลใน $data และ $files เท่านั้น
        $this->isTestMode = !empty($data) || !empty($files);
        // \var_dump($this->isTestMode);

        // ใช้ข้อมูลที่ส่งเข้ามาใน $data หรือ $files
        $this->data = $this->isTestMode
            ? $data
            : array_merge($_GET, $_POST, $this->parseJsonInput());

        $this->files = $this->isTestMode ? $files : $_FILES;

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
    protected function validate(): void
    {
        if (!empty($this->rules)) {
            // ตรวจสอบว่า $this->data เป็น array
            $dataToValidate = is_array($this->data) ? $this->data : [];
            Validator::validate($dataToValidate, $this->rules, $this->messages);
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

    public function isTestMode(): bool
    {
        return $this->isTestMode;
    }
}
