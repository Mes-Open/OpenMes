<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the UI locale for the request: the user's choice persisted in the
 * session by the /locale/{locale} route, else the system language from
 * Settings → System (`system_settings.language`).
 *
 * The system language is read on every request, like the plant timezone
 * (ApplyPlantTimezone): AppServiceProvider::boot() applies it too, but under
 * Octane that runs once per worker, so a language saved in Settings would
 * never reach users without a session choice until the workers restarted.
 * Runs in the web group after StartSession.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = config('app.available_locales', []);

        $system = $this->systemLanguage();
        if ($system !== null && array_key_exists($system, $available)) {
            config(['app.locale' => $system]);
            app()->setLocale($system);
        }

        $locale = $request->session()->get('locale', app()->getLocale());

        if (array_key_exists($locale, $available)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function systemLanguage(): ?string
    {
        try {
            $raw = DB::table('system_settings')->where('key', 'language')->value('value');
        } catch (\Throwable) {
            return null; // no database yet (install)
        }

        $value = $raw === null ? null : json_decode($raw, true);

        return is_string($value) ? $value : null;
    }
}
