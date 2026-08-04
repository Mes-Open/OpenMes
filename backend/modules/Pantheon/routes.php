<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    Route::get('/modules/pantheon', function (PantheonSettings $settings, Request $request) {
        $tenantId = $request->user()?->tenant_id;

        return view('pantheon::status', [
            // Resolved per request, so an admin sees their own tenant's connection
            // (PantheonSettings is bound non-singleton and reads a tenant-scoped row).
            'settings' => $settings,
            // A plain query builder has no global scope, so the tenant filter is
            // explicit — otherwise one customer's admin would read another's runs.
            'runs' => DB::table('pantheon_sync_runs')
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
        ]);
    })->name('pantheon.status');
});
