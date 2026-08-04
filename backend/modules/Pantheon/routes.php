<?php

use Illuminate\Support\Facades\Route;
use Modules\Pantheon\Services\PantheonSettings;

/*
 * Module routes, loaded from the provider while the module is enabled. Admin-only:
 * the page shows whether the connector can reach Pantheon and what the last sync
 * did, which is operational information, not shop-floor information.
 *
 * Route caching skips loadRoutesFrom, so after enabling the module on a production
 * install run `php artisan route:cache`.
 */
Route::middleware(['web', 'auth', 'role:Admin'])->group(function () {
    Route::get('/modules/pantheon', function (PantheonSettings $settings) {
        return view('pantheon::status', [
            'settings' => $settings,
            'runs' => \Illuminate\Support\Facades\DB::table('pantheon_sync_runs')
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
        ]);
    })->name('pantheon.status');
});
