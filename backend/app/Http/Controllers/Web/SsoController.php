<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SsoCallbackRequest;
use App\Services\Auth\Sso\OidcClient;
use App\Services\Auth\Sso\SsoException;
use App\Services\Auth\Sso\SsoUserResolver;
use App\Support\SsoSettings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Sign in through the company's identity provider (OpenID Connect).
 *
 * GET /auth/sso/redirect  → the provider's login page
 * GET /auth/sso/callback  → back here with a one-time code; the code is
 *                           exchanged server-side, the ID token verified, and
 *                           the matching OpenMES account signed in.
 */
class SsoController extends Controller
{
    /** One pending attempt per session: state, nonce and PKCE verifier. */
    private const SESSION_KEY = 'sso_pending';

    public function __construct(
        private readonly OidcClient $client,
        private readonly SsoUserResolver $resolver,
    ) {}

    public function redirect(Request $request)
    {
        if (! SsoSettings::enabled()) {
            return redirect()->route('login')->with('error', __('Single sign-on is not enabled.'));
        }

        $settings = SsoSettings::all();

        try {
            $metadata = $this->client->discover($settings['issuer']);
        } catch (SsoException $e) {
            return $this->fail($e);
        }

        $authorization = $this->client->authorizationRequest(
            $metadata,
            $settings['client_id'],
            $settings['scopes'],
            route('sso.callback'),
        );

        $request->session()->put(self::SESSION_KEY, [
            'state' => $authorization['state'],
            'nonce' => $authorization['nonce'],
            'code_verifier' => $authorization['code_verifier'],
        ]);

        return redirect()->away($authorization['url']);
    }

    public function callback(SsoCallbackRequest $request)
    {
        // Pulled, not read: a state value is good for exactly one return.
        $pending = $request->session()->pull(self::SESSION_KEY);

        if (! SsoSettings::enabled()) {
            return redirect()->route('login')->with('error', __('Single sign-on is not enabled.'));
        }

        if (! is_array($pending) || ! hash_equals((string) $pending['state'], (string) $request->input('state'))) {
            return $this->fail(new SsoException(
                __('Your sign-in attempt expired. Please try again.'),
                'state missing or mismatched',
            ));
        }

        if ($request->filled('error')) {
            return $this->fail(new SsoException(
                __('Sign-in was cancelled or refused by the identity provider.'),
                'provider returned error '.$request->input('error'),
            ));
        }

        $settings = SsoSettings::all();
        $claims = [];

        try {
            $metadata = $this->client->discover($settings['issuer']);
            $claims = $this->client->completeLogin(
                $metadata,
                $settings['client_id'],
                SsoSettings::clientSecret(),
                (string) $request->input('code'),
                (string) $pending['code_verifier'],
                route('sso.callback'),
                (string) $pending['nonce'],
            );
            $user = $this->resolver->resolve($claims, $settings);
        } catch (SsoException $e) {
            $identity = $claims['email'] ?? $claims['sub'] ?? null;

            return $this->fail($e, is_string($identity) ? mb_substr($identity, 0, 200) : null);
        }

        // Local two-factor stays in force on top of the provider's own checks
        // (mirrors the password and PIN logins). No "remember me" here.
        if ($user->two_factor_enabled) {
            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_remember', false);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return app(AuthController::class)->redirectToDashboard();
    }

    /** Back to the login screen with a safe message; the detail goes to the log. */
    private function fail(SsoException $e, ?string $identity = null)
    {
        Log::warning('SSO login failed', ['reason' => $e->detail ?? $e->getMessage()]);

        // Recorded as a failed login in the audit log, like a wrong password.
        event(new Failed('web', null, ['username' => 'sso:'.($identity ?? 'unknown')]));

        return redirect()->route('login')->with('error', $e->getMessage());
    }
}
