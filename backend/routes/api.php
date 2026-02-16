<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use App\Http\Controllers\Api\V1\BatchController;
use App\Http\Controllers\Api\V1\BatchStepController;
use App\Http\Controllers\Api\V1\LineController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentication routes (no auth required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Protected API routes (require authentication)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Lines
    Route::get('/lines', [LineController::class, 'index']);
    Route::get('/lines/{line}', [LineController::class, 'show']);

    // Work Orders
    Route::apiResource('work-orders', WorkOrderController::class);

    // Batches (nested under work orders)
    Route::get('/work-orders/{workOrder}/batches', [BatchController::class, 'index']);
    Route::post('/work-orders/{workOrder}/batches', [BatchController::class, 'store']);
    Route::get('/batches/{batch}', [BatchController::class, 'show']);

    // Batch Steps (step execution)
    Route::post('/batch-steps/{batchStep}/start', [BatchStepController::class, 'start']);
    Route::post('/batch-steps/{batchStep}/complete', [BatchStepController::class, 'complete']);
    Route::post('/batch-steps/{batchStep}/problem', [BatchStepController::class, 'problem']);
});
