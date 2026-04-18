<?php

namespace Modules\Packaging\Providers;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Packaging\Commands\ResetPackagingShiftCommand;
use Modules\Packaging\Controllers\PackagingController;
use Modules\Packaging\Controllers\PackagingEanController;
use Modules\Packaging\Models\WorkOrderEan;

class PackagingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // ── Views ──────────────────────────────────────────────────────────────
        $this->loadViewsFrom(__DIR__ . '/../views', 'packaging');

        // ── Migrations ─────────────────────────────────────────────────────────
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');

        // ── Eloquent relation on WorkOrder ─────────────────────────────────────
        WorkOrder::resolveRelationUsing('eans', function (WorkOrder $model) {
            return $model->hasMany(WorkOrderEan::class);
        });

        // ── Commands ───────────────────────────────────────────────────────────
        if ($this->app->runningInConsole()) {
            $this->commands([ResetPackagingShiftCommand::class]);
        }

        // ── Routes ─────────────────────────────────────────────────────────────
        Route::middleware(['web', 'auth'])
            ->name('packaging.')
            ->prefix('packaging')
            ->group(function () {
                // Scanning station (Operator + Supervisor + Admin)
                Route::middleware('role:Operator|Supervisor|Admin')->group(function () {
                    Route::get('/station', [PackagingController::class, 'station'])->name('station');
                    Route::post('/scan', [PackagingController::class, 'scan'])->name('scan');
                    Route::get('/items', [PackagingController::class, 'items'])->name('items');
                    Route::get('/history', [PackagingController::class, 'history'])->name('history');
                    Route::get('/history/poll', [PackagingController::class, 'historyAfter'])->name('history.poll');
                    Route::get('/stats', [PackagingController::class, 'stats'])->name('stats');
                });

                // Admin/Supervisor overview and EAN management
                Route::middleware('role:Supervisor|Admin')->group(function () {
                    Route::get('/', [PackagingController::class, 'adminOverview'])->name('overview');
                    Route::get('/eans', [PackagingEanController::class, 'index'])->name('eans.index');
                    Route::post('/eans', [PackagingEanController::class, 'store'])->name('eans.store');
                    Route::delete('/eans/{ean}', [PackagingEanController::class, 'destroy'])->name('eans.destroy');
                });
            });

        // ── Navigation menu ────────────────────────────────────────────────────
        $menu = app(\App\Services\MenuRegistry::class);
        $menu->addGroup('packaging', 'Pakowanie', order: 40);
        $menu->addGroupItem('packaging', 'Stanowisko skanowania', '/packaging/station', order: 10);

        if (auth()->check() && auth()->user()->hasAnyRole(['Admin', 'Supervisor'])) {
            $menu->addGroupItem('packaging', 'Przegląd pakowania', '/packaging', order: 20);
            $menu->addGroupItem('packaging', 'Zarządzanie EAN', '/packaging/eans', order: 30);
        }
    }
}
