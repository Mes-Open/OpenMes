<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use App\Http\Controllers\Api\V1\BatchController;
use App\Http\Controllers\Api\V1\BatchStepController;
use App\Http\Controllers\Api\V1\LineController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\IssueTypeController;
use App\Http\Controllers\Api\V1\CsvImportController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\EventLogController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ReportController;

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

    // Issues (Andon System)
    Route::get('/issues', [IssueController::class, 'index']);
    Route::get('/issues/{issue}', [IssueController::class, 'show']);
    Route::post('/issues', [IssueController::class, 'store']);
    Route::patch('/issues/{issue}', [IssueController::class, 'update']);
    Route::post('/issues/{issue}/acknowledge', [IssueController::class, 'acknowledge']);
    Route::post('/issues/{issue}/resolve', [IssueController::class, 'resolve']);
    Route::post('/issues/{issue}/close', [IssueController::class, 'close']);
    Route::get('/issues/stats/line', [IssueController::class, 'lineStats']);

    // Issue Types
    Route::get('/issue-types', [IssueTypeController::class, 'index']);
    Route::get('/issue-types/{issueType}', [IssueTypeController::class, 'show']);
    Route::post('/issue-types', [IssueTypeController::class, 'store']); // Admin only
    Route::patch('/issue-types/{issueType}', [IssueTypeController::class, 'update']); // Admin only
    Route::delete('/issue-types/{issueType}', [IssueTypeController::class, 'destroy']); // Admin only

    // CSV Import
    Route::post('/csv-imports/upload', [CsvImportController::class, 'upload']); // Admin only
    Route::post('/csv-imports/execute', [CsvImportController::class, 'execute']); // Admin only
    Route::get('/csv-imports', [CsvImportController::class, 'index']);
    Route::get('/csv-imports/{csvImport}', [CsvImportController::class, 'status']);
    Route::get('/csv-import-mappings', [CsvImportController::class, 'mappings']);
    Route::post('/csv-import-mappings', [CsvImportController::class, 'saveMapping']);

    // Audit Logs (Admin only)
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/entity', [AuditLogController::class, 'entity']);
    Route::get('/audit-logs/export', [AuditLogController::class, 'export']);

    // Event Logs
    Route::get('/event-logs', [EventLogController::class, 'index']);
    Route::get('/event-logs/entity', [EventLogController::class, 'entity']);

    // Analytics (Supervisor Dashboard)
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/production-by-line', [AnalyticsController::class, 'productionByLine']);
    Route::get('/analytics/cycle-time', [AnalyticsController::class, 'cycleTime']);
    Route::get('/analytics/throughput', [AnalyticsController::class, 'throughput']);
    Route::get('/analytics/issue-stats', [AnalyticsController::class, 'issueStats']);
    Route::get('/analytics/step-performance', [AnalyticsController::class, 'stepPerformance']);

    // Reports (Supervisor/Admin)
    Route::get('/reports/production-summary', [ReportController::class, 'productionSummary']);
    Route::get('/reports/batch-completion', [ReportController::class, 'batchCompletion']);
    Route::get('/reports/downtime', [ReportController::class, 'downtimeReport']);
    Route::get('/reports/export-csv', [ReportController::class, 'exportCsv']);
});
