<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DynamicCors
{
    /** Cleared by Settings → System on save, so a change applies at once. */
    public const CACHE_KEY = 'cors_settings';

    /**
     * Only the API answers cross-origin requests — the same paths as
     * config/cors.php. The rest of the app is the signed-in UI, whose pages
     * (and the CSRF token in them) no other site may read, whatever the
     * allowlist says.
     */
    private const PATHS = ['api/*', 'sanctum/csrf-cookie'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is(...self::PATHS)) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');

        // Preflight: respond directly with full CORS headers instead of
        // letting Laravel's HandleCors short-circuit with no headers (its
        // config has allowed_origins=[] because this middleware is the
        // source of truth for the allowlist).
        $isPreflight = $request->getMethod() === 'OPTIONS'
            && $request->headers->has('Access-Control-Request-Method');

        if ($isPreflight) {
            $response = response('', 204);
            $this->applyCorsHeaders($response, $request, $origin, true);

            return $response;
        }

        $response = $next($request);

        if ($origin) {
            $this->applyCorsHeaders($response, $request, $origin, false);
        }

        return $response;
    }

    private function applyCorsHeaders(Response $response, Request $request, ?string $origin, bool $isPreflight): void
    {
        // `null` is what sandboxed iframes and local files send — any site can
        // produce it, so it is never an allowed origin, even if stored.
        if (! $origin || $origin === 'null') {
            return;
        }

        $corsSettings = Cache::remember(self::CACHE_KEY, 60, function () {
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
            return;
        }

        // Credentials only for named origins: browsers reject them next to the
        // `*` wildcard, which v0.4.1 (582c9376) already removed as risky.
        if ($allowedRaw === '*') {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $origins = array_filter(array_map('trim', explode(',', $allowedRaw)));

            // The answer depends on the Origin, so caches must key on it —
            // also when this origin is refused.
            $response->headers->set('Vary', 'Origin', false);

            if (! in_array($origin, $origins, true)) {
                return;
            }

            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($isPreflight) {
            $methods = $corsSettings['cors_allowed_methods'] ?? 'GET, POST';
            if (str_starts_with($methods, '"')) {
                $methods = json_decode($methods, true) ?? $methods;
            }

            $requestedHeaders = $request->headers->get('Access-Control-Request-Headers')
                ?: 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept';

            $response->headers->set('Access-Control-Allow-Methods', $methods);
            $response->headers->set('Access-Control-Allow-Headers', $requestedHeaders);

            $maxAge = (int) ($corsSettings['cors_max_age'] ?? 600);
            $response->headers->set('Access-Control-Max-Age', (string) ($maxAge > 0 ? $maxAge : 600));
        }
    }
}
