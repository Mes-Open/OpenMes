<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\TestControl\TenantController as E2eTenantController;
use App\Http\Controllers\Web\Admin\AnomalyReasonController;
use App\Http\Controllers\Web\Admin\AreaController;
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
use App\Http\Controllers\Web\Admin\CustomFieldDefinitionController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\DivisionController;
use App\Http\Controllers\Web\Admin\FactoryController;
use App\Http\Controllers\Web\Admin\ImportExampleController;
use App\Http\Controllers\Web\Admin\IntegrationConfigController;
use App\Http\Controllers\Web\Admin\IssueTypeManagementController as AdminIssueTypeController;
use App\Http\Controllers\Web\Admin\LineStatusController as AdminLineStatusController;
use App\Http\Controllers\Web\Admin\LotSequenceController as AdminLotSequenceController;
use App\Http\Controllers\Web\Admin\MaintenanceEventController;
use App\Http\Controllers\Web\Admin\MaterialImportController;
// Gate 2 — Company structure
use App\Http\Controllers\Web\Admin\MaterialLotController as AdminMaterialLotController;
use App\Http\Controllers\Web\Admin\MaterialManagementController;
use App\Http\Controllers\Web\Admin\ModulesController as AdminModulesController;
use App\Http\Controllers\Web\Admin\OeeController as AdminOeeController;
use App\Http\Controllers\Web\Admin\PalletController as AdminPalletController;
use App\Http\Controllers\Web\Admin\ProductionAnomalyController;
use App\Http\Controllers\Web\Admin\ProductionCostReportController;
use App\Http\Controllers\Web\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Web\Admin\ScheduleController;
use App\Http\Controllers\Web\Admin\SchedulePlannerController;
use App\Http\Controllers\Web\Admin\ScrapReasonController;
use App\Http\Controllers\Web\Admin\ScrapReportController;
// Gate 3 — Basics
use App\Http\Controllers\Web\Admin\SiteController;
use App\Http\Controllers\Web\Admin\SkillController;
// Gate 4 — HR
use App\Http\Controllers\Web\Admin\SubassemblyController;
use App\Http\Controllers\Web\Admin\ToolController;
use App\Http\Controllers\Web\Admin\WageGroupController;
use App\Http\Controllers\Web\Admin\WorkerAbsenceController;
use App\Http\Controllers\Web\Admin\WorkerController;
// Gate 5 — Tracking advanced
use App\Http\Controllers\Web\Admin\WorkOrderManagementController as AdminWorkOrderController;
// Gate 6 — Costing
use App\Http\Controllers\Web\Admin\WorkstationTypeController;
// Materials & BOM
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\IssueManagementController;
use App\Http\Controllers\Web\Operator\BatchController as OperatorBatchController;
use App\Http\Controllers\Web\Operator\IssueController as OperatorIssueController;
// Gate 7 — Maintenance
use App\Http\Controllers\Web\Operator\LineController as OperatorLineController;
use App\Http\Controllers\Web\Operator\ProductionCorrectionController;
use App\Http\Controllers\Web\Operator\ScrapController as OperatorScrapController;
use App\Http\Controllers\Web\Operator\WorkOrderController as OperatorWorkOrderController;
use App\Http\Controllers\Web\Operator\WorkstationController as OperatorWorkstationController;
use App\Http\Controllers\Web\Packaging\LabelPrintController;
use App\Http\Controllers\Web\Packaging\LabelTemplateController;
use App\Http\Controllers\Web\Packaging\PackagingController;
use App\Http\Controllers\Web\Packaging\PackagingEanController;
use App\Http\Controllers\Web\QualityControlTaskController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\Web\Supervisor\DashboardController as SupervisorDashboardController;
use Illuminate\Support\Facades\Route;

// Installation routes (blocked after installation)
Route::prefix('install')->name('install.')->middleware(\App\Http\Middleware\CheckInstallation::class)->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');
    Route::get('/environment', [InstallController::class, 'showEnvironmentForm'])->name('environment');
    Route::post('/environment', [InstallController::class, 'setupEnvironment'])->name('environment.setup');
    Route::get('/database', [InstallController::class, 'showDatabaseForm'])->name('database');
    Route::post('/database', [InstallController::class, 'setupDatabase'])->name('database.setup');
    Route::get('/modules', [InstallController::class, 'showModulesForm'])->name('modules');
    Route::post('/modules', [InstallController::class, 'selectModules'])->name('modules.select');
    Route::get('/admin', [InstallController::class, 'showAdminForm'])->name('admin');
    Route::post('/admin', [InstallController::class, 'createAdmin'])->name('admin.create');
    Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
});

// Redirect root to installer, login, or dashboard depending on auth state.
Route::get('/', function () {
    if (! file_exists(storage_path('installed'))) {
        return redirect()->route('install.index');
    }

    // Authenticated users go to their role dashboard. Redirecting them to the
    // guest-only `login` route instead would bounce off its `guest` middleware
    // (which sends authenticated users back to `/`) and loop forever
    // (ERR_TOO_MANY_REDIRECTS). Only guests get the login page.
    if (auth()->check()) {
        $user = auth()->user();

        if ($user->hasRole('Admin')) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->hasRole('Supervisor')) {
            return redirect()->route('supervisor.dashboard');
        }
        if ($user->account_type === 'workstation' && $user->workstation?->line_id) {
            return redirect()->route('operator.queue', ['line' => $user->workstation->line_id]);
        }

        // Operators land on line selection (their primary screen); granted admin
        // tabs are reached from there via the OperatorLayout "Panel" link.
        return redirect()->route('operator.select-line');
    }

    return redirect()->route('login');
});

Route::get('/offline', function () {
    return view('offline');
})->name('offline');

// Language switcher — persists the choice in the session; SetLocale applies it
// on subsequent requests. Public so the login screen can switch too.
Route::get('/locale/{locale}', function (string $locale) {
    if (array_key_exists($locale, config('app.available_locales', []))) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

// Guest routes (unauthenticated)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/login/pin', [AuthController::class, 'loginWithPin'])->name('login.pin')->middleware('throttle:5,1');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
});

// 2FA challenge routes (no auth middleware — user is mid-login)
Route::get('/2fa/challenge', [\App\Http\Controllers\Web\TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
Route::post('/2fa/challenge', [\App\Http\Controllers\Web\TwoFactorChallengeController::class, 'verify'])->name('two-factor.verify')->middleware('throttle:5,1');

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Maintenance upcoming count (for reminder polling — all authenticated users)
    Route::get('/maintenance/upcoming-count', function () {
        $count = \App\Models\MaintenanceEvent::whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addHours(2))
            ->count();

        return response()->json(['count' => $count]);
    })->name('maintenance.upcoming-count');

    // Process template reference photos — streamed to any authenticated user
    // (operators see work instructions); files are NEVER publicly reachable.
    Route::get('/process-templates/{process_template}/photos/{photo}', [\App\Http\Controllers\Web\Admin\ProcessTemplatePhotoController::class, 'show'])
        ->name('process-templates.photos.show');

    // Rich work-instruction media (image/PDF/video) — same authenticated stream
    // model as photos; Range-enabled so operators can seek videos on a tablet.
    Route::get('/process-templates/{process_template}/media/{media}', [\App\Http\Controllers\Web\Admin\TemplateStepMediaController::class, 'show'])
        ->name('process-templates.media.show');

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
        // Admin-only settings export/import
        Route::get('/export', [\App\Http\Controllers\Web\SettingsController::class, 'exportSettings'])->name('export')->middleware('role:Admin');
        Route::post('/import', [\App\Http\Controllers\Web\SettingsController::class, 'importSettings'])->name('import')->middleware('role:Admin');
        // Admin-only backup & recovery
        Route::post('/backups/full', [\App\Http\Controllers\Web\BackupController::class, 'createFullBackup'])->name('backups.full')->middleware('role:Admin');
        Route::post('/backups/data', [\App\Http\Controllers\Web\BackupController::class, 'createDataBackup'])->name('backups.data')->middleware('role:Admin');
        Route::post('/backups/upload', [\App\Http\Controllers\Web\BackupController::class, 'uploadBackup'])->name('backups.upload')->middleware('role:Admin');
        Route::get('/backups/download/{filename}', [\App\Http\Controllers\Web\BackupController::class, 'downloadBackup'])->name('backups.download')->middleware('role:Admin');
        Route::delete('/backups/{filename}', [\App\Http\Controllers\Web\BackupController::class, 'deleteBackup'])->name('backups.delete')->middleware('role:Admin');
        Route::post('/backups/restore/{filename}', [\App\Http\Controllers\Web\BackupController::class, 'restoreBackup'])->name('backups.restore')->middleware('role:Admin');
        Route::post('/reset', [\App\Http\Controllers\Web\BackupController::class, 'resetSystem'])->name('reset')->middleware('role:Admin');
        // Two-Factor Authentication management
        Route::get('/two-factor/enable', [\App\Http\Controllers\Web\TwoFactorController::class, 'enable'])->name('two-factor.enable');
        Route::post('/two-factor/confirm', [\App\Http\Controllers\Web\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::post('/two-factor/disable', [\App\Http\Controllers\Web\TwoFactorController::class, 'disable'])->name('two-factor.disable');
        Route::post('/two-factor/recovery-codes', [\App\Http\Controllers\Web\TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
        // PIN management
        Route::get('/pin', [\App\Http\Controllers\Web\SettingsController::class, 'showPinForm'])->name('pin');
        Route::post('/pin', [\App\Http\Controllers\Web\SettingsController::class, 'updatePin'])->name('update-pin');
        Route::delete('/pin', [\App\Http\Controllers\Web\SettingsController::class, 'removePin'])->name('remove-pin');
        // Admin-only API token management
        Route::get('/api-tokens', [\App\Http\Controllers\Web\SettingsController::class, 'showApiTokens'])->name('api-tokens')->middleware('role:Admin');
        Route::post('/api-tokens', [\App\Http\Controllers\Web\SettingsController::class, 'createApiToken'])->name('api-tokens.create')->middleware('role:Admin');
        Route::delete('/api-tokens/{token}', [\App\Http\Controllers\Web\SettingsController::class, 'revokeApiToken'])->name('api-tokens.revoke')->middleware('role:Admin');
        // Admin-only role × tab access matrix
        Route::get('/access', [\App\Http\Controllers\Web\SettingsController::class, 'showAccess'])->name('access')->middleware('role:Admin');
        Route::post('/access', [\App\Http\Controllers\Web\SettingsController::class, 'updateAccess'])->name('update-access')->middleware('role:Admin');
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
        Route::get('/queue/check', [OperatorWorkOrderController::class, 'check'])->name('queue.check');
        Route::post('/work-order/{workOrder}/line-status', [OperatorWorkOrderController::class, 'updateLineStatus'])->name('work-order.line-status');
        Route::get('/work-order/{workOrder}', [OperatorWorkOrderController::class, 'show'])->name('work-order.detail');
        Route::post('/batch', [OperatorBatchController::class, 'store'])->name('batch.store');
        Route::post('/batch/{batch}/confirm', [OperatorBatchController::class, 'confirmParameters'])->name('batch.confirm');
        Route::post('/batch/{batch}/quality-check', [OperatorBatchController::class, 'qualityCheck'])->name('batch.quality-check');
        Route::post('/batch/{batch}/packaging-checklist', [OperatorBatchController::class, 'packagingChecklist'])->name('batch.packaging-checklist');
        Route::post('/batch/{batch}/release', [OperatorBatchController::class, 'release'])->name('batch.release');

        // Batch step progression (replaces the old Livewire BatchStepList — see
        // OperatorBatchController::startStep/completeStep delegating to BatchService).
        Route::get('/batch-step/{batchStep}/pick-preview', [OperatorBatchController::class, 'pickPreview'])->name('batch-step.pick-preview');
        Route::post('/batch-step/{batchStep}/start', [OperatorBatchController::class, 'startStep'])->name('batch-step.start');
        Route::post('/batch-step/{batchStep}/complete', [OperatorBatchController::class, 'completeStep'])->name('batch-step.complete');
        Route::post('/batch-step/{batchStep}/skip', [OperatorBatchController::class, 'skipStep'])->name('batch-step.skip');
        Route::post('/batch-step/{batchStep}/choose-variant', [OperatorBatchController::class, 'chooseVariant'])->name('batch-step.choose-variant');
        // Document control: validate a mandatory document so its step can complete.
        Route::post('/batch-step-document/{batchStepDocument}/validate', [OperatorBatchController::class, 'validateDocument'])->name('batch-step-document.validate');
        // Stream a step document's uploaded file (operators read it before validating).
        Route::get('/batch-step-document/{batchStepDocument}/file', [OperatorBatchController::class, 'showDocumentFile'])->name('batch-step-document.file');
        // Work-instruction checklist: tick / un-tick a step checklist item.
        Route::post('/batch-step/{batchStep}/checklist/{checklistItem}/toggle', [OperatorBatchController::class, 'toggleChecklistItem'])->name('batch-step.checklist.toggle');

        Route::post('/issue', [OperatorIssueController::class, 'store'])->name('issue.store');
        Route::post('/scrap', [OperatorScrapController::class, 'store'])->name('scrap.store');

        // Production downtime (replaces the old Livewire DowntimeReporter).
        Route::post('/downtime/start', [\App\Http\Controllers\Web\Operator\DowntimeController::class, 'start'])->name('downtime.start');
        Route::post('/downtime/{downtime}/stop', [\App\Http\Controllers\Web\Operator\DowntimeController::class, 'stop'])->name('downtime.stop');

        // Workstation production view
        Route::get('/workstation', [OperatorWorkstationController::class, 'index'])->name('workstation');
        Route::get('/workstation/check', [OperatorWorkstationController::class, 'check'])->name('workstation.check');
        // Manual machine-state set (#87) — operator/supervisor sets a workstation's state.
        Route::post('/workstation/machine-state/{workstation}', [OperatorWorkstationController::class, 'setMachineState'])->name('workstation.machine-state');
        Route::post('/workstation/{workOrder}/start', [OperatorWorkstationController::class, 'start'])->name('workstation.start');
        Route::post('/workstation/{workOrder}/complete', [OperatorWorkstationController::class, 'complete'])->name('workstation.complete');
        Route::post('/workstation/{workOrder}/shift-entry', [OperatorWorkstationController::class, 'shiftEntry'])->name('workstation.shift-entry');

        // Production quantity corrections
        Route::get('/shift-entry/{shiftEntry}/correct', [ProductionCorrectionController::class, 'edit'])->name('shift-entry.correct');
        Route::put('/shift-entry/{shiftEntry}/correct', [ProductionCorrectionController::class, 'update'])->name('shift-entry.correct.update');
    });

    // Inbound Inspections (Supervisor + Admin) — inspectors perform from this UI
    Route::prefix('inspections')->name('inspections.')->middleware('role:Supervisor|Admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\InspectionController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Web\InspectionController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Web\InspectionController::class, 'store'])->name('store');
        Route::get('/{inspection}', [\App\Http\Controllers\Web\InspectionController::class, 'show'])->name('show');
        Route::post('/{inspection}/results', [\App\Http\Controllers\Web\InspectionController::class, 'recordResult'])->name('record-result');
        Route::post('/{inspection}/complete', [\App\Http\Controllers\Web\InspectionController::class, 'complete'])->name('complete');
        Route::post('/{inspection}/disposition', [\App\Http\Controllers\Web\InspectionController::class, 'disposition'])->name('disposition');
    });

    // Supervisor routes (Supervisor and Admin)
    Route::prefix('supervisor')->name('supervisor.')->middleware('role:Supervisor|Admin')->group(function () {
        Route::get('/dashboard', [SupervisorDashboardController::class, 'index'])->name('dashboard');

        // Shift handover — produced/packed/WIP/shipped balance + close shift (audit snapshot)
        Route::get('/shift-handover', [\App\Http\Controllers\Web\Supervisor\ShiftHandoverController::class, 'index'])->name('shift-handover.index');
        Route::get('/shift-handover/preview', [\App\Http\Controllers\Web\Supervisor\ShiftHandoverController::class, 'preview'])->name('shift-handover.preview');
        Route::post('/shift-handover', [\App\Http\Controllers\Web\Supervisor\ShiftHandoverController::class, 'store'])->name('shift-handover.store');

        // Work Orders (supervisor can create + manage)
        Route::get('/work-orders', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'index'])->name('work-orders.index');
        // create/store before the {workOrder} routes so "create" isn't bound as an id.
        Route::get('/work-orders/create', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'create'])->name('work-orders.create');
        Route::post('/work-orders', [\App\Http\Controllers\Web\Supervisor\WorkOrderController::class, 'store'])->name('work-orders.store');
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
        // Non-conformance disposition (#11)
        Route::post('/issues/{issue}/disposition', [IssueManagementController::class, 'disposition'])->name('issues.disposition');
        // Corrective / preventive actions (CAPA)
        Route::get('/issues/{issue}/actions', [IssueManagementController::class, 'actions'])->name('issues.actions');
        Route::post('/issues/{issue}/actions', [IssueManagementController::class, 'storeAction'])->name('issues.actions.store');
        Route::put('/issues/actions/{action}', [IssueManagementController::class, 'updateAction'])->name('issues.actions.update');
        Route::post('/issues/actions/{action}/start', [IssueManagementController::class, 'startAction'])->name('issues.actions.start');
        Route::post('/issues/actions/{action}/complete', [IssueManagementController::class, 'completeAction'])->name('issues.actions.complete');
        Route::post('/issues/actions/{action}/verify', [IssueManagementController::class, 'verifyAction'])->name('issues.actions.verify');
        Route::delete('/issues/actions/{action}', [IssueManagementController::class, 'destroyAction'])->name('issues.actions.destroy');

        // Quality-control trigger queue (#105) — outstanding controls.
        Route::get('/quality-tasks', [QualityControlTaskController::class, 'index'])->name('quality-tasks.index');
        Route::post('/quality-tasks', [QualityControlTaskController::class, 'storeRoaming'])->name('quality-tasks.roaming');
        Route::post('/quality-tasks/{task}/perform', [QualityControlTaskController::class, 'perform'])->name('quality-tasks.perform');
        Route::post('/quality-tasks/{task}/skip', [QualityControlTaskController::class, 'skip'])->name('quality-tasks.skip');

        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports');
    });

    // Admin routes
    // Per-tab access (Settings → Access matrix) replaces the blanket role:Admin;
    // TabAccessMiddleware maps each /admin path to a tab and checks tab:<key>.
    Route::prefix('admin')->name('admin.')->middleware('tab.access')->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // OEE
        Route::get('/oee', [AdminOeeController::class, 'index'])->name('oee.index');
        Route::get('/oee/print', [AdminOeeController::class, 'print'])->name('oee.print');
        Route::get('/oee/print/pdf', [AdminOeeController::class, 'printPdf'])->name('oee.print.pdf');
        Route::get('/oee/{line}', [AdminOeeController::class, 'show'])->name('oee.show');

        // Reports — Work Order History (read-only historical analysis)
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports');
        Route::get('/reports/export', [AdminReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/{workOrder}', [AdminReportController::class, 'show'])->name('reports.show');

        // Reports — Production Cost (materials + labor + additional, per work order)
        Route::get('/cost-reports', [ProductionCostReportController::class, 'index'])->name('cost-reports.index');
        Route::get('/cost-reports/export', [ProductionCostReportController::class, 'export'])->name('cost-reports.export');
        Route::get('/cost-reports/{workOrder}', [ProductionCostReportController::class, 'show'])->name('cost-reports.show');

        // Alerts
        Route::get('/alerts', [\App\Http\Controllers\Web\Admin\AlertController::class, 'index'])->name('alerts');
        Route::get('/alerts/check', [\App\Http\Controllers\Web\Admin\AlertController::class, 'check'])->name('alerts.check');

        // Update
        Route::get('/update/check', [\App\Http\Controllers\Web\Admin\UpdateController::class, 'check'])->name('update.check');
        Route::post('/update/apply', [\App\Http\Controllers\Web\Admin\UpdateController::class, 'apply'])->name('update.apply');
        Route::get('/update/status', [\App\Http\Controllers\Web\Admin\UpdateController::class, 'status'])->name('update.status');
        Route::get('/update/history', [\App\Http\Controllers\Web\Admin\UpdateController::class, 'history'])->name('update.history');

        // Schedule (planner is the main view; list is a secondary overview)
        Route::get('/schedule/list', [ScheduleController::class, 'index'])->name('schedule.list');
        Route::get('/schedule', [SchedulePlannerController::class, 'index'])->name('schedule');
        Route::get('/schedule/capacity', [\App\Http\Controllers\Web\Admin\ScheduleCapacityController::class, 'index'])->name('schedule.capacity');
        Route::get('/schedule/capacity/cell', [\App\Http\Controllers\Web\Admin\ScheduleCapacityController::class, 'cellOrders'])->name('schedule.capacity.cell');
        Route::get('/schedule/check-updates', [SchedulePlannerController::class, 'checkUpdates'])->name('schedule.check-updates');
        Route::get('/schedule/changes', [SchedulePlannerController::class, 'changes'])->name('schedule.changes');
        Route::post('/schedule/changes/{change}/undo', [SchedulePlannerController::class, 'undoChange'])->name('schedule.changes.undo');
        Route::put('/schedule/{workOrder}', [SchedulePlannerController::class, 'updateOrder'])->name('schedule.update');
        Route::put('/schedule/{workOrder}/resize', [SchedulePlannerController::class, 'resizeOrder'])->name('schedule.resize');

        // Schedule · Employees (tachograph-style day/team/month planner)
        Route::get('/schedule/employees', [\App\Http\Controllers\Web\Admin\EmployeeScheduleController::class, 'index'])->name('schedule.employees');
        Route::get('/schedule/employees/add', [\App\Http\Controllers\Web\Admin\EmployeeScheduleController::class, 'create'])->name('schedule.employees.create');
        Route::post('/schedule/employees', [\App\Http\Controllers\Web\Admin\EmployeeScheduleController::class, 'store'])->name('schedule.employees.store');
        Route::delete('/schedule/employees/{activity}', [\App\Http\Controllers\Web\Admin\EmployeeScheduleController::class, 'destroy'])->name('schedule.employees.destroy');

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
        // Non-conformance disposition (#11)
        Route::post('/issues/{issue}/disposition', [IssueManagementController::class, 'disposition'])->name('issues.disposition');
        // Corrective / preventive actions (CAPA)
        Route::get('/issues/{issue}/actions', [IssueManagementController::class, 'actions'])->name('issues.actions');
        Route::post('/issues/{issue}/actions', [IssueManagementController::class, 'storeAction'])->name('issues.actions.store');
        Route::put('/issues/actions/{action}', [IssueManagementController::class, 'updateAction'])->name('issues.actions.update');
        Route::post('/issues/actions/{action}/start', [IssueManagementController::class, 'startAction'])->name('issues.actions.start');
        Route::post('/issues/actions/{action}/complete', [IssueManagementController::class, 'completeAction'])->name('issues.actions.complete');
        Route::post('/issues/actions/{action}/verify', [IssueManagementController::class, 'verifyAction'])->name('issues.actions.verify');
        Route::delete('/issues/actions/{action}', [IssueManagementController::class, 'destroyAction'])->name('issues.actions.destroy');

        // Quality-control trigger queue (#105) — outstanding controls.
        Route::get('/quality-tasks', [QualityControlTaskController::class, 'index'])->name('quality-tasks.index');
        Route::post('/quality-tasks', [QualityControlTaskController::class, 'storeRoaming'])->name('quality-tasks.roaming');
        Route::post('/quality-tasks/{task}/perform', [QualityControlTaskController::class, 'perform'])->name('quality-tasks.perform');
        Route::post('/quality-tasks/{task}/skip', [QualityControlTaskController::class, 'skip'])->name('quality-tasks.skip');

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

            // Reference photos (work instructions) — uploads throttled (DoS guard)
            Route::post('/{process_template}/photos', [\App\Http\Controllers\Web\Admin\ProcessTemplatePhotoController::class, 'store'])
                ->middleware('throttle:30,1')->name('photos.store');
            Route::delete('/{process_template}/photos/{photo}', [\App\Http\Controllers\Web\Admin\ProcessTemplatePhotoController::class, 'destroy'])->name('photos.destroy');

            // Rich work-instruction media (image/PDF/video) per step — uploads throttled.
            Route::post('/{process_template}/media', [\App\Http\Controllers\Web\Admin\TemplateStepMediaController::class, 'store'])
                ->middleware('throttle:30,1')->name('media.store');
            Route::delete('/{process_template}/media/{media}', [\App\Http\Controllers\Web\Admin\TemplateStepMediaController::class, 'destroy'])->name('media.destroy');

            // Per-step checklist items (work-instruction sign-offs).
            Route::post('/{process_template}/checklist-items', [\App\Http\Controllers\Web\Admin\TemplateStepChecklistController::class, 'store'])->name('checklist-items.store');
            Route::delete('/{process_template}/checklist-items/{checklistItem}', [\App\Http\Controllers\Web\Admin\TemplateStepChecklistController::class, 'destroy'])->name('checklist-items.destroy');

            // BOM Management (nested under process templates)
            Route::get('/{process_template}/bom', [BomManagementController::class, 'index'])->name('bom');
            Route::post('/{process_template}/bom', [BomManagementController::class, 'store'])->name('bom.store');
            Route::put('/{process_template}/bom/{bom_item}', [BomManagementController::class, 'update'])->name('bom.update');
            Route::delete('/{process_template}/bom/{bom_item}', [BomManagementController::class, 'destroy'])->name('bom.destroy');
        });

        // LOT Sequences
        Route::post('lot-sequences/preview', [AdminLotSequenceController::class, 'preview'])->name('lot-sequences.preview');
        Route::resource('lot-sequences', AdminLotSequenceController::class)->except(['show']);

        // Pallets
        Route::resource('pallets', AdminPalletController::class)->except(['show']);

        // ── ISA-95: Material Lots (physical lots) ───────────────────────────
        Route::resource('material-lots', AdminMaterialLotController::class);
        Route::post('/material-lots/{materialLot}/hold', [AdminMaterialLotController::class, 'hold'])->name('material-lots.hold');
        Route::post('/material-lots/{materialLot}/release', [AdminMaterialLotController::class, 'release'])->name('material-lots.release');

        // ── Material Traceability / Genealogy (React/Inertia — ported from develop Blade) ──
        Route::get('/traceability', [\App\Http\Controllers\Web\Admin\TraceabilityController::class, 'index'])->name('traceability.index');

        // Dashboard Widgets Setup
        Route::get('/dashboard-widgets', [\App\Http\Controllers\Web\Admin\DashboardWidgetController::class, 'index'])->name('dashboard-widgets.index');
        Route::post('/dashboard-widgets/{widget}/toggle', [\App\Http\Controllers\Web\Admin\DashboardWidgetController::class, 'toggle'])->name('dashboard-widgets.toggle');
        Route::post('/dashboard-widgets/reorder', [\App\Http\Controllers\Web\Admin\DashboardWidgetController::class, 'reorder'])->name('dashboard-widgets.reorder');
        Route::post('/dashboard-widgets/save-all', [\App\Http\Controllers\Web\Admin\DashboardWidgetController::class, 'saveAll'])->name('dashboard-widgets.save-all');

        // Batch Reports
        Route::get('/batches/{batch}/report', [\App\Http\Controllers\Web\Admin\BatchReportController::class, 'show'])->name('batch-report');
        Route::get('/batches/{batch}/report/pdf', [\App\Http\Controllers\Web\Admin\BatchReportController::class, 'pdf'])->name('batch-report.pdf');

        // Materials Management
        Route::resource('materials', MaterialManagementController::class);
        Route::post('/materials/{material}/toggle-active', [MaterialManagementController::class, 'toggleActive'])->name('materials.toggle-active');
        Route::get('/materials-import', [MaterialImportController::class, 'index'])->name('materials.import');
        Route::post('/materials-import/upload', [MaterialImportController::class, 'upload'])->name('materials.import.upload');
        Route::post('/materials-import/process', [MaterialImportController::class, 'process'])->name('materials.import.process');

        // Integration Configs
        Route::resource('integrations', IntegrationConfigController::class)->except(['show']);

        // Outgoing webhooks (#20)
        Route::resource('webhooks', \App\Http\Controllers\Web\Admin\WebhookController::class)->except(['show']);
        Route::post('/webhooks/{webhook}/toggle-active', [\App\Http\Controllers\Web\Admin\WebhookController::class, 'toggleActive'])->name('webhooks.toggle-active');
        Route::post('/webhooks/{webhook}/test', [\App\Http\Controllers\Web\Admin\WebhookController::class, 'test'])->name('webhooks.test');
        Route::get('/webhooks/{webhook}/deliveries', [\App\Http\Controllers\Web\Admin\WebhookController::class, 'deliveries'])->name('webhooks.deliveries');

        // Import Example CSV
        Route::get('/import-example/{type}', [ImportExampleController::class, 'download'])->name('import-example');

        // CSV Import
        Route::get('/csv-import', [AdminCsvImportController::class, 'index'])->name('csv-import');
        Route::post('/csv-import/upload', [AdminCsvImportController::class, 'upload'])->name('csv-import.upload');
        Route::post('/csv-import/process', [AdminCsvImportController::class, 'process'])->name('csv-import.process');
        Route::delete('/csv-import/mappings/{mapping}', [AdminCsvImportController::class, 'destroyMapping'])->name('csv-import.mappings.destroy');

        // Trash — soft-deleted rows across all domain entities, with restore.
        Route::get('/trash', [\App\Http\Controllers\Web\Admin\TrashController::class, 'index'])->name('trash.index');
        Route::post('/trash/{type}/{id}/restore', [\App\Http\Controllers\Web\Admin\TrashController::class, 'restore'])
            ->whereNumber('id')->name('trash.restore');

        // Audit Logs
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs');
        Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->name('audit-logs.export');

        // Unified Activity Logs (audit + request + auth events)
        Route::get('/logs/activity', [\App\Http\Controllers\Web\Admin\ActivityLogController::class, 'index'])->name('logs.activity');
        Route::get('/logs/activity/export', [\App\Http\Controllers\Web\Admin\ActivityLogController::class, 'export'])->name('logs.activity.export');

        // System Logs (Laravel app log + failed jobs + deployments)
        Route::get('/logs/system', [\App\Http\Controllers\Web\Admin\SystemLogController::class, 'index'])->name('logs.system');
        Route::get('/logs/system/tail', [\App\Http\Controllers\Web\Admin\SystemLogController::class, 'tail'])->name('logs.system.tail');
        Route::post('/logs/system/failed-jobs/{uuid}/retry', [\App\Http\Controllers\Web\Admin\SystemLogController::class, 'retryFailedJob'])
            ->name('logs.system.retry-failed-job');

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
        Route::resource('factories', FactoryController::class);
        Route::post('/factories/{factory}/toggle-active', [FactoryController::class, 'toggleActive'])->name('factories.toggle-active');

        // Divisions
        Route::resource('divisions', DivisionController::class)->except(['show']);
        Route::post('/divisions/{division}/toggle-active', [DivisionController::class, 'toggleActive'])->name('divisions.toggle-active');

        // ISA-95 Equipment Hierarchy: Sites & Areas
        Route::resource('sites', SiteController::class);
        Route::post('/sites/{site}/toggle-active', [SiteController::class, 'toggleActive'])->name('sites.toggle-active');
        Route::get('/areas/create', [AreaController::class, 'create'])->name('areas.create');
        Route::resource('sites.areas', AreaController::class)->shallow();
        Route::get('/areas', [AreaController::class, 'index'])->name('areas.index'); // flat list across sites
        Route::post('/areas/{area}/toggle-active', [AreaController::class, 'toggleActive'])->name('areas.toggle-active');

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

        // Custom Fields (admin-defined fields on registered entities)
        Route::resource('custom-fields', CustomFieldDefinitionController::class)
            ->parameters(['custom-fields' => 'customField'])->except(['show']);
        Route::post('/custom-fields/{customField}/toggle-active', [CustomFieldDefinitionController::class, 'toggleActive'])->name('custom-fields.toggle-active');
        Route::get('/custom-field-files/{file}', [CustomFieldDefinitionController::class, 'downloadFile'])->name('custom-field-files.show');

        // Scrap Reasons
        Route::resource('scrap-reasons', ScrapReasonController::class)->except(['show']);
        Route::post('/scrap-reasons/{scrapReason}/toggle-active', [ScrapReasonController::class, 'toggleActive'])->name('scrap-reasons.toggle-active');

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
        Route::resource('workers', WorkerController::class);
        Route::post('/workers/{worker}/toggle-active', [WorkerController::class, 'toggleActive'])->name('workers.toggle-active');
        // Worker certifications (ISA-95 Personnel Capability — pivot management)
        Route::post('/workers/{worker}/skills', [WorkerController::class, 'attachSkill'])->name('workers.skills.attach');
        Route::delete('/workers/{worker}/skills/{skill}', [WorkerController::class, 'detachSkill'])->name('workers.skills.detach');

        // Worker absences (vacation / sick / …) — availability source.
        Route::resource('worker-absences', WorkerAbsenceController::class)->except(['show']);

        // Crew break windows (recurring lunch / tea breaks) — availability source.
        Route::resource('crew-break-windows', \App\Http\Controllers\Web\Admin\CrewBreakWindowController::class)->except(['show']);

        // ISA-95 Personnel Classes (competency templates)
        Route::resource('personnel-classes', \App\Http\Controllers\Web\Admin\PersonnelClassController::class);

        // ── Gate 5: Tracking Advanced ─────────────────────────────────────────
        // Production Anomalies
        Route::get('/production-anomalies', [ProductionAnomalyController::class, 'index'])->name('production-anomalies.index');
        Route::get('/production-anomalies/create', [ProductionAnomalyController::class, 'create'])->name('production-anomalies.create');
        Route::post('/production-anomalies', [ProductionAnomalyController::class, 'store'])->name('production-anomalies.store');
        Route::post('/production-anomalies/{productionAnomaly}/process', [ProductionAnomalyController::class, 'process'])->name('production-anomalies.process');
        Route::delete('/production-anomalies/{productionAnomaly}', [ProductionAnomalyController::class, 'destroy'])->name('production-anomalies.destroy');

        // Scrap reporting (Pareto, scrap rate per line, trend)
        Route::get('/scrap-reports', [ScrapReportController::class, 'index'])->name('scrap-reports.index');

        // Non-conformance reporting (Pareto by issue type, disposition summary) (#11)
        Route::get('/non-conformance-reports', [\App\Http\Controllers\Web\Admin\NonConformanceReportController::class, 'index'])->name('non-conformance-reports.index');

        // MRP net requirements & shortage report (#90)
        Route::get('/net-requirements', [\App\Http\Controllers\Web\Admin\NetRequirementsReportController::class, 'index'])->name('net-requirements.index');

        // Inspection Plans (admin CRUD + version publish)
        Route::post('inspection-plans/{inspection_plan}/publish', [\App\Http\Controllers\Web\Admin\InspectionPlanController::class, 'publish'])->name('inspection-plans.publish');
        Route::resource('inspection-plans', \App\Http\Controllers\Web\Admin\InspectionPlanController::class)->except(['show']);

        // Quality-control triggers (#105) — admin CRUD.
        Route::post('quality-control-triggers/{qualityControlTrigger}/toggle-active', [\App\Http\Controllers\Web\Admin\QualityControlTriggerController::class, 'toggleActive'])->name('quality-control-triggers.toggle-active');
        Route::resource('quality-control-triggers', \App\Http\Controllers\Web\Admin\QualityControlTriggerController::class)->except(['show']);

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

        // Modbus connections (React/Inertia — ported from the original develop Blade UI)
        Route::resource('connectivity/modbus', \App\Http\Controllers\Web\Admin\Connectivity\ModbusConnectionController::class)
            ->parameters(['modbus' => 'machineConnection'])
            ->names('connectivity.modbus');
        Route::post('/connectivity/modbus/{machineConnection}/tags', [\App\Http\Controllers\Web\Admin\Connectivity\ModbusConnectionController::class, 'storeTag'])->name('connectivity.modbus.tags.store');
        Route::delete('/connectivity/modbus/{machineConnection}/tags/{tag}', [\App\Http\Controllers\Web\Admin\Connectivity\ModbusConnectionController::class, 'destroyTag'])->name('connectivity.modbus.tags.destroy');

        // OPC UA connections (served by an external gateway sidecar)
        Route::resource('connectivity/opcua', \App\Http\Controllers\Web\Admin\Connectivity\OpcuaConnectionController::class)
            ->parameters(['opcua' => 'machineConnection'])
            ->names('connectivity.opcua');
        Route::post('/connectivity/opcua/{machineConnection}/tags', [\App\Http\Controllers\Web\Admin\Connectivity\OpcuaConnectionController::class, 'storeTag'])->name('connectivity.opcua.tags.store');
        Route::delete('/connectivity/opcua/{machineConnection}/tags/{tag}', [\App\Http\Controllers\Web\Admin\Connectivity\OpcuaConnectionController::class, 'destroyTag'])->name('connectivity.opcua.tags.destroy');

        // Live machine monitor (React/Inertia — ported from the original develop Blade UI)
        Route::get('/machine-monitor', [\App\Http\Controllers\Web\Admin\MachineMonitorController::class, 'index'])->name('machine-monitor.index');
        Route::get('/machine-monitor/check', [\App\Http\Controllers\Web\Admin\MachineMonitorController::class, 'check'])->name('machine-monitor.check');
        // Manual machine-state set (#87) — supervisor/admin override from the monitor.
        Route::post('/machine-monitor/{workstation}/state', [\App\Http\Controllers\Web\Admin\MachineMonitorController::class, 'setState'])->name('machine-monitor.set-state');

        // ── Gate 7: Maintenance ───────────────────────────────────────────────
        // Tools
        Route::resource('tools', ToolController::class)->except(['show']);

        // Maintenance Events
        Route::resource('maintenance-events', MaintenanceEventController::class);
        Route::post('/maintenance-events/{maintenanceEvent}/start', [MaintenanceEventController::class, 'start'])->name('maintenance-events.start');
        Route::post('/maintenance-events/{maintenanceEvent}/complete', [MaintenanceEventController::class, 'complete'])->name('maintenance-events.complete');
        Route::post('/maintenance-events/{maintenanceEvent}/cancel', [MaintenanceEventController::class, 'cancel'])->name('maintenance-events.cancel');

        // ── ISA-95: Process Segments (reusable operation definitions) ────────
        Route::resource('process-segments', \App\Http\Controllers\Web\Admin\ProcessSegmentController::class);

        // Maintenance Schedules (recurring preventive maintenance)
        Route::resource('maintenance-schedules', \App\Http\Controllers\Web\Admin\MaintenanceScheduleController::class)
            ->except(['show']);
        Route::post('/maintenance-schedules/{maintenanceSchedule}/generate-now', [\App\Http\Controllers\Web\Admin\MaintenanceScheduleController::class, 'generateNow'])
            ->name('maintenance-schedules.generate-now');
    });

    // ── Packaging ───────────────────────────────────────────────────────────
    Route::name('packaging.')->prefix('packaging')->group(function () {
        Route::middleware('role:Operator|Supervisor|Admin')->group(function () {
            Route::get('/station', [PackagingController::class, 'station'])->name('station');
            Route::post('/scan', [PackagingController::class, 'scan'])->name('scan');
            Route::get('/items', [PackagingController::class, 'items'])->name('items');
            Route::get('/history', [PackagingController::class, 'history'])->name('history');
            Route::get('/history/poll', [PackagingController::class, 'historyAfter'])->name('history.poll');
            Route::get('/stats', [PackagingController::class, 'stats'])->name('stats');
            Route::get('/pallets', [PackagingController::class, 'openPallets'])->name('pallets.open');
            Route::post('/pallets', [PackagingController::class, 'createPallet'])->name('pallets.create');
            Route::post('/pallets/{pallet}/close', [PackagingController::class, 'closePallet'])->name('pallets.close');
        });

        Route::middleware('role:Supervisor|Admin')->group(function () {
            Route::get('/', [PackagingController::class, 'adminOverview'])->name('overview');
            Route::get('/eans', [PackagingEanController::class, 'index'])->name('eans.index');
            Route::post('/eans', [PackagingEanController::class, 'store'])->name('eans.store');
            Route::delete('/eans/{ean}', [PackagingEanController::class, 'destroy'])->name('eans.destroy');
        });

        Route::middleware('role:Operator|Supervisor|Admin')->prefix('labels')->name('labels.')->group(function () {
            Route::get('/work-order/{workOrder}/pdf', [LabelPrintController::class, 'workOrderPdf'])->name('work-order.pdf');
            Route::get('/work-order/{workOrder}/zpl', [LabelPrintController::class, 'workOrderZpl'])->name('work-order.zpl');
            Route::get('/finished-goods/{batch}/pdf', [LabelPrintController::class, 'finishedGoodsPdf'])->name('finished-goods.pdf');
            Route::get('/finished-goods/{batch}/zpl', [LabelPrintController::class, 'finishedGoodsZpl'])->name('finished-goods.zpl');
            Route::get('/workstation-step/{batchStep}/pdf', [LabelPrintController::class, 'batchStepPdf'])->name('workstation-step.pdf');
            Route::get('/workstation-step/{batchStep}/zpl', [LabelPrintController::class, 'batchStepZpl'])->name('workstation-step.zpl');
            Route::get('/pallet/{pallet}/pdf', [LabelPrintController::class, 'palletPdf'])->name('pallet.pdf');
            Route::get('/pallet/{pallet}/zpl', [LabelPrintController::class, 'palletZpl'])->name('pallet.zpl');
            Route::post('/print-multiple', [LabelPrintController::class, 'printMultiple'])->name('print-multiple');
        });

        Route::middleware('role:Admin')->group(function () {
            Route::resource('label-templates', LabelTemplateController::class)->except(['show']);
            Route::post('/label-templates/{labelTemplate}/set-default', [LabelTemplateController::class, 'setDefault'])->name('label-templates.set-default');
        });
    });
});

/*
 * E2E test-control surface — isolated-tenant lifecycle for the Playwright suite
 * (../../e2e). Hard-gated by EnsureE2eEnabled: 404 unless config('e2e.enabled')
 * AND non-production. Called by the test runner without a session, so these are
 * CSRF-exempt (see bootstrap/app.php validateCsrfTokens except).
 */
Route::prefix('__e2e__')
    ->middleware(\App\Http\Middleware\EnsureE2eEnabled::class)
    ->group(function () {
        Route::post('/tenant', [E2eTenantController::class, 'store']);
        Route::post('/tenant/{tenant}/reset', [E2eTenantController::class, 'reset']);
        Route::post('/tenant/{tenant}/bump-work-order', [E2eTenantController::class, 'bumpWorkOrder']);
        Route::delete('/tenant/{tenant}', [E2eTenantController::class, 'destroy']);
    });
