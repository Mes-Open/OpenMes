<?php

namespace Modules\SubiektNexo\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\SubiektNexo\Controllers\Api\SubiektNexoApiController;
use Modules\SubiektNexo\Services\ProductSyncService;
use Modules\SubiektNexo\Services\SferaClient;

class SubiektNexoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SferaClient::class);
        $this->app->singleton(ProductSyncService::class);
    }

    public function boot(): void
    {
        // Views
        $this->loadViewsFrom(__DIR__.'/../views', 'subiekt-nexo');

        // API Routes
        Route::prefix('api/v1/subiekt')
            ->middleware(['api', 'auth:sanctum'])
            ->group(function () {
                Route::get('/status', [SubiektNexoApiController::class, 'status']);
                Route::post('/connect', [SubiektNexoApiController::class, 'connect']);
                Route::get('/products', [SubiektNexoApiController::class, 'products']);
                Route::get('/products/{symbol}', [SubiektNexoApiController::class, 'product']);
                Route::get('/contractors', [SubiektNexoApiController::class, 'contractors']);

                // Admin-only sync operations
                Route::middleware('role:Admin')->group(function () {
                    Route::post('/sync', [SubiektNexoApiController::class, 'sync']);
                    Route::post('/sync/{symbol}', [SubiektNexoApiController::class, 'syncProduct']);
                });
            });

        // Web Admin Routes
        Route::prefix('admin/subiekt')
            ->middleware(['web', 'auth', 'role:Admin'])
            ->name('admin.subiekt.')
            ->group(function () {
                Route::get('/', [SubiektNexoApiController::class, 'status'])->name('index');
            });
    }
}
