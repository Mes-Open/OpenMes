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
use App\Http\Controllers\Api\V1\ProcessTemplateController;
use App\Http\Controllers\Api\V1\ProductTypeController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WorkstationController;
use App\Http\Controllers\Api\V1\WorkstationTypeController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Controllers\Api\V1\WageGroupController;
use App\Http\Controllers\Api\V1\CrewController;
use App\Http\Controllers\Api\V1\WorkerController;
use App\Http\Controllers\Api\V1\FactoryController;
use App\Http\Controllers\Api\V1\DivisionController;
use App\Http\Controllers\Api\V1\LineStatusController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CostSourceController;
use App\Http\Controllers\Api\V1\AnomalyReasonController;
use App\Http\Controllers\Api\V1\SubassemblyController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\ToolController;
use App\Http\Controllers\Api\V1\MaintenanceEventController;
use App\Http\Controllers\Api\V1\ProductionAnomalyController;
use App\Http\Controllers\Api\V1\AdditionalCostController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\ConnectivityController;
use App\Http\Controllers\Api\V1\SystemController;
use Spatie\Permission\Models\Role;

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
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Protected API routes (require authentication)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Lines (read for any authenticated user; admin-only mutations below)
    Route::get('/lines', [LineController::class, 'index']);
    Route::get('/lines/{line}', [LineController::class, 'show']);
    Route::get('/lines/{line}/users', [LineController::class, 'users']);
    Route::get('/lines/{line}/product-types', [LineController::class, 'productTypes']);
    Route::get('/lines/{line}/workstations', [WorkstationController::class, 'index']);
    Route::get('/workstations/{workstation}', [WorkstationController::class, 'show']);

    // Product Types (read for any authenticated user)
    Route::get('/product-types', [ProductTypeController::class, 'index']);
    Route::get('/product-types/{product_type}', [ProductTypeController::class, 'show']);
    Route::get('/product-types/{product_type}/process-templates', [ProcessTemplateController::class, 'index']);

    // Process Templates (read for any authenticated user)
    Route::get('/process-templates/{process_template}', [ProcessTemplateController::class, 'show']);

    // Workstation Types (read for any authenticated user)
    Route::get('/workstation-types', [WorkstationTypeController::class, 'index']);
    Route::get('/workstation-types/{workstation_type}', [WorkstationTypeController::class, 'show']);

    // HR — read for any authenticated user
    Route::get('/skills', [SkillController::class, 'index']);
    Route::get('/skills/{skill}', [SkillController::class, 'show']);
    Route::get('/wage-groups', [WageGroupController::class, 'index']);
    Route::get('/wage-groups/{wage_group}', [WageGroupController::class, 'show']);
    Route::get('/crews', [CrewController::class, 'index']);
    Route::get('/crews/{crew}', [CrewController::class, 'show']);
    Route::get('/crews/{crew}/workers', [CrewController::class, 'workers']);
    Route::get('/workers', [WorkerController::class, 'index']);
    Route::get('/workers/{worker}', [WorkerController::class, 'show']);

    // Org structure — read for any authenticated user
    Route::get('/factories', [FactoryController::class, 'index']);
    Route::get('/factories/{factory}', [FactoryController::class, 'show']);
    Route::get('/factories/{factory}/divisions', [DivisionController::class, 'index']);
    Route::get('/divisions/{division}', [DivisionController::class, 'show']);
    Route::get('/lines/{line}/statuses', [LineStatusController::class, 'index']);

    // Ops support — read for any auth user
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
    Route::get('/cost-sources', [CostSourceController::class, 'index']);
    Route::get('/cost-sources/{cost_source}', [CostSourceController::class, 'show']);
    Route::get('/anomaly-reasons', [AnomalyReasonController::class, 'index']);
    Route::get('/anomaly-reasons/{anomaly_reason}', [AnomalyReasonController::class, 'show']);
    Route::get('/subassemblies', [SubassemblyController::class, 'index']);
    Route::get('/subassemblies/{subassembly}', [SubassemblyController::class, 'show']);
    Route::get('/shifts', [ShiftController::class, 'index']);
    Route::get('/shifts/{shift}', [ShiftController::class, 'show']);

    // Tools — read for any authenticated user
    Route::get('/tools', [ToolController::class, 'index']);
    Route::get('/tools/{tool}', [ToolController::class, 'show']);

    // Maintenance Events — read for any authenticated user; transitions for
    // assigned operators or supervisors+
    Route::get('/maintenance-events', [MaintenanceEventController::class, 'index']);
    Route::get('/maintenance-events/{maintenance_event}', [MaintenanceEventController::class, 'show']);
    Route::post('/maintenance-events/{maintenance_event}/start', [MaintenanceEventController::class, 'start']);
    Route::post('/maintenance-events/{maintenance_event}/complete', [MaintenanceEventController::class, 'complete']);
    Route::post('/maintenance-events/{maintenance_event}/cancel', [MaintenanceEventController::class, 'cancel']);

    // Production Anomalies (operators can create + edit own draft; admins/supers manage)
    Route::get('/production-anomalies', [ProductionAnomalyController::class, 'index']);
    Route::get('/production-anomalies/{productionAnomaly}', [ProductionAnomalyController::class, 'show']);
    Route::post('/work-orders/{workOrder}/production-anomalies', [ProductionAnomalyController::class, 'store']);
    Route::patch('/production-anomalies/{productionAnomaly}', [ProductionAnomalyController::class, 'update']);
    Route::delete('/production-anomalies/{productionAnomaly}', [ProductionAnomalyController::class, 'destroy']);
    Route::post('/production-anomalies/{productionAnomaly}/process', [ProductionAnomalyController::class, 'process']);

    // Additional Costs (admin/supervisor only — policy enforced)
    Route::get('/work-orders/{workOrder}/additional-costs', [AdditionalCostController::class, 'index']);
    Route::post('/work-orders/{workOrder}/additional-costs', [AdditionalCostController::class, 'store']);
    Route::patch('/additional-costs/{additionalCost}', [AdditionalCostController::class, 'update']);
    Route::delete('/additional-costs/{additionalCost}', [AdditionalCostController::class, 'destroy']);

    // Attachments (polymorphic — entity_type + entity_id)
    Route::get('/attachments', [AttachmentController::class, 'index']);
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show']);
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download']);
    Route::post('/attachments', [AttachmentController::class, 'store']);
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);

    // Connectivity (Admin-only — policy enforced)
    Route::get('/connectivity/connections', [ConnectivityController::class, 'listConnections']);
    Route::get('/connectivity/connections/{machineConnection}', [ConnectivityController::class, 'showConnection']);
    Route::get('/connectivity/connections/{machineConnection}/mqtt', [ConnectivityController::class, 'showMqttSettings']);
    Route::delete('/connectivity/connections/{machineConnection}', [ConnectivityController::class, 'deleteConnection']);
    Route::post('/connectivity/connections/{machineConnection}/toggle-active', [ConnectivityController::class, 'toggleConnectionActive']);

    Route::get('/connectivity/topics', [ConnectivityController::class, 'listTopics']);
    Route::get('/connectivity/topics/{machineTopic}', [ConnectivityController::class, 'showTopic']);
    Route::delete('/connectivity/topics/{machineTopic}', [ConnectivityController::class, 'deleteTopic']);
    Route::post('/connectivity/topics/{machineTopic}/toggle-active', [ConnectivityController::class, 'toggleTopicActive']);

    Route::get('/connectivity/mappings', [ConnectivityController::class, 'listMappings']);
    Route::get('/connectivity/mappings/{topicMapping}', [ConnectivityController::class, 'showMapping']);
    Route::delete('/connectivity/mappings/{topicMapping}', [ConnectivityController::class, 'deleteMapping']);

    Route::get('/connectivity/messages', [ConnectivityController::class, 'listMessages']);
    Route::get('/connectivity/messages/{machineMessage}', [ConnectivityController::class, 'showMessage']);

    // System — Settings / Modules / Schedule / Alerts / Update Check
    // (role checks performed in controller for finer granularity)
    Route::get('/system/settings', [SystemController::class, 'listSettings']);
    Route::get('/system/settings/{key}', [SystemController::class, 'showSetting']);
    Route::put('/system/settings/{key}', [SystemController::class, 'updateSetting']);

    Route::get('/system/modules', [SystemController::class, 'listModules']);
    Route::post('/system/modules/{name}/enable', [SystemController::class, 'enableModule']);
    Route::post('/system/modules/{name}/disable', [SystemController::class, 'disableModule']);

    Route::get('/system/schedule', [SystemController::class, 'schedule']);
    Route::get('/system/alerts', [SystemController::class, 'alerts']);
    Route::get('/system/alerts/counts', [SystemController::class, 'alertsCounts']);
    Route::get('/system/update-check', [SystemController::class, 'updateCheck']);

    // Lines + Workstations admin mutations
    Route::middleware('role:Admin')->group(function () {
        Route::post('/lines', [LineController::class, 'store']);
        Route::patch('/lines/{line}', [LineController::class, 'update']);
        Route::delete('/lines/{line}', [LineController::class, 'destroy']);
        Route::post('/lines/{line}/toggle-active', [LineController::class, 'toggleActive']);
        Route::post('/lines/{line}/users', [LineController::class, 'syncUsers']);
        Route::delete('/lines/{line}/users/{user}', [LineController::class, 'unassignUser']);
        Route::post('/lines/{line}/product-types', [LineController::class, 'syncProductTypes']);

        Route::post('/lines/{line}/workstations', [WorkstationController::class, 'store']);
        Route::patch('/workstations/{workstation}', [WorkstationController::class, 'update']);
        Route::delete('/workstations/{workstation}', [WorkstationController::class, 'destroy']);
        Route::post('/workstations/{workstation}/toggle-active', [WorkstationController::class, 'toggleActive']);

        // Product Types — admin mutations
        Route::post('/product-types', [ProductTypeController::class, 'store']);
        Route::patch('/product-types/{product_type}', [ProductTypeController::class, 'update']);
        Route::delete('/product-types/{product_type}', [ProductTypeController::class, 'destroy']);
        Route::post('/product-types/{product_type}/toggle-active', [ProductTypeController::class, 'toggleActive']);

        // Process Templates — admin mutations
        Route::post('/product-types/{product_type}/process-templates', [ProcessTemplateController::class, 'store']);
        Route::patch('/process-templates/{process_template}', [ProcessTemplateController::class, 'update']);
        Route::delete('/process-templates/{process_template}', [ProcessTemplateController::class, 'destroy']);
        Route::post('/process-templates/{process_template}/toggle-active', [ProcessTemplateController::class, 'toggleActive']);

        // Template Steps
        Route::post('/process-templates/{process_template}/steps', [ProcessTemplateController::class, 'addStep']);
        Route::post('/process-templates/{process_template}/steps/reorder', [ProcessTemplateController::class, 'reorderSteps']);
        Route::patch('/template-steps/{template_step}', [ProcessTemplateController::class, 'updateStep']);
        Route::delete('/template-steps/{template_step}', [ProcessTemplateController::class, 'destroyStep']);

        // Workstation Types
        Route::post('/workstation-types', [WorkstationTypeController::class, 'store']);
        Route::patch('/workstation-types/{workstation_type}', [WorkstationTypeController::class, 'update']);
        Route::delete('/workstation-types/{workstation_type}', [WorkstationTypeController::class, 'destroy']);
        Route::post('/workstation-types/{workstation_type}/toggle-active', [WorkstationTypeController::class, 'toggleActive']);

        // Skills
        Route::post('/skills', [SkillController::class, 'store']);
        Route::patch('/skills/{skill}', [SkillController::class, 'update']);
        Route::delete('/skills/{skill}', [SkillController::class, 'destroy']);

        // Wage Groups
        Route::post('/wage-groups', [WageGroupController::class, 'store']);
        Route::patch('/wage-groups/{wage_group}', [WageGroupController::class, 'update']);
        Route::delete('/wage-groups/{wage_group}', [WageGroupController::class, 'destroy']);
        Route::post('/wage-groups/{wage_group}/toggle-active', [WageGroupController::class, 'toggleActive']);

        // Crews
        Route::post('/crews', [CrewController::class, 'store']);
        Route::patch('/crews/{crew}', [CrewController::class, 'update']);
        Route::delete('/crews/{crew}', [CrewController::class, 'destroy']);
        Route::post('/crews/{crew}/toggle-active', [CrewController::class, 'toggleActive']);

        // Workers
        Route::post('/workers', [WorkerController::class, 'store']);
        Route::patch('/workers/{worker}', [WorkerController::class, 'update']);
        Route::delete('/workers/{worker}', [WorkerController::class, 'destroy']);
        Route::post('/workers/{worker}/skills', [WorkerController::class, 'syncSkills']);

        // Factories
        Route::post('/factories', [FactoryController::class, 'store']);
        Route::patch('/factories/{factory}', [FactoryController::class, 'update']);
        Route::delete('/factories/{factory}', [FactoryController::class, 'destroy']);
        Route::post('/factories/{factory}/toggle-active', [FactoryController::class, 'toggleActive']);

        // Divisions
        Route::post('/factories/{factory}/divisions', [DivisionController::class, 'store']);
        Route::patch('/divisions/{division}', [DivisionController::class, 'update']);
        Route::delete('/divisions/{division}', [DivisionController::class, 'destroy']);
        Route::post('/divisions/{division}/toggle-active', [DivisionController::class, 'toggleActive']);

        // Line Statuses
        Route::post('/lines/{line}/statuses', [LineStatusController::class, 'store']);
        Route::post('/lines/{line}/statuses/reorder', [LineStatusController::class, 'reorder']);
        Route::patch('/line-statuses/{line_status}', [LineStatusController::class, 'update']);
        Route::delete('/line-statuses/{line_status}', [LineStatusController::class, 'destroy']);

        // Companies
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::patch('/companies/{company}', [CompanyController::class, 'update']);
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);
        Route::post('/companies/{company}/toggle-active', [CompanyController::class, 'toggleActive']);

        // Cost sources
        Route::post('/cost-sources', [CostSourceController::class, 'store']);
        Route::patch('/cost-sources/{cost_source}', [CostSourceController::class, 'update']);
        Route::delete('/cost-sources/{cost_source}', [CostSourceController::class, 'destroy']);
        Route::post('/cost-sources/{cost_source}/toggle-active', [CostSourceController::class, 'toggleActive']);

        // Anomaly reasons
        Route::post('/anomaly-reasons', [AnomalyReasonController::class, 'store']);
        Route::patch('/anomaly-reasons/{anomaly_reason}', [AnomalyReasonController::class, 'update']);
        Route::delete('/anomaly-reasons/{anomaly_reason}', [AnomalyReasonController::class, 'destroy']);

        // Subassemblies
        Route::post('/subassemblies', [SubassemblyController::class, 'store']);
        Route::patch('/subassemblies/{subassembly}', [SubassemblyController::class, 'update']);
        Route::delete('/subassemblies/{subassembly}', [SubassemblyController::class, 'destroy']);

        // Shifts
        Route::post('/shifts', [ShiftController::class, 'store']);
        Route::patch('/shifts/{shift}', [ShiftController::class, 'update']);
        Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy']);

        // Tools — admin-only writes; supervisors handled via policy in controller for status/update
        Route::post('/tools', [ToolController::class, 'store']);
        Route::delete('/tools/{tool}', [ToolController::class, 'destroy']);
    });

    // Tool updates allowed for Admin OR Supervisor (policy enforced in controller)
    Route::patch('/tools/{tool}', [ToolController::class, 'update']);
    Route::post('/tools/{tool}/status', [ToolController::class, 'transitionStatus']);

    // Maintenance Events — Admin/Supervisor for create/update/delete
    Route::middleware('role:Admin|Supervisor')->group(function () {
        Route::post('/maintenance-events', [MaintenanceEventController::class, 'store']);
        Route::patch('/maintenance-events/{maintenance_event}', [MaintenanceEventController::class, 'update']);
        Route::delete('/maintenance-events/{maintenance_event}', [MaintenanceEventController::class, 'destroy']);
    });

    // Work Orders
    Route::apiResource('work-orders', WorkOrderController::class);

    // Work Order status transitions
    Route::post('/work-orders/{workOrder}/accept', [WorkOrderController::class, 'accept']);
    Route::post('/work-orders/{workOrder}/reject', [WorkOrderController::class, 'reject']);
    Route::post('/work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel']);
    Route::post('/work-orders/{workOrder}/pause', [WorkOrderController::class, 'pause']);
    Route::post('/work-orders/{workOrder}/resume', [WorkOrderController::class, 'resume']);
    Route::post('/work-orders/{workOrder}/reopen', [WorkOrderController::class, 'reopen']);
    Route::post('/work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete']);

    // Batches (nested under work orders)
    Route::get('/work-orders/{workOrder}/batches', [BatchController::class, 'index']);
    Route::post('/work-orders/{workOrder}/batches', [BatchController::class, 'store']);
    Route::get('/batches/{batch}', [BatchController::class, 'show']);
    Route::patch('/batches/{batch}', [BatchController::class, 'update']);
    Route::post('/batches/{batch}/cancel', [BatchController::class, 'cancel']);
    Route::delete('/batches/{batch}', [BatchController::class, 'destroy']);

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
    Route::delete('/issues/{issue}', [IssueController::class, 'destroy']); // Admin only (enforced in controller)
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
    Route::middleware('role:Admin')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/entity', [AuditLogController::class, 'entity']);
        Route::get('/audit-logs/export', [AuditLogController::class, 'export']);

        // Event Logs
        Route::get('/event-logs', [EventLogController::class, 'index']);
        Route::get('/event-logs/entity', [EventLogController::class, 'entity']);

        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('throttle:5,1');
        Route::get('/users/{user}/lines', [UserController::class, 'lines']);
        Route::post('/users/{user}/lines', [UserController::class, 'syncLines']);

        // Roles (read-only, populates role pickers)
        Route::get('/roles', function () {
            return response()->json([
                'data' => Role::orderBy('name')->get(['id', 'name']),
            ]);
        });
    });

    // Analytics (Supervisor/Admin)
    Route::middleware('role:Supervisor|Admin')->group(function () {
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
        Route::get('/analytics/production-by-line', [AnalyticsController::class, 'productionByLine']);
        Route::get('/analytics/cycle-time', [AnalyticsController::class, 'cycleTime']);
        Route::get('/analytics/throughput', [AnalyticsController::class, 'throughput']);
        Route::get('/analytics/issue-stats', [AnalyticsController::class, 'issueStats']);
        Route::get('/analytics/step-performance', [AnalyticsController::class, 'stepPerformance']);

        // Reports
        Route::get('/reports/production-summary', [ReportController::class, 'productionSummary']);
        Route::get('/reports/batch-completion', [ReportController::class, 'batchCompletion']);
        Route::get('/reports/downtime', [ReportController::class, 'downtimeReport']);
        Route::get('/reports/export-csv', [ReportController::class, 'exportCsv']);
    });
});
