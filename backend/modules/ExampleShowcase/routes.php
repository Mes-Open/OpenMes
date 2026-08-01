<?php

use Illuminate\Support\Facades\Route;

/*
 * Module routes. Loaded from the provider's boot() via loadRoutesFrom(), so they
 * only exist while the module is enabled. Kept in the `web` + `auth` groups so
 * the page has a session and requires a logged-in user — no core route file is
 * touched. The sidebar menu items registered in the provider point here.
 *
 * Route caching: loadRoutesFrom is skipped when routes are cached, so after
 * enabling the module on a production install run `php artisan route:cache`.
 */
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/modules/example-showcase', fn () => view('example-showcase::index'))
        ->name('example-showcase.index');
});
