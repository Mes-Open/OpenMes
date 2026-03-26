<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Get correlation ID from request header or generate a new one
        $correlationId = $request->header('X-Correlation-ID') ?? (string) Str::uuid();

        // 2. Set the correlation ID in the request header so it's available globally via request()
        $request->headers->set('X-Correlation-ID', $correlationId);

        // 3. Continue processing the request
        $response = $next($request);

        // 4. Attach the correlation ID to the response header
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
