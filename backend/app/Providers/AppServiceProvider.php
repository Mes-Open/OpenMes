<?php

namespace App\Providers;

use App\Http\Controllers\Web\Admin\AlertController;
use App\Services\MenuRegistry;
use App\Services\ModuleManager;
use App\Services\WidgetRegistry;
use App\Services\Security\FactorySecurityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Contracts\Services\WorkOrderServiceInterface::class, \App\Services\WorkOrder\WorkOrderService::class);
        $this->app->singleton(\App\Contracts\Services\IssueServiceInterface::class, \App\Services\IssueService::class);
        $this->app->singleton(\App\Contracts\Services\BatchServiceInterface::class, \App\Services\WorkOrder\BatchService::class);
        $this->app->singleton(\App\Contracts\Services\CsvParserServiceInterface::class, \App\Services\CsvImport\CsvParserService::class);
        $this->app->singleton(\App\Contracts\Services\WorkOrderImportServiceInterface::class, \App\Services\CsvImport\WorkOrderImportService::class);
        $this->app->singleton(\App\Contracts\Services\SnapshotServiceInterface::class, \App\Services\ProcessTemplate\SnapshotService::class);
        $this->app->singleton(\App\Contracts\Services\AuthServiceInterface::class, \App\Services\Auth\AuthService::class);
        $this->app->singleton(\App\Contracts\Services\FactorySecurityServiceInterface::class, \App\Services\Security\FactorySecurityService::class);
        $this->app->singleton(\App\Contracts\Services\MachineMonitorServiceInterface::class, \App\Services\Industrial\MachineMonitorService::class);
        $this->app->singleton(\App\Contracts\Services\LineageGraphServiceInterface::class, \App\Services\Traceability\LineageGraphService::class);
        $this->app->singleton(\App\Contracts\Services\OeeCalculationServiceInterface::class, \App\Services\Analytics\OeeCalculationService::class);
        $this->app->singleton(\App\Contracts\Services\FaultIntelligenceServiceInterface::class, \App\Services\Analytics\FaultIntelligenceService::class);
        $this->app->singleton(\App\Contracts\Services\EdgeNodeSyncServiceInterface::class, \App\Services\Edge\EdgeNodeSyncService::class);
        $this->app->singleton(\App\Contracts\Services\ConstraintSchedulerInterface::class, \App\Services\Scheduling\ConstraintScheduler::class);

        $this->app->singleton(ModuleManager::class, fn() => new ModuleManager());
        $this->app->singleton(MenuRegistry::class, fn() => new MenuRegistry());
        $this->app->singleton(WidgetRegistry::class, fn() => new WidgetRegistry());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register event subscribers
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\UpdateWorkOrderStatusOnIssueChange::class);

        // Initialize shop-floor security gates
        app(FactorySecurityService::class)->defineGates();

        // Share registries with every view so layouts and dashboards can render
        // items registered by modules without additional controller work.
        View::share('menuRegistry', $this->app->make(MenuRegistry::class));
        View::share('widgetRegistry', $this->app->make(WidgetRegistry::class));

        // Alert badge count — only computed when user is authenticated Admin/Supervisor
        View::composer('layouts.components.sidebar', function ($view) {
            $alertCount = 0;
            try {
                if (Auth::check() && Auth::user()->hasAnyRole(['Admin', 'Supervisor'])) {
                    $alertCount = AlertController::totalCount();
                }
            } catch (\Throwable) {}
            $view->with('alertCount', $alertCount);
        });

        // Load enabled modules — wrapped in try/catch so a bad module
        // never prevents the application from booting.
        try {
            /** @var ModuleManager $manager */
            $manager = $this->app->make(ModuleManager::class);
            $manager->loadEnabled($this->app);
        } catch (\Throwable) {
            // Silent — database may not be available during fresh install
        }
    }
}
