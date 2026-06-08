<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the user's chosen UI locale (persisted in the session by the
 * /locale/{locale} route) for the request. When the session has no override it
 * keeps the locale already set by AppServiceProvider from the system_settings
 * 'language' default — so the global language chosen in Settings actually takes
 * effect, instead of being reset to config('app.locale'). Runs in the web group
 * after StartSession.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', app()->getLocale());

        if (array_key_exists($locale, config('app.available_locales', []))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
