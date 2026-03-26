<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if session has a locale (set via UI switch)
        if ($request->hasSession() && $request->session()->has('locale')) {
            $sessionLocale = $request->session()->get('locale');
            if (in_array($sessionLocale, ['en', 'tr'])) {
                App::setLocale($sessionLocale);
                return $next($request);
            }
        }

        // 2. Fallback to Accept-Language header
        $localeHeader = $request->header('Accept-Language');

        // Simple parsing for first language code (e.g. 'en-US,en;q=0.9' -> 'en')
        if ($localeHeader) {
            $parts = explode(',', $localeHeader);
            $primary = explode(';', $parts[0])[0];
            $lang = substr($primary, 0, 2);

            if (in_array($lang, ['en', 'tr'])) {
                App::setLocale($lang);
            }
        }

        return $next($request);
    }
}
