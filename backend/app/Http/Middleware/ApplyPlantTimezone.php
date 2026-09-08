<?php

namespace App\Http\Middleware;

use App\Support\TimezoneRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-applies the plant timezone (system_settings → config('app.timezone') +
 * date_default_timezone_set) for every request.
 *
 * AppServiceProvider::boot() already applies it, but under Octane that runs once
 * per worker: without this, changing the zone in Settings would only reach the
 * worker that handled the save, and every other worker would keep rendering
 * timestamps in the old zone until the container restarted. Runs before the
 * controller — and before HandleInertiaRequests reads config('app.timezone') for
 * the frontend `timezone` prop.
 */
class ApplyPlantTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        TimezoneRegistry::refresh();

        return $next($request);
    }
}
