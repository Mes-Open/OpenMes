<?php

namespace App\Http\Middleware;

use App\Models\DeviceToken;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a self-enrolled HTTP sensor by its device token. The token
 * travels as `Authorization: Bearer omd_…` or in the `X-Device-Token` header.
 * On success the resolved DeviceToken and its MachineConnection are attached to
 * the request, and the request-scoped TenantContext is set so tenant-scoped
 * models read/write within the device's tenant even though there is no user.
 * Mirrors AuthenticateApiKey.
 */
class AuthenticateDeviceToken
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $this->extractToken($request);

        if ($plaintext === null) {
            return $this->unauthorized(__('Device token missing.'));
        }

        $token = DeviceToken::findByPlaintext($plaintext);

        if ($token === null || ! $token->isUsable()) {
            return $this->unauthorized(__('Invalid or revoked device token.'));
        }

        $connection = $token->machineConnection;

        if ($connection === null || ! $connection->is_active) {
            return $this->unauthorized(__('Invalid or revoked device token.'));
        }

        // Establish tenant scope for this headless request before any query runs.
        $this->tenant->set($token->tenant_id);

        $request->attributes->set('device_token', $token);
        $request->attributes->set('device_connection', $connection);
        $token->markUsed();

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

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('X-Device-Token');

        if (is_string($header) && $header !== '') {
            return trim($header);
        }

        $bearer = $request->bearerToken();

        return $bearer !== null && str_starts_with($bearer, DeviceToken::PREFIX) ? $bearer : null;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 401);
    }
}
