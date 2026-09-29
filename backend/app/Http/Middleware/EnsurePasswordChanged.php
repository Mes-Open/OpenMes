<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a user who owes a password change on the page that collects it.
 *
 * The forced change used to be enforced once, by a redirect at the end of
 * login. That is not enforcement: by then the session is fully authenticated,
 * so typing any other address walked straight past it. An administrator who
 * ticked "require password change" after a suspected compromise got a control
 * that looked like it worked and did not.
 *
 * Checking on every request is the only version of this that means anything.
 *
 * Reported privately by Maxwell Jones, September 2026.
 */
class EnsurePasswordChanged
{
    /**
     * Routes the user must still reach while they owe a change: the form
     * itself, the endpoint it posts to, and the way out. Logout in particular —
     * trapping someone in a page they cannot leave or escape is not security,
     * it is a lockout.
     */
    private const ALLOWED = [
        'settings/change-password',
        'change-password',
        'logout',
        'api/auth/change-password',
        'api/auth/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->force_password_change) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        $message = __('You must change your password before continuing.');

        // API clients get a machine-readable refusal rather than a redirect to
        // an HTML form they cannot render. 403 with a flag the client can key
        // off: the credentials are valid, the account is simply not permitted
        // to act until the password is changed.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'force_password_change' => true,
            ], 403);
        }

        return redirect()->route('settings.change-password')->with('error', $message);
    }

    private function isAllowed(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach (self::ALLOWED as $allowed) {
            if ($path === $allowed) {
                return true;
            }
        }

        return false;
    }
}
