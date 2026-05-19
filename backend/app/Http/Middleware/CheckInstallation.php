<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInstallation
{
    /**
     * Block access to install routes if the application is already installed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (file_exists(storage_path('installed'))) {
            if ($request->expectsJson()) {
                abort(403, 'Application is already installed.');
            }

            return redirect('/');
        }

        return $next($request);
    }
}
