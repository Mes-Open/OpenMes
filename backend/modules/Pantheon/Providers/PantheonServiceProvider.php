<?php

namespace Modules\Pantheon\Providers;

use App\Services\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Pantheon\Console\Commands\PantheonSyncCommand;
use Modules\Pantheon\Services\PantheonSettings;

/**
 * Wires the Pantheon connector into OpenMES.
 *
 * Registered only while the module is ENABLED (ModuleManager::loadEnabled), so an
 * install without Pantheon carries no listener, no schedule entry and no menu
 * item — and core keeps working exactly as before.
 *
 * The connector deliberately touches no core file: all Pantheon knowledge (view
 * names, classification codes, document types, column mapping) lives in this
 * module, and it feeds OpenMES through the same import services the ERP REST
 * endpoints use.
 */
class PantheonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Deliberately NOT a singleton. Settings are per tenant: the sync command
        // walks the tenants that have a Pantheon row and resolves the settings again
        // inside each tenant's context, so a cached first-tenant instance would make
        // every later tenant talk to the wrong ERP with the wrong credentials.
        $this->app->bind(PantheonSettings::class, fn () => PantheonSettings::load());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../views', 'pantheon');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        // ModuleManager does not run module migrations; registering them here puts
        // them into the normal `php artisan migrate` run.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PantheonSyncCommand::class]);
        }

        $this->registerSchedule();
        $this->registerMenu();
    }

    /**
     * Master data nightly, the document backlog every 10 minutes.
     *
     * The scheduler already runs inside the primary container, so nothing has to
     * be configured on the host. Both entries are `withoutOverlapping` because a
     * slow PAWS instance must not stack runs on top of each other.
     */
    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('pantheon:sync --only=products')
                ->dailyAt('02:10')
                ->withoutOverlapping();

            $schedule->command('pantheon:sync --only=stock-documents')
                ->everyTenMinutes()
                ->withoutOverlapping();
        });
    }

    /** A status page under the Admin group, next to the other integrations. */
    private function registerMenu(): void
    {
        $this->callAfterResolving(MenuRegistry::class, function (MenuRegistry $menu) {
            $menu->addItem('admin', 'Pantheon', url('/modules/pantheon'));
        });
    }
}
