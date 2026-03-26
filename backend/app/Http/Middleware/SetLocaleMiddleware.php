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
        $locale = $request->header('Accept-Language');

        // Simple parsing for first language code (e.g. 'en-US,en;q=0.9' -> 'en')
        if ($locale) {
            $parts = explode(',', $locale);
            $primary = explode(';', $parts[0])[0];
            $lang = substr($primary, 0, 2);

            if (in_array($lang, ['en', 'tr'])) {
                App::setLocale($lang);
            }
        }

        return $next($request);
    }
}
