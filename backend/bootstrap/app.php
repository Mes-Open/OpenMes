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
        $middleware->trustProxies(at: '*');
        $middleware->append(\App\Http\Middleware\CorrelationIdMiddleware::class);
        $middleware->web(append: [
            \App\Http\Middleware\SetLocaleMiddleware::class,
        ]);
        $middleware->append(\App\Http\Middleware\CheckInstallation::class);
        $middleware->validateCsrfTokens(except: [
            'install/*',
        ]);

        // Register Spatie Permission middleware
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $status = 500;
                $message = $e->getMessage() ?: __('Internal Server Error');
                $errors = null;

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = 422;
                    $message = __('Validation Failed');
                    $errors = $e->errors();
                } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $status = 401;
                    $message = __('Unauthenticated');
                } elseif ($e instanceof \Illuminate\Auth\Access\AuthorizationException || $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
                    $status = 403;
                    $message = __('This action is unauthorized.');
                } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    $status = 404;
                    $message = __('Resource not found.');
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                }

                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                    'errors' => $errors,
                    'locale' => app()->getLocale(),
                    'correlation_id' => $request->header('X-Correlation-ID'),
                ], $status);
            }
        });
    })->create();
