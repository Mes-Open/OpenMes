<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\Web\Admin\AnomalyReasonController;
use App\Http\Controllers\Web\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Web\Admin\BomManagementController;
use App\Http\Controllers\Web\Admin\CompanyController;
use App\Http\Controllers\Web\Admin\Connectivity\ConnectivityController;
use App\Http\Controllers\Web\Admin\Connectivity\MachineTopicController;
use App\Http\Controllers\Web\Admin\Connectivity\MqttConnectionController;
use App\Http\Controllers\Web\Admin\Connectivity\TopicMappingController;
use App\Http\Controllers\Web\Admin\CostSourceController;
use App\Http\Controllers\Web\Admin\CrewController;
use App\Http\Controllers\Web\Admin\CsvImportController as AdminCsvImportController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\DivisionController;
use App\Http\Controllers\Web\Admin\FactoryController;
use App\Http\Controllers\Web\Admin\IntegrationConfigController;
use App\Http\Controllers\Web\Admin\IssueTypeManagementController as AdminIssueTypeController;
use App\Http\Controllers\Web\Admin\LineStatusController as AdminLineStatusController;
use App\Http\Controllers\Web\Admin\LotSequenceController as AdminLotSequenceController;
// Gate 2 — Company structure
use App\Http\Controllers\Web\Admin\MaintenanceEventController;
use App\Http\Controllers\Web\Admin\MaterialManagementController;
use App\Http\Controllers\Web\Admin\ModulesController as AdminModulesController;
use App\Http\Controllers\Web\Admin\ProductionAnomalyController;
// Gate 3 — Basics
use App\Http\Controllers\Web\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Web\Admin\SkillController;
// Gate 4 — HR
use App\Http\Controllers\Web\Admin\SubassemblyController;
use App\Http\Controllers\Web\Admin\ToolController;
use App\Http\Controllers\Web\Admin\WageGroupController;
use App\Http\Controllers\Web\Admin\WorkerController;
// Gate 5 — Tracking advanced
use App\Http\Controllers\Web\Admin\WorkOrderManagementController as AdminWorkOrderController;
// Gate 6 — Costing
use App\Http\Controllers\Web\Admin\WorkstationTypeController;
// Materials & BOM
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\IssueManagementController;
use App\Http\Controllers\Web\Operator\BatchController as OperatorBatchController;
// Gate 7 — Maintenance
use App\Http\Controllers\Web\Operator\IssueController as OperatorIssueController;
use App\Http\Controllers\Web\Operator\LineController as OperatorLineController;
use App\Http\Controllers\Web\Operator\WorkOrderController as OperatorWorkOrderController;
use App\Http\Controllers\Web\Operator\WorkstationController as OperatorWorkstationController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\Web\Supervisor\DashboardController as SupervisorDashboardController;
use Illuminate\Support\Facades\Route;

// Installation routes (no middleware)
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');
    Route::get('/environment', [InstallController::class, 'showEnvironmentForm'])->name('environment');
    Route::post('/environment', [InstallController::class, 'setupEnvironment'])->name('environment.setup');
    Route::get('/database', [InstallController::class, 'showDatabaseForm'])->name('database');
    Route::post('/database', [InstallController::class, 'setupDatabase'])->name('database.setup');
    Route::get('/admin', [InstallController::class, 'showAdminForm'])->name('admin');
    Route::post('/admin', [InstallController::class, 'createAdmin'])->name('admin.create');
    Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
});

// Redirect root to installer or login
Route::get('/', function () {
    if (! file_exists(storage_path('installed'))) {
        return redirect()->route('install.index');
    }

    return redirect()->route('login');
});

Route::get('/offline', function () {
    return view('offline');
})->name('offline');

// Guest routes (unauthenticated)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/login/pin', [AuthController::class, 'loginWithPin'])->name('login.pin')->middleware('throttle:10,1');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\SettingsController::class, 'index'])->name('index');
        Route::get('/change-password', [\App\Http\Controllers\Web\SettingsController::class, 'showChangePasswordForm'])->name('change-password');
        Route::post('/change-password', [\App\Http\Controllers\Web\SettingsController::class, 'updatePassword'])->name('update-password');
        Route::get('/profile', [\App\Http\Controllers\Web\SettingsController::class, 'showProfileForm'])->name('profile');
        Route::post('/profile', [\App\Http\Controllers\Web\SettingsController::class, 'updateProfile'])->name('update-profile');
        // Admin-only system settings
        Route::get('/system', [\App\Http\Controllers\Web\SettingsController::class, 'showSystemSettings'])->name('system')->middleware('role:Admin');
        Route::post('/system', [\App\Http\Controllers\Web\SettingsController::class, 'updateSystemSettings'])->name('update-system')->middleware('role:Admin');
        // Admin-only sample data
        Route::post('/sample-data', [\App\Http\Controllers\Web\SettingsController::class, 'loadSampleData'])->name('sample-data')->middleware('role:Admin');
        // PIN management
        Route::get('/pin', [\App\Http\Controllers\Web\SettingsController::class, 'showPinForm'])->name('pin');
        Route::post('/pin', [\App\Http\Controllers\Web\SettingsController::class, 'updatePin'])->name('update-pin');
        Route::delete('/pin', [\App\Http\Controllers\Web\SettingsController::class, 'removePin'])->name('remove-pin');
        // Admin-only API token management
        Route::get('/api-tokens', [\App\Http\Controllers\Web\SettingsController::class, 'showApiTokens'])->name('api-tokens')->middleware('role:Admin');
        Route::post('/api-tokens', [\App\Http\Controllers\Web\SettingsController::class, 'createApiToken'])->name('api-tokens.create')->middleware('role:Admin');
        Route::delete('/api-tokens/{token}', [\App\Http\Controllers\Web\SettingsController::class, 'revokeApiToken'])->name('api-tokens.revoke')->middleware('role:Admin');
    });

    // Legacy change password route (redirect to settings)
    Route::get('/change-password', function () {
        return redirect()->route('settings.change-password');
    })->name('change-password');

    // Onboarding Wizard (Admin only)
    Route::prefix('onboarding')->name('onboarding.')->middleware('role:Admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\OnboardingController::class, 'index'])->name('index');
        Route::get('/step/1', [\App\Http\Controllers\Web\OnboardingController::class, 'step1'])->name('step1');
        Route::post('/step/1', [\App\Http\Controllers\Web\OnboardingController::class, 'storeStep1']);
        Route::get('/step/2', [\App\Http\Controllers\Web\OnboardingController::class, 'step2'])->name('step2');
        Route::post('/step/2', [\App\Http\Controllers\Web\OnboardingController::class, 'storeStep2']);
        Route::get('/step/3', [\App\Http\Controllers\Web\OnboardingController::class, 'step3'])->name('step3');
        Route::post('/step/3', [\App\Http\Controllers\Web\OnboardingController::class, 'storeStep3']);
        Route::get('/step/4', [\App\Http\Controllers\Web\OnboardingController::class, 'step4'])->name('step4');
        Route::post('/step/4', [\App\Http\Controllers\Web\OnboardingController::class, 'storeStep4']);
        Route::get('/complete', [\App\Http\Controllers\Web\OnboardingController::class, 'complete'])->name('complete');
        Route::post('/skip', [\App\Http\Controllers\Web\OnboardingController::class, 'skip'])->name('skip');
    });

    // Operator routes (Operator, Supervisor, Admin)
    Route::prefix('operator')->name('operator.')->middleware('role:Operator|Supervisor|Admin')->group(function () {
        Route::get('/select-line', [OperatorLineController::class, 'index'])->name('select-line');
        Route::post('/select-line', [OperatorLineController::class, 'select'])->name('select-line.post');
        Route::get('/queue', [OperatorWorkOrderController::class, 'queue'])->name('queue');
        Route::post('/work-order/{workOrder}/line-status', [OperatorWorkOrderController::class, 'updateLineStatus'])->name('work-order.line-status');
        Route::get('/work-order/{workOrder}', [OperatorWorkOrderController::class, 'show'])->name('work-order.detail');
        Route::post('/batch', [OperatorBatchController::class, 'store'])->name('batch.store');
        Route::post('/batch/{batch}/confirm', [OperatorBatchController::class, 'confirmParameters'])->name('batch.confirm');
        Route::post('/batch/{batch}/quality-check', [OperatorBatchController::class, 'qualityCheck'])->name('batch.quality-check');
        Route::post('/batch/{batch}/packaging-checklist', [OperatorBatchController::class, 'packagingChecklist'])->name('batch.packaging-checklist');
        Route::post('/batch/{batch}/release', [OperatorBatchController::class, 'release'])->name('batch.release');
        Route::post('/issue', [OperatorIssueController::class, 'store'])->name('issue.store');

        // Workstation production view
        Route::get('/workstation', [OperatorWorkstationController::class, 'index'])->name('workstation');
        Route::post('/workstation/{workOrder}/start', [OperatorWorkstationController::class, 'start'])->name('workstation.start');
        Route::post('/workstation/{workOrder}/complete', [OperatorWorkstationController::class, 'complete'])->name('workstation.complete');
        Route::post('/workstation/{workOrder}/shift-entry', [OperatorWorkstationController::class, 'shiftEntry'])->name('workstation.shift-entry');
    });

    // Supervisor routes (Supervisor and Admin)
    Route::prefix('supervisor')->name('supervisor.')->middleware('role:Supervisor|Admin')->group(function () {
        Route::get('/dashboard', [SupervisorDashboardController::class, 'index'])->name('dashboard');

        // Work Orders (supervisor can manage status)
        Route::get('/work-orders', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'index'])->name('work-orders.index');
        Route::get('/work-orders/{workOrder}', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'show'])->name('work-orders.show');
        Route::post('/work-orders/{workOrder}/accept', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'accept'])->name('work-orders.accept');
        Route::post('/work-orders/{workOrder}/reject', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'reject'])->name('work-orders.reject');
        Route::post('/work-orders/{workOrder}/pause', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'pause'])->name('work-orders.pause');
        Route::post('/work-orders/{workOrder}/resume', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'resume'])->name('work-orders.resume');
        Route::post('/work-orders/{workOrder}/complete', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'complete'])->name('work-orders.complete');
        Route::post('/work-orders/{workOrder}/cancel', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'cancel'])->name('work-orders.cancel');
        Route::post('/work-orders/{workOrder}/reopen', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'reopen'])->name('work-orders.reopen');
        Route::get('/work-orders/{workOrder}/edit', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'edit'])->name('work-orders.edit');
        Route::put('/work-orders/{workOrder}', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'update'])->name('work-orders.update');

        // Issues management
        Route::get('/issues', [IssueManagementController::class, 'index'])->name('issues.index');
        Route::post('/issues/{issue}/acknowledge', [IssueManagementController::class, 'acknowledge'])->name('issues.acknowledge');
        Route::post('/issues/{issue}/resolve', [IssueManagementController::class, 'resolve'])->name('issues.resolve');
        Route::post('/issues/{issue}/close', [IssueManagementController::class, 'close'])->name('issues.close');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports');
    });

    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware('role:Admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Reports
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports');

        // Alerts
        Route::get('/alerts', [\App\Http\Controllers\Web\Admin\AlertController::class, 'index'])->name('alerts');

        // Update
        Route::get('/update/check', [\App\Http\Controllers\Web\Admin\UpdateController::class, 'check'])->name('update.check');
        Route::post('/update/apply', [\App\Http\Controllers\Web\Admin\UpdateController::class, 'apply'])->name('update.apply');

        // Schedule
        Route::get('/schedule', [\App\Http\Controllers\Web\Admin\ScheduleController::class, 'index'])->name('schedule');

        // Shifts
        Route::get('/shifts', [\App\Http\Controllers\Web\Admin\ShiftController::class, 'index'])->name('shifts.index');
        Route::get('/shifts/create', [\App\Http\Controllers\Web\Admin\ShiftController::class, 'create'])->name('shifts.create');
        Route::post('/shifts', [\App\Http\Controllers\Web\Admin\ShiftController::class, 'store'])->name('shifts.store');
        Route::get('/shifts/{shift}/edit', [\App\Http\Controllers\Web\Admin\ShiftController::class, 'edit'])->name('shifts.edit');
        Route::put('/shifts/{shift}', [\App\Http\Controllers\Web\Admin\ShiftController::class, 'update'])->name('shifts.update');
        Route::delete('/shifts/{shift}', [\App\Http\Controllers\Web\Admin\ShiftController::class, 'destroy'])->name('shifts.destroy');

        // Work Orders
        Route::resource('work-orders', AdminWorkOrderController::class);
        Route::post('/work-orders/{workOrder}/cancel', [AdminWorkOrderController::class, 'cancel'])->name('work-orders.cancel');
        Route::post('/work-orders/{workOrder}/accept', [AdminWorkOrderController::class, 'accept'])->name('work-orders.accept');
        Route::post('/work-orders/{workOrder}/reject', [AdminWorkOrderController::class, 'reject'])->name('work-orders.reject');
        Route::post('/work-orders/{workOrder}/pause', [AdminWorkOrderController::class, 'pause'])->name('work-orders.pause');
        Route::post('/work-orders/{workOrder}/resume', [AdminWorkOrderController::class, 'resume'])->name('work-orders.resume');
        Route::post('/work-orders/{workOrder}/reopen', [AdminWorkOrderController::class, 'reopen'])->name('work-orders.reopen');
        Route::post('/work-orders/{workOrder}/complete', [AdminWorkOrderController::class, 'complete'])->name('work-orders.complete');

        // Issue Types
        Route::resource('issue-types', AdminIssueTypeController::class);
        Route::post('/issue-types/{issueType}/toggle-active', [AdminIssueTypeController::class, 'toggleActive'])->name('issue-types.toggle-active');

        // Issues Management
        Route::get('/issues', [IssueManagementController::class, 'index'])->name('issues.index');
        Route::post('/issues/{issue}/acknowledge', [IssueManagementController::class, 'acknowledge'])->name('issues.acknowledge');
        Route::post('/issues/{issue}/resolve', [IssueManagementController::class, 'resolve'])->name('issues.resolve');
        Route::post('/issues/{issue}/close', [IssueManagementController::class, 'close'])->name('issues.close');

        // User Management
        Route::resource('users', \App\Http\Controllers\Web\Admin\UserManagementController::class);

        // Production Lines Management
        Route::resource('lines', \App\Http\Controllers\Web\Admin\LineManagementController::class);
        Route::post('/lines/{line}/toggle-active', [\App\Http\Controllers\Web\Admin\LineManagementController::class, 'toggleActive'])->name('lines.toggle-active');
        Route::post('/lines/{line}/assign-operator', [\App\Http\Controllers\Web\Admin\LineManagementController::class, 'assignOperator'])->name('lines.assign-operator');
        Route::delete('/lines/{line}/unassign-operator/{user}', [\App\Http\Controllers\Web\Admin\LineManagementController::class, 'unassignOperator'])->name('lines.unassign-operator');
        // Per-line statuses
        Route::post('/lines/{line}/statuses', [AdminLineStatusController::class, 'storeForLine'])->name('lines.statuses.store');
        // Per-line product types
        Route::post('/lines/{line}/product-types', [\App\Http\Controllers\Web\Admin\LineManagementController::class, 'syncProductTypes'])->name('lines.product-types.sync');
        Route::post('/lines/{line}/view-columns', [\App\Http\Controllers\Web\Admin\LineManagementController::class, 'saveViewColumns'])->name('lines.view-columns.save');
        Route::post('/lines/{line}/view-template', [\App\Http\Controllers\Web\Admin\LineManagementController::class, 'assignViewTemplate'])->name('lines.view-template.assign');
        Route::post('/lines/{line}/default-view', [\App\Http\Controllers\Web\Admin\LineManagementController::class, 'setDefaultView'])->name('lines.default-view.set');

        // View Templates
        Route::resource('view-templates', \App\Http\Controllers\Web\Admin\ViewTemplateController::class)->except(['show']);

        // Global line statuses management
        Route::get('/line-statuses', [AdminLineStatusController::class, 'index'])->name('line-statuses.index');
        Route::post('/line-statuses', [AdminLineStatusController::class, 'store'])->name('line-statuses.store');
        Route::put('/line-statuses/{lineStatus}', [AdminLineStatusController::class, 'update'])->name('line-statuses.update');
        Route::delete('/line-statuses/{lineStatus}', [AdminLineStatusController::class, 'destroy'])->name('line-statuses.destroy');

        // Workstations Management (nested under lines)
        Route::prefix('lines/{line}/workstations')->name('lines.workstations.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Web\Admin\WorkstationManagementController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Web\Admin\WorkstationManagementController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Web\Admin\WorkstationManagementController::class, 'store'])->name('store');
            Route::get('/{workstation}/edit', [\App\Http\Controllers\Web\Admin\WorkstationManagementController::class, 'edit'])->name('edit');
            Route::put('/{workstation}', [\App\Http\Controllers\Web\Admin\WorkstationManagementController::class, 'update'])->name('update');
            Route::delete('/{workstation}', [\App\Http\Controllers\Web\Admin\WorkstationManagementController::class, 'destroy'])->name('destroy');
            Route::post('/{workstation}/toggle-active', [\App\Http\Controllers\Web\Admin\WorkstationManagementController::class, 'toggleActive'])->name('toggle-active');
        });

        // Product Types Management
        Route::resource('product-types', \App\Http\Controllers\Web\Admin\ProductTypeManagementController::class);
        Route::post('/product-types/{product_type}/toggle-active', [\App\Http\Controllers\Web\Admin\ProductTypeManagementController::class, 'toggleActive'])->name('product-types.toggle-active');

        // Process Templates Management (nested under product types)
        Route::prefix('product-types/{product_type}/process-templates')->name('product-types.process-templates.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'store'])->name('store');
            Route::get('/{process_template}', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'show'])->name('show');
            Route::get('/{process_template}/edit', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'edit'])->name('edit');
            Route::put('/{process_template}', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'update'])->name('update');
            Route::delete('/{process_template}', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'destroy'])->name('destroy');
            Route::post('/{process_template}/toggle-active', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'toggleActive'])->name('toggle-active');

            // Template steps management
            Route::post('/{process_template}/steps', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'addStep'])->name('add-step');
            Route::put('/{process_template}/steps/{step}', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'updateStep'])->name('update-step');
            Route::delete('/{process_template}/steps/{step}', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'deleteStep'])->name('delete-step');
            Route::post('/{process_template}/steps/reorder', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'reorderSteps'])->name('reorder-steps');
            Route::post('/{process_template}/steps/{step}/move-up', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'moveStepUp'])->name('move-step-up');
            Route::post('/{process_template}/steps/{step}/move-down', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'moveStepDown'])->name('move-step-down');

            // BOM Management (nested under process templates)
            Route::get('/{process_template}/bom', [BomManagementController::class, 'index'])->name('bom');
            Route::post('/{process_template}/bom', [BomManagementController::class, 'store'])->name('bom.store');
            Route::put('/{process_template}/bom/{bom_item}', [BomManagementController::class, 'update'])->name('bom.update');
            Route::delete('/{process_template}/bom/{bom_item}', [BomManagementController::class, 'destroy'])->name('bom.destroy');
        });

        // LOT Sequences
        Route::resource('lot-sequences', AdminLotSequenceController::class)->except(['show']);

        // Batch Reports
        Route::get('/batches/{batch}/report', [\App\Http\Controllers\Web\Admin\BatchReportController::class, 'show'])->name('batch-report');
        Route::get('/batches/{batch}/report/pdf', [\App\Http\Controllers\Web\Admin\BatchReportController::class, 'pdf'])->name('batch-report.pdf');

        // Materials Management
        Route::resource('materials', MaterialManagementController::class);
        Route::post('/materials/{material}/toggle-active', [MaterialManagementController::class, 'toggleActive'])->name('materials.toggle-active');

        // Integration Configs
        Route::resource('integrations', IntegrationConfigController::class)->except(['show']);

        // CSV Import
        Route::get('/csv-import', [AdminCsvImportController::class, 'index'])->name('csv-import');
        Route::post('/csv-import/upload', [AdminCsvImportController::class, 'upload'])->name('csv-import.upload');
        Route::post('/csv-import/process', [AdminCsvImportController::class, 'process'])->name('csv-import.process');
        Route::delete('/csv-import/mappings/{mapping}', [AdminCsvImportController::class, 'destroyMapping'])->name('csv-import.mappings.destroy');

        // Audit Logs
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs');
        Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->name('audit-logs.export');

        // Modules
        Route::get('/modules', [AdminModulesController::class, 'index'])->name('modules.index');
        Route::get('/modules/install', [AdminModulesController::class, 'install'])->name('modules.install');
        Route::get('/modules/store', [AdminModulesController::class, 'store'])->name('modules.store');
        Route::post('/modules/upload', [AdminModulesController::class, 'upload'])->name('modules.upload');
        Route::post('/modules/{name}/enable', [AdminModulesController::class, 'enable'])->name('modules.enable');
        Route::post('/modules/{name}/disable', [AdminModulesController::class, 'disable'])->name('modules.disable');
        Route::delete('/modules/{name}', [AdminModulesController::class, 'destroy'])->name('modules.destroy');

        // ── Gate 2: Company Structure ────────────────────────────────────────
        // Factories
        Route::resource('factories', FactoryController::class)->except(['show']);
        Route::post('/factories/{factory}/toggle-active', [FactoryController::class, 'toggleActive'])->name('factories.toggle-active');

        // Divisions
        Route::resource('divisions', DivisionController::class)->except(['show']);
        Route::post('/divisions/{division}/toggle-active', [DivisionController::class, 'toggleActive'])->name('divisions.toggle-active');

        // Workstation Types
        Route::resource('workstation-types', WorkstationTypeController::class)->except(['show']);
        Route::post('/workstation-types/{workstationType}/toggle-active', [WorkstationTypeController::class, 'toggleActive'])->name('workstation-types.toggle-active');

        // Subassemblies
        Route::resource('subassemblies', SubassemblyController::class)->except(['show']);
        Route::post('/subassemblies/{subassembly}/toggle-active', [SubassemblyController::class, 'toggleActive'])->name('subassemblies.toggle-active');

        // ── Gate 3: Basics / Dictionaries ────────────────────────────────────
        // Companies (contractors)
        Route::resource('companies', CompanyController::class)->except(['show']);
        Route::post('/companies/{company}/toggle-active', [CompanyController::class, 'toggleActive'])->name('companies.toggle-active');

        // Anomaly Reasons
        Route::resource('anomaly-reasons', AnomalyReasonController::class)->except(['show']);
        Route::post('/anomaly-reasons/{anomalyReason}/toggle-active', [AnomalyReasonController::class, 'toggleActive'])->name('anomaly-reasons.toggle-active');

        // ── Gate 4: HR ───────────────────────────────────────────────────────
        // Wage Groups
        Route::resource('wage-groups', WageGroupController::class)->except(['show']);
        Route::post('/wage-groups/{wageGroup}/toggle-active', [WageGroupController::class, 'toggleActive'])->name('wage-groups.toggle-active');

        // Crews
        Route::resource('crews', CrewController::class)->except(['show']);
        Route::post('/crews/{crew}/toggle-active', [CrewController::class, 'toggleActive'])->name('crews.toggle-active');

        // Skills
        Route::resource('skills', SkillController::class)->except(['show']);

        // Workers
        Route::resource('workers', WorkerController::class)->except(['show']);
        Route::post('/workers/{worker}/toggle-active', [WorkerController::class, 'toggleActive'])->name('workers.toggle-active');

        // ── Gate 5: Tracking Advanced ─────────────────────────────────────────
        // Production Anomalies
        Route::get('/production-anomalies', [ProductionAnomalyController::class, 'index'])->name('production-anomalies.index');
        Route::get('/production-anomalies/create', [ProductionAnomalyController::class, 'create'])->name('production-anomalies.create');
        Route::post('/production-anomalies', [ProductionAnomalyController::class, 'store'])->name('production-anomalies.store');
        Route::post('/production-anomalies/{productionAnomaly}/process', [ProductionAnomalyController::class, 'process'])->name('production-anomalies.process');
        Route::delete('/production-anomalies/{productionAnomaly}', [ProductionAnomalyController::class, 'destroy'])->name('production-anomalies.destroy');

        // ── Gate 6: Costing ───────────────────────────────────────────────────
        // Cost Sources
        Route::resource('cost-sources', CostSourceController::class)->except(['show']);
        Route::post('/cost-sources/{costSource}/toggle-active', [CostSourceController::class, 'toggleActive'])->name('cost-sources.toggle-active');

        // ── Connectivity ──────────────────────────────────────────────────────
        Route::get('/connectivity', [ConnectivityController::class, 'index'])->name('connectivity.index');

        // MQTT connections
        Route::get('/connectivity/mqtt', [MqttConnectionController::class, 'index'])->name('connectivity.mqtt.index');
        Route::get('/connectivity/mqtt/create', [MqttConnectionController::class, 'create'])->name('connectivity.mqtt.create');
        Route::post('/connectivity/mqtt', [MqttConnectionController::class, 'store'])->name('connectivity.mqtt.store');
        Route::get('/connectivity/mqtt/{mqttConnection}', [MqttConnectionController::class, 'show'])->name('connectivity.mqtt.show');
        Route::get('/connectivity/mqtt/{mqttConnection}/edit', [MqttConnectionController::class, 'edit'])->name('connectivity.mqtt.edit');
        Route::put('/connectivity/mqtt/{mqttConnection}', [MqttConnectionController::class, 'update'])->name('connectivity.mqtt.update');
        Route::delete('/connectivity/mqtt/{mqttConnection}', [MqttConnectionController::class, 'destroy'])->name('connectivity.mqtt.destroy');
        Route::post('/connectivity/mqtt/{mqttConnection}/toggle-active', [MqttConnectionController::class, 'toggleActive'])->name('connectivity.mqtt.toggle-active');
        Route::get('/connectivity/mqtt/{mqttConnection}/messages', [MqttConnectionController::class, 'messages'])->name('connectivity.mqtt.messages');

        // Topics (nested under a connection)
        Route::post('/connectivity/mqtt/{mqttConnection}/topics', [MachineTopicController::class, 'store'])->name('connectivity.mqtt.topics.store');
        Route::put('/connectivity/mqtt/{mqttConnection}/topics/{topic}', [MachineTopicController::class, 'update'])->name('connectivity.mqtt.topics.update');
        Route::delete('/connectivity/mqtt/{mqttConnection}/topics/{topic}', [MachineTopicController::class, 'destroy'])->name('connectivity.mqtt.topics.destroy');

        // Mappings (nested under topic)
        Route::post('/connectivity/mqtt/{mqttConnection}/topics/{topic}/mappings', [TopicMappingController::class, 'store'])->name('connectivity.mqtt.topics.mappings.store');
        Route::put('/connectivity/mqtt/{mqttConnection}/topics/{topic}/mappings/{mapping}', [TopicMappingController::class, 'update'])->name('connectivity.mqtt.topics.mappings.update');
        Route::delete('/connectivity/mqtt/{mqttConnection}/topics/{topic}/mappings/{mapping}', [TopicMappingController::class, 'destroy'])->name('connectivity.mqtt.topics.mappings.destroy');

        // ── Gate 7: Maintenance ───────────────────────────────────────────────
        // Tools
        Route::resource('tools', ToolController::class)->except(['show']);

        // Maintenance Events
        Route::resource('maintenance-events', MaintenanceEventController::class)->except(['destroy']);
        Route::post('/maintenance-events/{maintenanceEvent}/start', [MaintenanceEventController::class, 'start'])->name('maintenance-events.start');
        Route::post('/maintenance-events/{maintenanceEvent}/complete', [MaintenanceEventController::class, 'complete'])->name('maintenance-events.complete');
        Route::post('/maintenance-events/{maintenanceEvent}/cancel', [MaintenanceEventController::class, 'cancel'])->name('maintenance-events.cancel');
    });
});
