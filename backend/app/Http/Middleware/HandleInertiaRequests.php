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
            // Nav chrome needs the alert badge and a CSRF token for the
            // logout form. Lazy closures so they only run when a page renders.
            'nav' => [
                'alertCount' => fn () => $this->alertCount($user),
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
        if (! $user) {
            return [];
        }

        // A tab shows only when the user may access it AND its feature module is
        // enabled for this installation (#144).
        return array_values(array_filter(
            \App\Support\TabRegistry::keys(),
            fn (string $key) => $user->can(\App\Support\TabRegistry::permission($key))
                && \App\Support\ModuleRegistry::isTabEnabled($key),
        ));
    }

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
