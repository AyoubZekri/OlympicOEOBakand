<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\IndividualController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\ContractController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
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

    // Individuals Routes
    Route::get('/individuals', [IndividualController::class, 'index']);
    Route::post('/individuals/create', [IndividualController::class, 'store']);
    Route::post('/individuals/show', [IndividualController::class, 'show']);
    Route::post('/individuals/update', [IndividualController::class, 'update']);
    Route::post('/individuals/delete', [IndividualController::class, 'destroy']);

    // Teams Routes
    Route::get('/teams', [TeamController::class, 'index']);
    Route::post('/teams/create', [TeamController::class, 'store']);
    Route::post('/teams/show', [TeamController::class, 'show']);
    Route::post('/teams/update', [TeamController::class, 'update']);
    Route::post('/teams/delete', [TeamController::class, 'destroy']);

    // Contracts Routes
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::post('/contracts/create', [ContractController::class, 'store']);
    Route::post('/contracts/show', [ContractController::class, 'show']);
    Route::post('/contracts/update', [ContractController::class, 'update']);
    Route::post('/contracts/delete', [ContractController::class, 'destroy']);

    // Funds Routes
    Route::get('/funds', [\App\Http\Controllers\Api\FundController::class, 'index']);
    Route::post('/funds/create', [\App\Http\Controllers\Api\FundController::class, 'store']);
    Route::post('/funds/update', [\App\Http\Controllers\Api\FundController::class, 'update']);
    Route::post('/funds/delete', [\App\Http\Controllers\Api\FundController::class, 'destroy']);

    // Fund Transactions Routes
    Route::get('/transactions', [\App\Http\Controllers\Api\FundTransactionController::class, 'index']);
    Route::post('/transactions/create', [\App\Http\Controllers\Api\FundTransactionController::class, 'store']);
    Route::post('/transactions/delete', [\App\Http\Controllers\Api\FundTransactionController::class, 'destroy']);

    // Payments Routes
    Route::get('/payments', [\App\Http\Controllers\Api\PaymentController::class, 'index']);
    Route::post('/payments/create', [\App\Http\Controllers\Api\PaymentController::class, 'store']);
    Route::post('/payments/update', [\App\Http\Controllers\Api\PaymentController::class, 'update']);
    Route::post('/payments/delete', [\App\Http\Controllers\Api\PaymentController::class, 'destroy']);
    Route::post('/payments/return', [\App\Http\Controllers\Api\PaymentController::class, 'returnPayment']);
 });
