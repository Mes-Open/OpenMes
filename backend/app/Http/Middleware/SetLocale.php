<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the user's chosen UI locale (persisted in the session by the
 * /locale/{locale} route) for the request. Falls back to the configured default
 * when unset or invalid. Runs in the web group after StartSession.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale'));

        if (array_key_exists($locale, config('app.available_locales', []))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
