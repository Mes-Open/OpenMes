<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Env;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        // Sanctum SPA mode: same-origin requests carrying the session cookie are
        // treated as stateful, so api routes guarded by `auth:web,sanctum`
        // authenticate via the browser session — no bearer token needed. Mobile
        // clients still authenticate the same routes with Sanctum tokens.
        $middleware->statefulApi();
        // CheckInstallation is applied per-route on install/* routes only (see routes/web.php)
        // Prepend (not append) so DynamicCors runs before Laravel's built-in
        // HandleCors. HandleCors short-circuits preflight OPTIONS responses
        // when config/cors.allowed_origins is empty and skips later middleware,
        // so DynamicCors never sees them. Running first lets us own preflight.
        $middleware->prepend(\App\Http\Middleware\DynamicCors::class);
        $middleware->validateCsrfTokens(except: [
            'install/*',
            'broadcasting/auth',
        ]);

        // Append request logging at the end of the web stack so $request->user()
        // is populated by SubstituteBindings/StartSession/Authenticate before us.
        $middleware->web(append: [
            // SetLocale first so app()->getLocale() is correct by the time
            // HandleInertiaRequests::share() reads it.
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\LogRequest::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
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
            'tab.access' => \App\Http\Middleware\TabAccessMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->route('login')->withErrors(['session' => 'Your session has expired. Please log in again.']);
        });

        // Render a friendly Inertia "Error" page for error statuses in
        // production, so the app chrome (sidebar) stays put and the user can
        // navigate away instead of landing on a bare error screen. API/JSON
        // clients keep their normal JSON error; local/testing keep the debug
        // page and untouched test responses.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            if (app()->environment(['local', 'testing'])) {
                return $response;
            }

            if ($request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                return $response;
            }

            if (in_array($response->getStatusCode(), [500, 503, 404, 403, 429], true)) {
                // Pin the root view (a routing 404 fires before the Inertia
                // middleware sets it) and (re)share auth/nav so the Error page
                // keeps the user's sidebar. The share touches the session, which
                // exists for in-route errors but not a bare routing 404 — there
                // we skip it and render the page standalone.
                \Inertia\Inertia::setRootView('inertia');
                if ($request->hasSession() && $request->session()->isStarted()) {
                    \Inertia\Inertia::share(
                        app(\App\Http\Middleware\HandleInertiaRequests::class)->share($request)
                    );
                }

                return \Inertia\Inertia::render('Error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();

// Honor LARAVEL_STORAGE_PATH also under `php -S` (artisan serve): its request
// workers expose process env only via getenv() — $_ENV/$_SERVER stay empty —
// so the framework's built-in override never fires there. Env::get() reads
// getenv-backed adapters. Used by desktop/unattended installs to keep all
// runtime state (logs, sessions, the `installed` marker) out of the codebase.
if (($storagePath = Env::get('LARAVEL_STORAGE_PATH')) && is_string($storagePath)) {
    $app->useStoragePath($storagePath);
}

return $app;
