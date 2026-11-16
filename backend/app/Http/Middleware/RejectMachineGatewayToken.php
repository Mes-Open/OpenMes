<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ApiTokenScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Contracts\HasAbilities;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps machine-gateway tokens on the gateway routes.
 *
 * Sanctum's `auth:sanctum` admits any valid token to any route, so a token
 * minted for the OPC UA sidecar would otherwise act as the admin who created
 * it everywhere else in the API — including /auth/refresh, which would trade
 * it for a full `*` token.
 *
 * Fails closed: it runs on the whole `api` middleware group (core and module
 * route files alike) and lets a gateway token through only on the routes named
 * `api.machine-gateway.*`. Session (cookie) auth and full tokens pass anywhere.
 */
class RejectMachineGatewayToken
{
    public const GATEWAY_ROUTES = 'api.machine-gateway.*';

    public function handle(Request $request, Closure $next): Response
    {
        // Only a bearer token can be a gateway token — skip the lookup otherwise.
        if ($request->bearerToken() === null || $request->route()?->named(self::GATEWAY_ROUTES)) {
            return $next($request);
        }

        $token = Auth::guard('sanctum')->user()?->currentAccessToken();

        // Asked through can() rather than by reading `abilities`, so a session
        // (TransientToken) and a full `*` token both answer "not confined".
        if ($token instanceof HasAbilities
            && $token->can(ApiTokenScope::GATEWAY_ABILITY)
            && ! $token->can('*')) {
            return response()->json([
                'message' => __('This token can only be used by a machine gateway.'),
            ], 403);
        }

        return $next($request);
    }
}
