<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates ERP integration requests by API key. The key travels in the
 * `X-Api-Key` header, or as `Authorization: Bearer <key>`. On success the
 * resolved ApiKey is attached to the request (`api_key` attribute) for the
 * scope middleware and controllers, and the request-scoped TenantContext is
 * set so tenant-scoped models read/write within the key's tenant even though
 * there is no logged-in user.
 */
class AuthenticateApiKey
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $this->extractKey($request);

        if ($plaintext === null) {
            return $this->unauthorized(__('API key missing.'));
        }

        $key = ApiKey::findByPlaintext($plaintext);

        if ($key === null || ! $key->isUsable()) {
            return $this->unauthorized(__('Invalid or expired API key.'));
        }

        if (! $key->ipAllowed($request->ip())) {
            return $this->unauthorized(__('Source IP not allowed for this API key.'));
        }

        // Establish tenant scope for this headless request before any query runs.
        $this->tenant->set($key->tenant_id);

        $request->attributes->set('api_key', $key);
        $key->markUsed();

        return $next($request);
    }

    /**
     * Clear the tenant context after the response is sent so a reused Octane
     * worker never carries one request's tenant into the next.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->tenant->clear();
    }

    private function extractKey(Request $request): ?string
    {
        $header = $request->header('X-Api-Key');

        if (is_string($header) && $header !== '') {
            return trim($header);
        }

        $bearer = $request->bearerToken();

        return $bearer !== null && str_starts_with($bearer, ApiKey::PREFIX) ? $bearer : null;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 401);
    }
}
