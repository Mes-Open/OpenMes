<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
        // CheckInstallation is applied per-route on install/* routes only (see routes/web.php)
        $middleware->append(\App\Http\Middleware\DynamicCors::class);
        $middleware->validateCsrfTokens(except: [
            'install/*',
        ]);

        // Append request logging at the end of the web stack so $request->user()
        // is populated by SubstituteBindings/StartSession/Authenticate before us.
        $middleware->web(append: [
            \App\Http\Middleware\LogRequest::class,
        ]);

        // Also log API requests; the middleware resolves the user from the
        // sanctum guard when the default web guard isn't populated.
        $middleware->api(append: [
            \App\Http\Middleware\LogRequest::class,
        ]);

        // Register Spatie Permission middleware
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->route('login')->withErrors(['session' => 'Your session has expired. Please log in again.']);
        });
    })->create();
