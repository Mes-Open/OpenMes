<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Disable CSRF verification for all web form tests.
     * API tests use JSON requests which bypass CSRF automatically.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // A stale bootstrap/cache/config.php makes Laravel skip .env entirely
        // and bake every env() value, so phpunit.xml's <env> block and any
        // DB_* override on the command line are silently ignored — the suite
        // then runs against whatever database the cache was built with, which
        // on a dev box is the developer's own. RefreshDatabase wraps each test
        // in a transaction so nothing is lost, but nothing about it is visible
        // either. Fail loudly and name the fix.
        if (! app()->environment('testing')) {
            $this->fail(sprintf(
                'Tests booted with APP_ENV=%s (database "%s"), not "testing" — '
                .'bootstrap/cache/config.php is stale. Run: php artisan config:clear',
                app()->environment(),
                config('database.connections.'.config('database.default').'.database'),
            ));
        }

        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Spatie caches the role/permission map outside the database, so it does
        // not roll back with the test transaction: a grant made by one test stays
        // visible to every test that runs after it in the same process, and the
        // failure surfaces in whichever unrelated test happens to run next.
        // Start each test from the database instead.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
