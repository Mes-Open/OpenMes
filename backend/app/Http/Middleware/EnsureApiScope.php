<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorizes an API-key request against a required scope. Runs after
 * AuthenticateApiKey, which attaches the resolved key. Usage: `scope:erp:orders:import`.
 */
class EnsureApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $key = $request->attributes->get('api_key');

        if (! $key instanceof ApiKey) {
            return response()->json(['message' => __('API key missing.')], 401);
        }

        if (! $key->hasScope($scope)) {
            return response()->json([
                'message' => __('This API key is not authorized for :scope.', ['scope' => $scope]),
            ], 403);
        }

        return $next($request);
    }
}
