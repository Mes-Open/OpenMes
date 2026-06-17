<?php

namespace App\Providers;

use App\Console\Commands\ResetPackagingShiftCommand;
use App\Listeners\LogAuthEvent;
use App\Services\MenuRegistry;
use App\Services\ModuleManager;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\App;
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
        // Until the installer has run (no storage/installed flag yet) there is
        // no configured database. The shipped .env defaults to database-backed
        // sessions (correct for the Docker stack, whose entrypoint sets the
        // flag before serving), but on a bare PHP host that makes every
        // request — including the install wizard itself — query a database that
        // does not exist yet, so the wizard can never render (HTTP 500). Force
        // file-based session/cache drivers while uninstalled so the wizard
        // boots without a DB; once installed, the configured drivers and the
        // migrated `sessions` table take over.
        if (! $this->app->runningUnitTests() && ! file_exists(storage_path('installed'))) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
            ]);
        }

        // Reverb sync: register model → collection broadcast listeners.
        \App\Sync\CollectionBroadcaster::boot();

        // unique:/exists: validation ignores soft-deleted rows on tables in
        // SoftDeleteRegistry (one hook instead of per-rule whereNull clauses).
        $this->app['validator']->setPresenceVerifier(
            new \App\Validation\SoftDeleteAwarePresenceVerifier($this->app['db']),
        );

        // Scramble API docs — only logged-in users can view /docs/api and /docs/api.json.
        Gate::define('viewApiDocs', fn ($user) => $user !== null);

        // Admins always pass tab:* access checks — a safety net so they can
        // never lock themselves out of the admin panel via the access matrix,
        // even if a new tab's permission hasn't been granted yet.
        Gate::before(function ($user, string $ability) {
            if (str_starts_with($ability, 'tab:') && $user->hasRole('Admin')) {
                return true;
            }

            return null;
        });

        // Octane keeps the Spatie PermissionRegistrar singleton (and its
        // in-memory permission collection) alive across requests in a worker, so
        // a runtime permission change — e.g. the Settings → Access matrix — isn't
        // seen by other workers until they recycle, causing inconsistent 403s.
        // Drop the singleton at the start of each Octane request so it reloads
        // from the shared cache (no cache thrashing, just a per-request re-read).
        if (class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            Event::listen(\Laravel\Octane\Events\RequestReceived::class, function ($event) {
                $event->sandbox->forgetInstance(\Spatie\Permission\PermissionRegistrar::class);
            });
        }

        // Register the authentication event subscriber so login / logout /
        // failed-login attempts are written to the audit_logs table.
        Event::subscribe(LogAuthEvent::class);

        // Live-edit (dev/staging only): under Octane the Vite manifest is cached
        // in a static property in worker memory, so a `vite build --watch` rebuild
        // wouldn't appear until workers recycle. When OCTANE_LIVE_RELOAD is set
        // (the dev overlay), clear that static cache before each Octane request so
        // rebuilt .jsx assets show on a plain refresh. No effect in production.
        if (env('OCTANE_LIVE_RELOAD') && class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            Event::listen(\Laravel\Octane\Events\RequestReceived::class, function () {
                $manifests = new \ReflectionProperty(\Illuminate\Foundation\Vite::class, 'manifests');
                $manifests->setAccessible(true);
                $manifests->setValue(null, []);
            });
        }

        // Share registries with every view so layouts and dashboards can render
        // items registered by modules without additional controller work.
        View::share('menuRegistry', $this->app->make(MenuRegistry::class));
        View::share('widgetRegistry', $this->app->make(WidgetRegistry::class));

        // Set application locale from system_settings. Also override
        // config('app.locale') so that under Octane, where FlushLocaleState
        // resets the locale to the config default on every request, the
        // system-wide language still applies (SetLocale then layers any
        // per-session override on top).
        try {
            $row = DB::table('system_settings')->where('key', 'language')->first();
            $locale = $row ? json_decode($row->value, true) : null;
            if ($locale && in_array($locale, array_keys($this->availableLocales()))) {
                config(['app.locale' => $locale]);
                App::setLocale($locale);
            }
        } catch (\Throwable) {
            // DB not available during install
        }

        // Share available locales with views
        View::share('availableLocales', $this->availableLocales());
        View::share('currentLocale', App::getLocale());

        // NOTE: the old Blade-layout View::composers (demoExpiresAt for
        // layouts.app, alertCount for layouts.components.sidebar) were removed
        // when those layouts were deleted in the React/Inertia migration. The
        // alert badge is now computed live client-side (LiveAlertCount.jsx) and
        // the server fallback comes from HandleInertiaRequests->nav.alertCount.

        // Share current language name
        View::share('currentLocaleName', $this->availableLocales()[App::getLocale()] ?? 'English');

        // (Packaging menu items were registered into MenuRegistry to feed the
        // deleted Blade sidebar; the React sidebar nav is defined in
        // resources/js/layouts/adminNav.js, so that registration was removed.)

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
            'tr' => 'Türkçe',
        ];
    }
}
