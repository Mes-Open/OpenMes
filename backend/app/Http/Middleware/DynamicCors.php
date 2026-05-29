<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DynamicCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $origin = $request->headers->get('Origin');

        if (! $origin) {
            return $response;
        }

        $corsSettings = Cache::remember('cors_settings', 60, function () {
            return DB::table('system_settings')
                ->whereIn('key', ['cors_allowed_origins', 'cors_allowed_methods', 'cors_max_age'])
                ->pluck('value', 'key')
                ->toArray();
        });

        $allowedRaw = $corsSettings['cors_allowed_origins'] ?? '';
        // Strip JSON encoding if stored as JSON string
        if (str_starts_with($allowedRaw, '"')) {
            $allowedRaw = json_decode($allowedRaw, true) ?? $allowedRaw;
        }

        // Empty = block all cross-origin requests (most secure default)
        if (empty($allowedRaw) || $allowedRaw === '""') {
            return $response;
        }

        if ($allowedRaw === '*') {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $origins = array_map('trim', explode(',', $allowedRaw));
            $origins = array_filter($origins);

            if (in_array($origin, $origins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Vary', 'Origin');
            } else {
                return $response; // Origin not allowed — no CORS headers
            }
        }

        $methods = $corsSettings['cors_allowed_methods'] ?? 'GET, POST';
        if (str_starts_with($methods, '"')) {
            $methods = json_decode($methods, true) ?? $methods;
        }
        $response->headers->set('Access-Control-Allow-Methods', $methods);
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        $maxAge = (int) ($corsSettings['cors_max_age'] ?? 0);
        if ($maxAge > 0) {
            $response->headers->set('Access-Control-Max-Age', (string) $maxAge);
        }

        return $response;
    }
}
