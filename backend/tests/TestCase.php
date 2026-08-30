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
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Spatie caches the role/permission map outside the database, so it does
        // not roll back with the test transaction: a grant made by one test stays
        // visible to every test that runs after it in the same process, and the
        // failure surfaces in whichever unrelated test happens to run next.
        // Start each test from the database instead.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
