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

    // Improvement Programs Routes
    Route::get('/improvement-programs', [\App\Http\Controllers\Api\ImprovementProgramController::class, 'index']);
    Route::post('/improvement-programs/create', [\App\Http\Controllers\Api\ImprovementProgramController::class, 'create']);
    Route::post('/improvement-programs/update', [\App\Http\Controllers\Api\ImprovementProgramController::class, 'update']);
    Route::post('/improvement-programs/delete', [\App\Http\Controllers\Api\ImprovementProgramController::class, 'delete']);

    // Training Sessions Routes
    Route::get('/training-sessions', [App\Http\Controllers\Api\TrainingSessionController::class, 'index']);
    Route::post('/training-sessions/create', [App\Http\Controllers\Api\TrainingSessionController::class, 'store']);
    Route::post('/training-sessions/update', [App\Http\Controllers\Api\TrainingSessionController::class, 'update']);
    Route::post('/training-sessions/delete', [App\Http\Controllers\Api\TrainingSessionController::class, 'destroy']);
    Route::post('/training-sessions/update-status', [App\Http\Controllers\Api\TrainingSessionController::class, 'updateStatus']);

    // Training Attendance Routes
    Route::get('/training-sessions/{sessionId}/attendance', [App\Http\Controllers\Api\TrainingAttendanceController::class, 'getSessionAttendance']);
    Route::post('/training-attendance/save', [App\Http\Controllers\Api\TrainingAttendanceController::class, 'saveAttendance']);

    // Absences Routes (using app_absences table)
    Route::get('/absences', [App\Http\Controllers\Api\AbsenceController::class, 'index']);
    Route::post('/absences/create', [App\Http\Controllers\Api\AbsenceController::class, 'store']);
    Route::post('/absences/update-justification', [App\Http\Controllers\Api\AbsenceController::class, 'updateJustification']);
    Route::post('/absences/delete', [App\Http\Controllers\Api\AbsenceController::class, 'destroy']);

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
    Route::post('/individuals/print', [IndividualController::class, 'printInternalSystem']);

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

    // Equipments Routes
    Route::get('/equipments', [\App\Http\Controllers\Api\EquipmentController::class, 'index']);
    Route::post('/equipments/create', [\App\Http\Controllers\Api\EquipmentController::class, 'store']);
    Route::post('/equipments/update', [\App\Http\Controllers\Api\EquipmentController::class, 'update']);
    Route::post('/equipments/delete', [\App\Http\Controllers\Api\EquipmentController::class, 'destroy']);

    // Equipment Operations Routes
    Route::get('/equipment-operations', [\App\Http\Controllers\Api\EquipmentOperationController::class, 'index']);
    Route::post('/equipment-operations/create', [\App\Http\Controllers\Api\EquipmentOperationController::class, 'store']);
    Route::post('/equipment-operations/return', [\App\Http\Controllers\Api\EquipmentOperationController::class, 'returnEquipment']);
    Route::post('/equipment-operations/undo-return', [\App\Http\Controllers\Api\EquipmentOperationController::class, 'undoReturnEquipment']);
    Route::post('/equipment-operations/update', [\App\Http\Controllers\Api\EquipmentOperationController::class, 'update']);
    Route::post('/equipment-operations/delete', [\App\Http\Controllers\Api\EquipmentOperationController::class, 'destroy']);

    // Disciplinary Routes
    Route::get('/disciplinary', [\App\Http\Controllers\Api\DisciplinaryController::class, 'index']);
    Route::post('/disciplinary/create', [\App\Http\Controllers\Api\DisciplinaryController::class, 'store']);
    Route::post('/disciplinary/update', [\App\Http\Controllers\Api\DisciplinaryController::class, 'update']);
    Route::post('/disciplinary/delete', [\App\Http\Controllers\Api\DisciplinaryController::class, 'destroy']);

    // Correspondences Routes
    Route::get('/correspondences', [\App\Http\Controllers\Api\CorrespondenceController::class, 'index']);
    Route::post('/correspondences/create', [\App\Http\Controllers\Api\CorrespondenceController::class, 'store']);
    Route::post('/correspondences/update', [\App\Http\Controllers\Api\CorrespondenceController::class, 'update']);
    Route::post('/correspondences/delete', [\App\Http\Controllers\Api\CorrespondenceController::class, 'destroy']);

    // Player Evaluations Routes
    Route::get('/player-evaluations', [\App\Http\Controllers\Api\PlayerEvaluationController::class, 'index']);
    Route::post('/player-evaluations/create', [\App\Http\Controllers\Api\PlayerEvaluationController::class, 'store']);
    Route::post('/player-evaluations/update', [\App\Http\Controllers\Api\PlayerEvaluationController::class, 'update']);
    Route::post('/player-evaluations/delete', [\App\Http\Controllers\Api\PlayerEvaluationController::class, 'destroy']);

    // Contract Reviews Routes
    Route::get('/contract-reviews', [\App\Http\Controllers\Api\ContractReviewController::class, 'index']);
    Route::post('/contract-reviews/create', [\App\Http\Controllers\Api\ContractReviewController::class, 'create']);
    Route::post('/contract-reviews/update', [\App\Http\Controllers\Api\ContractReviewController::class, 'update']);
    Route::post('/contract-reviews/delete', [\App\Http\Controllers\Api\ContractReviewController::class, 'delete']);
});

