<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\Web\Operator\LineController as OperatorLineController;
use App\Http\Controllers\Web\Operator\WorkOrderController as OperatorWorkOrderController;
use App\Http\Controllers\Web\Operator\BatchController as OperatorBatchController;
use App\Http\Controllers\Web\Operator\IssueController as OperatorIssueController;
use App\Http\Controllers\Web\Supervisor\DashboardController as SupervisorDashboardController;
use App\Http\Controllers\Web\Admin\CsvImportController as AdminCsvImportController;
use App\Http\Controllers\Web\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\WorkOrderManagementController as AdminWorkOrderController;
use App\Http\Controllers\Web\Admin\IssueTypeManagementController as AdminIssueTypeController;
use App\Http\Controllers\Web\Admin\ModulesController as AdminModulesController;
use App\Http\Controllers\Web\IssueManagementController;
// Gate 2 — Company structure
use App\Http\Controllers\Web\Admin\FactoryController;
use App\Http\Controllers\Web\Admin\DivisionController;
use App\Http\Controllers\Web\Admin\WorkstationTypeController;
use App\Http\Controllers\Web\Admin\SubassemblyController;
// Gate 3 — Basics
use App\Http\Controllers\Web\Admin\CompanyController;
use App\Http\Controllers\Web\Admin\AnomalyReasonController;
// Gate 4 — HR
use App\Http\Controllers\Web\Admin\WageGroupController;
use App\Http\Controllers\Web\Admin\CrewController;
use App\Http\Controllers\Web\Admin\SkillController;
use App\Http\Controllers\Web\Admin\WorkerController;
// Gate 5 — Tracking advanced
use App\Http\Controllers\Web\Admin\ProductionAnomalyController;
// Gate 6 — Costing
use App\Http\Controllers\Web\Admin\CostSourceController;
// Gate 7 — Maintenance
use App\Http\Controllers\Web\Admin\ToolController;
use App\Http\Controllers\Web\Admin\MaintenanceEventController;

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
    if (!file_exists(storage_path('installed'))) {
        return redirect()->route('install.index');
    }
    return redirect()->route('login');
});

// Guest routes (unauthenticated)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
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
    });

    // Legacy change password route (redirect to settings)
    Route::get('/change-password', function () {
        return redirect()->route('settings.change-password');
    })->name('change-password');

    // Operator routes (Operator, Supervisor, Admin)
    Route::prefix('operator')->name('operator.')->middleware('role:Operator|Supervisor|Admin')->group(function () {
        Route::get('/select-line', [OperatorLineController::class, 'index'])->name('select-line');
        Route::post('/select-line', [OperatorLineController::class, 'select'])->name('select-line.post');
        Route::get('/queue', [OperatorWorkOrderController::class, 'queue'])->name('queue');
        Route::get('/work-order/{workOrder}', [OperatorWorkOrderController::class, 'show'])->name('work-order.detail');
        Route::post('/batch', [OperatorBatchController::class, 'store'])->name('batch.store');
        Route::post('/issue', [OperatorIssueController::class, 'store'])->name('issue.store');
    });

    // Supervisor routes (Supervisor and Admin)
    Route::prefix('supervisor')->name('supervisor.')->middleware('role:Supervisor|Admin')->group(function () {
        Route::get('/dashboard', [SupervisorDashboardController::class, 'index'])->name('dashboard');

        // Issues management
        Route::get('/issues', [IssueManagementController::class, 'index'])->name('issues.index');
        Route::post('/issues/{issue}/acknowledge', [IssueManagementController::class, 'acknowledge'])->name('issues.acknowledge');
        Route::post('/issues/{issue}/resolve', [IssueManagementController::class, 'resolve'])->name('issues.resolve');
        Route::post('/issues/{issue}/close', [IssueManagementController::class, 'close'])->name('issues.close');
    });

    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware('role:Admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Work Orders
        Route::resource('work-orders', AdminWorkOrderController::class);
        Route::post('/work-orders/{workOrder}/cancel', [AdminWorkOrderController::class, 'cancel'])->name('work-orders.cancel');

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
            Route::post('/{process_template}/steps/{step}/move-up', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'moveStepUp'])->name('move-step-up');
            Route::post('/{process_template}/steps/{step}/move-down', [\App\Http\Controllers\Web\Admin\ProcessTemplateManagementController::class, 'moveStepDown'])->name('move-step-down');
        });

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
