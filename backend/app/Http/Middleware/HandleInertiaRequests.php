<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'inertia';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only('id', 'name', 'username', 'email', 'tenant_id'),
                    'roles' => $user->getRoleNames(),
                    'initial' => mb_strtoupper(mb_substr($user->name, 0, 1)),
                    // Admin-panel tabs this user may access — drives nav filtering.
                    // Backend enforcement is in TabAccessMiddleware; this is UX only.
                    'accessibleTabs' => $this->accessibleTabs($user),
                    // Operators get the line-selection workflow; the admin sidebar
                    // lists "Lines" first for them.
                    'isOperator' => $user->hasRole('Operator') || $user->account_type === 'workstation',
                    // Granted admin tabs as {key,label,url} — drives the operator
                    // screen's sidebar (OperatorLayout) so operators can reach the
                    // panel pages they've been given. Empty when none granted.
                    'accessibleTabLinks' => $this->accessibleTabLinks($user),
                ] : null,
            ],
            // Optional feature modules switched on for this installation. The
            // admin nav gets this filtering for free through `accessibleTabs`
            // (TabRegistry consults ModuleRegistry); the supervisor tree is gated
            // by role rather than by tab, so it needs the list directly to hide
            // entries for a module an admin has turned off.
            'enabledModules' => fn () => \App\Support\ModuleRegistry::enabled(),
            // Nav chrome needs the alert badge and a CSRF token for the
            // logout form. Lazy closures so they only run when a page renders.
            'nav' => [
                'alertCount' => fn () => $this->alertCount($user),
            ],
            // Menu items registered by enabled modules via MenuRegistry. The old
            // Blade sidebar read the registry directly; the React sidebar can't,
            // so bridge it here. Only enabled modules populate the registry
            // (AppServiceProvider boots their providers), so this is self-gating.
            //   items:  { <builtin-group>: [{label,url,order}] } injected into
            //           existing dropdowns (orders|production|structure|hr|
            //           maintenance|admin).
            //   groups: [{id,label,order,items:[{label,url,order}]}] custom
            //           top-level dropdowns a module declares on its own.
            'moduleNav' => [
                'items' => fn () => app(\App\Services\MenuRegistry::class)->getAllItems(),
                'groups' => fn () => app(\App\Services\MenuRegistry::class)->getGroups(),
            ],
            'csrf_token' => fn () => csrf_token(),
            'appVersion' => fn () => config('version.current'),
            // i18n: the active locale + the switcher's options. The frontend
            // loads the matching lang/<locale>.json chunk itself (see lib/i18n).
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => config('app.available_locales'),
            // Plant timezone — the frontend formats all dates/times in this zone
            // (config/app.php → APP_TIMEZONE) instead of the viewer's browser zone.
            'timezone' => fn () => config('app.timezone'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    /**
     * Tab keys the user can access (from TabRegistry), for nav filtering.
     *
     * @return array<int, string>
     */
    private function accessibleTabs($user): array
    {
        return $this->accessibleTabs ??= \App\Support\TabRegistry::accessibleFor($user);
    }

    /** Memoised per request; this middleware instance does not outlive one. */
    private ?array $accessibleTabs = null;

    /**
     * Accessible tabs as {key, label, url}, in registry order — for the operator
     * screen's sidebar. Labels are English keys; the frontend translates them.
     *
     * @return array<int, array{key: string, label: string, url: string|null}>
     */
    private function accessibleTabLinks($user): array
    {
        $labels = \App\Support\TabRegistry::labels();

        return array_map(
            fn (string $key) => [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'url' => \App\Support\TabRegistry::url($key),
            ],
            // Resolved once per request — the tab set is not cheap to compute
            // and both this and the `accessibleTabs` prop need it.
            $this->accessibleTabs($user),
        );
    }

    private function alertCount($user): int
    {
        if (! $user || ! $user->hasAnyRole(['Admin', 'Supervisor'])) {
            return 0;
        }

        try {
            return \App\Http\Controllers\Web\Admin\AlertController::totalCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
