<?php

namespace App\Http\Middleware;

use App\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a route whose feature module is switched off for this installation.
 *
 * `TabAccessMiddleware` already does this for `/admin`, but it does it as a side
 * effect of resolving a tab — so the `/supervisor` tree, which is gated by role
 * instead of by tab, inherited no module check at all and kept serving screens
 * an admin had turned off in Settings → Modules. The module question is
 * install-wide and has nothing to do with roles, so it belongs in its own gate
 * that either section can apply.
 *
 * 404, not 403, to match the admin behaviour: a disabled area should look like
 * it doesn't exist, not like something being withheld.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(ModuleRegistry::isModuleEnabled($module), 404);

        return $next($request);
    }
}
