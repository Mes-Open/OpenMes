<?php

namespace App\Providers;

use App\Console\Commands\ResetPackagingShiftCommand;
use App\Http\Controllers\Web\Admin\AlertController;
use App\Listeners\LogAuthEvent;
use App\Services\MenuRegistry;
use App\Services\ModuleManager;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, fn () => new ModuleManager);
        $this->app->singleton(MenuRegistry::class, fn () => new MenuRegistry);
        $this->app->singleton(WidgetRegistry::class, fn () => new WidgetRegistry);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Scramble API docs — only logged-in users can view /docs/api and /docs/api.json.
        Gate::define('viewApiDocs', fn ($user) => $user !== null);

        // Register the authentication event subscriber so login / logout /
        // failed-login attempts are written to the audit_logs table.
        Event::subscribe(LogAuthEvent::class);

        // Share registries with every view so layouts and dashboards can render
        // items registered by modules without additional controller work.
        View::share('menuRegistry', $this->app->make(MenuRegistry::class));
        View::share('widgetRegistry', $this->app->make(WidgetRegistry::class));

        // Set application locale from system_settings
        try {
            $row = DB::table('system_settings')->where('key', 'language')->first();
            $locale = $row ? json_decode($row->value, true) : null;
            if ($locale && in_array($locale, array_keys($this->availableLocales()))) {
                App::setLocale($locale);
            }
        } catch (\Throwable) {
            // DB not available during install
        }

        // Share available locales with views
        View::share('availableLocales', $this->availableLocales());
        View::share('currentLocale', App::getLocale());

        // Demo account expiry — passed to layout so the countdown banner can render
        View::composer('layouts.app', function ($view) {
            $expiresAt = null;
            try {
                if (Auth::hasUser()) {
                    $tenant = Auth::user()->tenant;
                    $expiresAt = $tenant?->expires_at;
                }
            } catch (\Throwable) {
            }
            $view->with('demoExpiresAt', $expiresAt);
        });

        // Alert badge count — only computed when user is authenticated Admin/Supervisor
        View::composer('layouts.components.sidebar', function ($view) {
            $alertCount = 0;
            try {
                if (Auth::check() && Auth::user()->hasAnyRole(['Admin', 'Supervisor'])) {
                    $alertCount = AlertController::totalCount();
                }
            } catch (\Throwable) {
            }
            $view->with('alertCount', $alertCount);
        });

        // Share current language name
        View::share('currentLocaleName', $this->availableLocales()[App::getLocale()] ?? 'English');

        // Packaging menu items — registered via View::composer so auth() check
        // works (boot runs before auth middleware, so direct auth()->check() always false).
        $menu = $this->app->make(MenuRegistry::class);
        $menu->addGroup('packaging', __('Packaging'), order: 40);
        $menu->addGroupItem('packaging', __('Scanning Station'), '/packaging/station', order: 10);

        View::composer('layouts.components.sidebar', function () use ($menu) {
            if (Auth::check() && Auth::user()->hasAnyRole(['Admin', 'Supervisor'])) {
                $menu->addGroupItem('packaging', __('Packaging Overview'), '/packaging', order: 20);
                $menu->addGroupItem('packaging', __('EAN Management'), '/packaging/eans', order: 30);
            }

            if (Auth::check() && Auth::user()->hasRole('Admin')) {
                $menu->addGroupItem('packaging', __('Label Templates'), '/packaging/label-templates', order: 40);
            }
        });

        // Register Packaging console commands
        if ($this->app->runningInConsole()) {
            $this->commands([ResetPackagingShiftCommand::class]);
        }

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

    /**
     * Available locales — add new languages here.
     * Each JSON file in lang/ directory is auto-discovered by Laravel.
     */
    private function availableLocales(): array
    {
        return [
            'en' => 'English',
            'pl' => 'Polski',
        ];
    }
}
