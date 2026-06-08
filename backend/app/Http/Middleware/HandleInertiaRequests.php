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
