<?php

namespace App\Http\Middleware;

use App\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on an optional feature module being enabled (#144). When the
 * module is switched off for the installation the route 404s — the area behaves
 * as if it doesn't exist, matching TabAccessMiddleware's treatment of disabled
 * modules for admin pages. Used for API routes that carry no user/tab and so
 * aren't covered by TabAccessMiddleware (e.g. the machine-to-machine ERP API).
 *
 * Usage: `->middleware('module:erp')`.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(ModuleRegistry::isModuleEnabled($module), 404);

        return $next($request);
    }
}
