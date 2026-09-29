<?php

namespace Tests\Feature;

use App\Support\ModuleProviderGuard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Tests\TestCase;

/**
 * A module's provider must not be able to take the application down with it.
 *
 * The case this comes from: a module built against a newer core called a menu
 * helper with an argument this version does not accept. The throw happened in
 * boot(), which Laravel calls while booting every provider — outside the
 * try/catch in ModuleManager::loadEnabled() — so every route answered 502,
 * including the admin screen needed to turn the module off again.
 */
class ModuleProviderGuardTest extends TestCase
{
    public function test_an_exception_from_register_does_not_escape(): void
    {
        Log::spy();

        $guard = new ModuleProviderGuard($this->app, new ThrowsOnRegisterProvider($this->app), 'Broken');

        $guard->register();

        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context) => $message === 'module.provider_register_failed'
                && $context['module'] === 'Broken',
        );
    }

    public function test_an_exception_from_boot_does_not_escape(): void
    {
        Log::spy();

        $guard = new ModuleProviderGuard($this->app, new ThrowsOnBootProvider($this->app), 'Broken');

        $guard->boot();

        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context) => $message === 'module.provider_boot_failed'
                && $context['module'] === 'Broken',
        );
    }

    public function test_a_working_provider_still_registers_and_boots(): void
    {
        $inner = new WorkingProvider($this->app);
        $guard = new ModuleProviderGuard($this->app, $inner, 'Working');

        $guard->register();
        $guard->boot();

        $this->assertTrue($inner->registered, 'register() was not passed through');
        $this->assertTrue($inner->booted, 'boot() was not passed through');
    }

    /**
     * The container deduplicates providers by class name. Every module's guard
     * is the same class, so without force the second module would be treated as
     * already registered and never load at all.
     */
    public function test_two_modules_both_load_despite_sharing_a_guard_class(): void
    {
        $first = new WorkingProvider($this->app);
        $second = new WorkingProvider($this->app);

        $this->app->register(new ModuleProviderGuard($this->app, $first, 'First'), force: true);
        $this->app->register(new ModuleProviderGuard($this->app, $second, 'Second'), force: true);

        $this->assertTrue($first->registered, 'first module did not register');
        $this->assertTrue($second->registered, 'second module was swallowed by provider deduplication');
    }

    /** A provider with no boot() at all is common and must not error. */
    public function test_a_provider_without_boot_is_fine(): void
    {
        $inner = new RegisterOnlyProvider($this->app);
        $guard = new ModuleProviderGuard($this->app, $inner, 'NoBoot');

        $guard->register();
        $guard->boot();

        $this->assertTrue($inner->registered);
    }
}

class RegisterOnlyProvider extends ServiceProvider
{
    public bool $registered = false;

    public function register(): void
    {
        $this->registered = true;
    }
}

class ThrowsOnRegisterProvider extends ServiceProvider
{
    public function register(): void
    {
        throw new \RuntimeException('register blew up');
    }
}

class ThrowsOnBootProvider extends ServiceProvider
{
    public function boot(): void
    {
        throw new \ArgumentCountError('Unknown named parameter $badge');
    }
}

class WorkingProvider extends ServiceProvider
{
    public bool $registered = false;

    public bool $booted = false;

    public function register(): void
    {
        $this->registered = true;
    }

    public function boot(): void
    {
        $this->booted = true;
    }
}
