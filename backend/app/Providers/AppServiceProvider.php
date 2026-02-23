<?php

namespace App\Providers;

use App\Services\MenuRegistry;
use App\Services\ModuleManager;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share the menu registry with every view so nav.blade.php can render
        // items and groups registered by modules without additional controller work.
        View::share('menuRegistry', $this->app->make(MenuRegistry::class));

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
