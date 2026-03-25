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
        $this->app->singleton(ModuleManager::class, fn() => new ModuleManager());
        $this->app->singleton(MenuRegistry::class, fn() => new MenuRegistry());
        $this->app->singleton(WidgetRegistry::class, fn() => new WidgetRegistry());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
