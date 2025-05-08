<?php

use App\Core\Response;

// Helper สำหรับ Response

if (!function_exists('app')) {
    function app(?string $abstract = null)
    {
        static $app;

        // ตรวจสอบว่ามี instance ของ App อยู่แล้วหรือไม่
        if (!$app) {
            // ใช้ require_once เพื่อป้องกันการโหลดซ้ำ
            $app = require_once __DIR__ . '/../../bootstrap/app.php';
        }

        // หากไม่มีการระบุ $abstract ให้คืนค่า instance ของ App
        if ($abstract === null) {
            return $app;
        }

        // ดึง service จาก container
        return $app->make($abstract);
    }
}

function config($key, $default = null)
{
    static $configs = [];

    if (empty($configs)) {
        $configPath = __DIR__ . '/../../config';
        foreach (glob("$configPath/*.php") as $file) {
            $name = basename($file, '.php');
            $configs[$name] = require $file;
        }
    }

    return array_reduce(explode('.', $key), function ($carry, $segment) use ($default) {
        return $carry[$segment] ?? $default;
    }, $configs);
}



if (!function_exists('response')) {
    function response()
    {
        // ใช้ Response ที่มาจาก App หรือเป็น instance ที่ถูกส่งมาแล้ว
        static $response;

        if (!$response) {
            // ถ้ายังไม่มี Response ที่ตั้งค่าไว้ จะสร้างใหม่
            $response = new Response();
        }

        // คืนค่าผลลัพธ์จาก Response ที่ตั้งค่าแล้ว
        return $response;
    }
}

// Helper สำหรับ Array
if (!function_exists('array_flatten')) {
    function array_flatten($array)
    {
        return array_merge(...array_map('array_values', $array));
    }
}

// Helper สำหรับ String
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return strpos($haystack, $needle) === 0;
    }
}