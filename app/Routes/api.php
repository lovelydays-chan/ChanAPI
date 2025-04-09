<?php

use App\Controllers\UserController;

if (!isset($app)) {
    die('Error: $app not found.');
}

$app->addRoute('GET', '/', function () {
    echo 'Welcome to the API';
});

// Group สำหรับ /api/users
$app->addRoute('GET', '/api/users', UserController::class, 'index');
$app->addRoute('GET', '/api/users/{id}', UserController::class, 'show');
$app->addRoute('POST', '/api/users', UserController::class, 'store');
$app->addRoute('PUT', '/api/users/{id}', UserController::class, 'update');
$app->addRoute('DELETE', '/api/users/{id}', UserController::class, 'delete');

// Route แบบ callback function
$app->addRoute('GET', '/api/status', function () {
    return response()->json(['status' => 'API is running']);
});