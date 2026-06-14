<?php

namespace App\Http\Middleware;

use App\Support\TabRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the admin panel by the role × tab access matrix. Replaces the blanket
 * `role:Admin` on the /admin group: the request path is resolved to a tab and
 * the user must hold that tab's permission (tab:<key>). Admins always pass via
 * the tab:* Gate::before. Admin paths outside the matrix stay Admin-only.
 *
 * Authentication is guaranteed by the enclosing `auth` group.
 */
class TabAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $tab = TabRegistry::tabForPath($request->path());

        if ($tab === null) {
            // An admin route not covered by any matrix tab — keep it Admin-only.
            abort_unless($user->hasRole('Admin'), 403);

            return $next($request);
        }

        abort_unless($user->can(TabRegistry::permission($tab)), 403);

        return $next($request);
    }
}
