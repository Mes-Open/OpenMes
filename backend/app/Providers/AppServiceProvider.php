<?php

namespace App\Providers;

use App\Console\Commands\ResetPackagingShiftCommand;
use App\Listeners\LogAuthEvent;
use App\Services\MenuRegistry;
use App\Services\ModuleManager;
use App\Services\WidgetRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
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
        // Request-scoped tenant for headless (API-key) contexts. Set per request
        // by AuthenticateApiKey; falls through to null for user-authenticated
        // requests, which resolve the tenant from the logged-in user instead.
        $this->app->singleton(\App\Support\TenantContext::class, fn () => new \App\Support\TenantContext);
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

        // Plant timezone chosen in the installer. Applied here rather than left to
        // APP_TIMEZONE because docker-compose sets that variable on every service,
        // and a real environment variable overrides the .env file — so on a Docker
        // install the env route cannot be changed without editing compose files.
        // Absent setting = keep whatever APP_TIMEZONE resolved to.
        \App\Support\TimezoneRegistry::apply();

        // A queue worker is long-lived too, so the boot-time apply() above would
        // pin it to the zone that was configured when it started. Re-read before
        // each job; web requests get the same treatment in ApplyPlantTimezone.
        Queue::looping(fn () => \App\Support\TimezoneRegistry::refresh());

        // Reverb sync: register model → collection broadcast listeners.
        \App\Sync\CollectionBroadcaster::boot();

        // Reverb push for the live shift monitor: machine state, counters and
        // stops nudge their workstation's channel so the page re-fetches.
        \App\Sync\ShiftMonitorBroadcaster::boot();

        // unique:/exists: validation ignores soft-deleted rows on tables in
        // SoftDeleteRegistry (one hook instead of per-rule whereNull clauses).
        $this->app['validator']->setPresenceVerifier(
            new \App\Validation\SoftDeleteAwarePresenceVerifier($this->app['db']),
        );

        // ERP integration API rate limits, keyed per API key (falling back to
        // client IP before a key is resolved). Import is heavier and DB-mutating
        // so it is throttled tighter than the read/export endpoints.
        RateLimiter::for('erp-import', function ($request) {
            $key = $request->attributes->get('api_key');
            $id = $key?->id ?? $request->ip();

            return Limit::perMinute(30)->by('erp-import:'.$id);
        });
        RateLimiter::for('erp-read', function ($request) {
            $key = $request->attributes->get('api_key');
            $id = $key?->id ?? $request->ip();

            return Limit::perMinute(120)->by('erp-read:'.$id);
        });

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

        // Warehousing (#212): a concluded work order owes the warehouse a
        // material release and a product receipt. The listener creates them as
        // drafts and no-ops when the module is off or no warehouse exists.
        Event::listen(
            \App\Events\WorkOrder\WorkOrderCompleted::class,
            \App\Listeners\GenerateWorkOrderStockDocuments::class,
        );

        // Outgoing webhooks (#20): observe the source models so a status change /
        // creation fans out to subscribed endpoints. The dispatcher is
        // best-effort and never breaks the underlying write.
        \App\Models\WorkOrder::observe(\App\Observers\WorkOrderWebhookObserver::class);
        \App\Models\Issue::observe(\App\Observers\IssueWebhookObserver::class);
        \App\Models\Batch::observe(\App\Observers\BatchWebhookObserver::class);

        // Module hook system: dispatch the domain events modules listen to.
        // Typed lifecycle events (previously defined but never fired) — these make
        // WorkOrderCreated/Updated/Completed and StepStarted/Completed real on every
        // save path; BatchCreated is fired via Batch::$dispatchesEvents.
        \App\Models\WorkOrder::observe(\App\Observers\WorkOrderEventObserver::class);
        \App\Models\BatchStep::observe(\App\Observers\BatchStepEventObserver::class);
        // Extra segments live in their own table, so a segment-only planner edit
        // changes no work_orders column — watch them for the schedule hook too.
        \App\Models\WorkOrderPlacement::observe(\App\Observers\WorkOrderPlacementEventObserver::class);

        // Quality gates (#quality-gate): a recorded output that fails its
        // configured expected result auto-raises a blocking Issue, which the
        // existing WorkOrder::isBlocked()/BatchStep::canStart() checks already
        // use to stop the next station — see BatchStepOutputValueObserver.
        \App\Models\BatchStepOutputValue::observe(\App\Observers\BatchStepOutputValueObserver::class);

        // Generic CRUD hook: one wildcard Eloquent listener re-dispatches
        // ResourceChanged for every curated resource (SoftDeleteRegistry::MODELS)
        // so a module can hook any create/update/delete without per-model wiring.
        // Sensitive models are excluded — ResourceChanged carries the full model
        // to third-party listeners, so we never hand out User (password hash) or
        // ApiKey (secret hash) rows.
        $sensitive = [\App\Models\User::class, \App\Models\ApiKey::class];
        $hookedModels = array_diff_key(
            array_flip(array_values(\App\Support\SoftDeleteRegistry::MODELS)),
            array_flip($sensitive),
        );
        foreach (['created', 'updated', 'deleted'] as $verb) {
            Event::listen("eloquent.{$verb}: *", function (string $eventName, array $data) use ($hookedModels, $verb) {
                $model = $data[0] ?? null;
                if ($model instanceof \Illuminate\Database\Eloquent\Model && isset($hookedModels[$model::class])) {
                    // A throwing module listener must never break the core write
                    // that triggered it (mirrors the best-effort webhook observers).
                    try {
                        event(new \App\Events\Resource\ResourceChanged($model, $verb));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('ResourceChanged hook failed', ['error' => $e->getMessage()]);
                    }
                }
            });
        }

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

        // Module extension registries are bridged to the React/Inertia frontend,
        // not consumed from Blade (the Blade layouts were deleted in the React
        // migration): MenuRegistry → the `moduleNav` prop (HandleInertiaRequests)
        // drives the sidebar; WidgetRegistry → the `moduleWidgets` prop
        // (DashboardController) drives the dashboard cards. The MenuRegistry share
        // stays for any remaining standalone Blade (module pages, PDFs).
        View::share('menuRegistry', $this->app->make(MenuRegistry::class));

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
            'de' => 'Deutsch',
            'vi' => 'Tiếng Việt',
        ];
    }
}
