<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;

Route::post('/login', [AuthController::class, 'login']);

// Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Roles Routes
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles/create', [RoleController::class, 'store']);
    Route::post('/roles/show', [RoleController::class, 'show']);
    Route::post('/roles/update', [RoleController::class, 'update']);
    Route::post('/roles/delete', [RoleController::class, 'destroy']);

    // Users Routes
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users/create', [UserController::class, 'store']);
    Route::post('/users/show', [UserController::class, 'show']);
    Route::post('/users/update', [UserController::class, 'update']);
    Route::post('/users/delete', [UserController::class, 'destroy']);
// });
