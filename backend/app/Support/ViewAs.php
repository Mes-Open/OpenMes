<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which role's interface an administrator is currently looking at.
 *
 * Presentation only: the account, its roles and every permission check stay the
 * administrator's. The choice lives in the session (never the URL, so a shared
 * link cannot switch someone else's view) and is read fresh on every request —
 * nothing here is static, because Octane keeps the process alive between
 * requests and a static value would leak to the next user.
 *
 * A stored choice counts only while the user still holds the Admin role, so
 * taking the role away ends the preview on the next request.
 */
final class ViewAs
{
    public const SESSION_KEY = 'view_as';

    public const ADMIN = 'admin';

    public const SUPERVISOR = 'supervisor';

    public const OPERATOR = 'operator';

    public const PLANNER = 'planner';

    public const PURCHASING = 'purchasing';

    public const QUALITY = 'quality';

    public const PRODUCTION = 'production';

    public const WAREHOUSE = 'warehouse';

    public const VIEWS = [
        self::ADMIN, self::PLANNER, self::PURCHASING, self::QUALITY,
        self::PRODUCTION, self::WAREHOUSE, self::SUPERVISOR, self::OPERATOR,
    ];

    /** Functional views mapped to the role whose tab grants drive the preview. */
    private const ROLE_BY_VIEW = [
        self::PLANNER => 'Planner',
        self::PURCHASING => 'Purchasing',
        self::QUALITY => 'QA',
        self::PRODUCTION => 'Production',
        self::WAREHOUSE => 'Warehouse',
    ];

    /** May this user switch views at all? Server-side: the bar is never sent otherwise. */
    public static function available(?object $user): bool
    {
        return $user !== null && method_exists($user, 'hasRole') && $user->hasRole('Admin');
    }

    /** The active view for this request, or null when the user may not switch. */
    public static function current(Request $request): ?string
    {
        if (! self::available($request->user())) {
            return null;
        }

        $view = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return in_array($view, self::VIEWS, true) ? $view : self::ADMIN;
    }

    /**
     * Admin-tab set for a functional role preview. Null means the caller should
     * keep using the signed-in administrator's tabs (admin/supervisor/operator).
     *
     * @return array<int, string>|null
     */
    public static function tabsForCurrent(Request $request): ?array
    {
        $view = self::current($request);
        $role = $view !== null ? (self::ROLE_BY_VIEW[$view] ?? null) : null;

        return $role !== null ? TabRegistry::accessibleForRole($role) : null;
    }

    /** Where switching to a view takes the administrator. */
    public static function landingRoute(string $view): string
    {
        return match ($view) {
            self::SUPERVISOR => 'supervisor.dashboard',
            self::OPERATOR => 'operator.select-line',
            default => 'admin.dashboard',
        };
    }
}
