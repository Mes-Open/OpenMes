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
    });

    // Legacy change password route (redirect to settings)
    Route::get('/change-password', function () {
        return redirect()->route('settings.change-password');
    })->name('change-password');

    // Operator routes
    Route::prefix('operator')->name('operator.')->middleware('role:Operator')->group(function () {
        Route::get('/select-line', [OperatorLineController::class, 'index'])->name('select-line');
        Route::post('/select-line', [OperatorLineController::class, 'select'])->name('select-line.post');
        Route::get('/queue', [OperatorWorkOrderController::class, 'queue'])->name('queue');
        Route::get('/work-order/{workOrder}', [OperatorWorkOrderController::class, 'show'])->name('work-order.detail');
        Route::post('/batch', [OperatorBatchController::class, 'store'])->name('batch.store');
        Route::post('/issue', [OperatorIssueController::class, 'store'])->name('issue.store');
    });

    // Supervisor routes
    Route::prefix('supervisor')->name('supervisor.')->middleware('role:Supervisor')->group(function () {
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
    });
});
