<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequest
{
    /**
     * Path prefixes always skipped: poll endpoints, assets, dev tools.
     */
    private const SKIP_PREFIXES = [
        'livewire/',           // Livewire heartbeats
        'build/',              // Vite assets
        '_debugbar/',          // dev tool
        '_ignition/',          // dev tool
        'admin/update/status', // updater banner polling — very frequent
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);

        try {
            $this->log($request, $response, $start);
        } catch (\Throwable $e) {
            // Fail-safe: never break the request because of a logging issue.
            Log::warning('RequestLog write failed: '.$e->getMessage());
        }

        return $response;
    }

    private function log(Request $request, Response $response, float $start): void
    {
        if (! $request->user()) {
            return; // do not log anonymous traffic
        }

        $path = ltrim($request->path(), '/');
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $method = $request->method();
        $isMutating = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $sampled = false;

        if (! $isMutating) {
            // GET / HEAD / OPTIONS: 1/10 sampling
            if (random_int(1, 10) !== 1) {
                return;
            }
            $sampled = true;
        }

        RequestLog::create([
            'user_id'     => $request->user()->id,
            'method'      => $method,
            'path'        => '/'.$path,
            'route_name'  => optional($request->route())->getName(),
            'status'      => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            'ip_address'  => $request->ip(),
            'user_agent'  => substr($request->userAgent() ?? '', 0, 500),
            'sampled'     => $sampled,
        ]);
    }
}
