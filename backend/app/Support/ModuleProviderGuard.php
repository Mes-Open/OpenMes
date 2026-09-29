<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Wraps an installed module's service provider so a failure inside it costs
 * that module, not the whole application.
 *
 * ModuleManager::loadEnabled() already ran `$app->register()` inside a
 * try/catch, and the comment there promised "a bad module never prevents the
 * application from booting". It only half held: `register()` was covered, but
 * Laravel calls a provider's `boot()` later, while booting every provider, and
 * an exception thrown there escapes that catch entirely. A module built
 * against a newer core — calling a menu helper with an argument this version
 * does not accept, say — took the entire installation down with a 502, on
 * every route, including the admin screen needed to disable it again.
 *
 * Both phases run here instead, each guarded. A module that throws simply does
 * not register its routes, menus or listeners; every other page keeps working
 * and the reason is in the log rather than in an nginx error page.
 */
class ModuleProviderGuard extends ServiceProvider
{
    public function __construct(
        Application $app,
        private readonly ServiceProvider $inner,
        private readonly string $module,
    ) {
        parent::__construct($app);
    }

    public function register(): void
    {
        $this->guard('register');
    }

    public function boot(): void
    {
        $this->guard('boot');
    }

    /**
     * Run one phase of the wrapped provider, swallowing anything it throws.
     *
     * `boot()` is resolved through the container because a provider may type-hint
     * its dependencies there, which is how Laravel calls it too. `register()` is
     * always defined by the base class; `boot()` is not, so it is checked.
     */
    private function guard(string $phase): void
    {
        if (! method_exists($this->inner, $phase)) {
            return;
        }

        try {
            $this->app->call([$this->inner, $phase]);
        } catch (\Throwable $e) {
            Log::error("module.provider_{$phase}_failed", [
                'module' => $this->module,
                'provider' => $this->inner::class,
                'exception' => $e->getMessage(),
            ]);

            // Also to the app's error reporting, so this is visible wherever
            // exceptions normally are and not only to whoever tails the log.
            report($e);
        }
    }
}
