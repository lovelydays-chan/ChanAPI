<?php

use App\Controllers\UserController;


$app->addRoute('GET', '/', function () {
    echo 'Welcome to the API';
});

// Group สำหรับ /api/users
$app->addRoute('GET', '/api/users', UserController::class, 'index');
$app->addRoute('GET', '/api/users/{id}', UserController::class, 'show');
$app->addRoute('POST', '/api/users', UserController::class, 'store');
$app->addRoute('PUT', '/api/users/{id}', UserController::class, 'update');
$app->addRoute('DELETE', '/api/users/{id}', UserController::class, 'delete');

$app->addRoute('GET', '/api/test/{id}', UserController::class, 'test');
// Route แบบ callback function
$app->addRoute('GET', '/api/status', function () {
    return response()->json(['status' => 'API is running']);
});