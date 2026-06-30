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

        // A feature module switched off for this installation (#144) is gone —
        // 404 so a deep link behaves like the area doesn't exist, not "forbidden".
        abort_unless(\App\Support\ModuleRegistry::isTabEnabled($tab), 404);

        if (! $user->can(TabRegistry::permission($tab))) {
            // The dashboard is the admin panel's implicit home. Bouncing a user
            // who CAN open other tabs with a hard 403 there is a dead end (the
            // field 403 on /admin/dashboard). For that one case, on a normal
            // navigation, send them to the first tab they can open instead.
            // Every specific resource tab keeps a real 403 (the access-matrix
            // contract) — and so does any API / non-GET request.
            if ($tab === 'dashboard' && $request->isMethod('GET') && ! $request->expectsJson()) {
                $landing = TabRegistry::firstAccessibleUrl($user);
                if ($landing && trim($landing, '/') !== trim($request->path(), '/')) {
                    return redirect($landing);
                }
            }

            abort(403);
        }

        return $next($request);
    }
}
