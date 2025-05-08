<?php

use App\Controllers\UserController;
use App\Core\RouteService as Route;

// Route แบบ callback function
Route::get('/', function () {
    return 'Welcome to the API';
});

// Route สำหรับ UserController
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'delete']);

// Route แบบ callback function
Route::get('/status', function () {
    return response()->json(['status' => 'API is running'])->send();
});

