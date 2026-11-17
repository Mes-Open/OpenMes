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
 * Keeps read-only tokens to reading.
 *
 * A read-only token acts as the user who created it, so without this check it
 * could do everything that user can. The rule is by HTTP method rather than per
 * route: only GET, HEAD and OPTIONS pass, so a route added later (core or
 * module) is covered without anyone remembering to tag it — fail closed.
 *
 * Session (cookie) auth and full `*` tokens are not affected.
 */
class RejectWritesWithReadOnlyToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() === null || $request->isMethodSafe()) {
            return $next($request);
        }

        $token = Auth::guard('sanctum')->user()?->currentAccessToken();

        if ($token instanceof HasAbilities
            && $token->can(ApiTokenScope::READ_ABILITY)
            && ! $token->can('*')) {
            return response()->json([
                'message' => __('This token is read-only.'),
            ], 403);
        }

        return $next($request);
    }
}
