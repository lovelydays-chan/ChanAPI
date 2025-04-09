<?php

use App\Core\Response;

// Helper สำหรับ Response

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