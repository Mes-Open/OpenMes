<?php

namespace Tests\Support;

use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Base for tests of an installed module under backend/modules/.
 *
 * The suite's migrate:fresh runs once per process, before any module provider
 * is registered, so a module's tables are never part of that schema. Each test
 * here registers the provider on its fresh application and, inside the test's
 * own transaction, migrates the module's directory if its tables are missing —
 * rolled back with the rest of the test, so the shared in-memory schema stays
 * exactly what core tests expect.
 */
abstract class ModuleTestCase extends TestCase
{
    use RefreshDatabase;

    /** Directory name under backend/modules/. */
    protected string $module;

    /** Provider class, as module.json declares it. */
    protected string $provider;

    /** A table the module's migrations create — the "already migrated" probe. */
    protected string $probeTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register($this->provider);
        app('router')->getRoutes()->refreshNameLookups();

        // Console commands a provider registers arrive through an Artisan
        // "starting" callback; the test kernel already built its Artisan
        // instance, so drop it and the next call rebuilds it with the module's.
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
    }

    protected function afterRefreshingDatabase(): void
    {
        if (Schema::hasTable($this->probeTable)) {
            return;
        }

        $path = app(ModuleManager::class)->migrationsPath($this->module);

        Artisan::call('migrate', ['--path' => $path, '--realpath' => true, '--force' => true]);
    }
}
