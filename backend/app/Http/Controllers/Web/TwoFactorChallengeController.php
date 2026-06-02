<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    /**
     * Show the 2FA challenge form.
     */
    public function show(Request $request)
    {
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/TwoFactorChallenge');
    }

    /**
     * Verify the 2FA code and complete login.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $userId = $request->session()->get('2fa_user_id');
        $remember = $request->session()->get('2fa_remember', false);

        if (!$userId) {
            return redirect()->route('login');
        }

        // Rate limit: 5 attempts per minute
        $key = '2fa-challenge:' . $userId;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'code' => "Too many attempts. Please wait {$seconds} seconds.",
            ]);
        }

        $user = User::find($userId);
        if (!$user || !$user->two_factor_enabled) {
            $request->session()->forget(['2fa_user_id', '2fa_remember']);
            return redirect()->route('login');
        }

        // Try TOTP code
        if ($request->filled('code')) {
            $google2fa = new Google2FA();
            $secret = Crypt::decryptString($user->two_factor_secret);
            $valid = $google2fa->verifyKey($secret, $request->input('code'), 1);

            if ($valid) {
                return $this->completeLogin($request, $user, $remember);
            }

            RateLimiter::hit($key, 60);
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        // Try recovery code
        if ($request->filled('recovery_code')) {
            $recoveryCode = $request->input('recovery_code');
            $storedCodes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

            foreach ($storedCodes as $index => $hashedCode) {
                if (Hash::check($recoveryCode, $hashedCode)) {
                    // Remove used code
                    unset($storedCodes[$index]);
                    $user->update([
                        'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($storedCodes))),
                    ]);

                    return $this->completeLogin($request, $user, $remember);
                }
            }

            RateLimiter::hit($key, 60);
            return back()->withErrors(['recovery_code' => 'Invalid recovery code.']);
        }

        return back()->withErrors(['code' => 'Please enter an authentication code or recovery code.']);
    }

    /**
     * Complete login after 2FA verification.
     */
    protected function completeLogin(Request $request, User $user, bool $remember)
    {
        $request->session()->forget(['2fa_user_id', '2fa_remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        if ($user->force_password_change) {
            return redirect()->route('change-password')
                ->with('error', 'You must change your password before continuing.');
        }

        // Redirect based on role (same logic as AuthController)
        if ($user->hasRole('Admin')) {
            if (OnboardingController::shouldShowWizard()) {
                return redirect()->route('onboarding.index');
            }
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('Supervisor')) {
            return redirect()->route('supervisor.dashboard');
        }

        if ($user->account_type === 'workstation' && $user->workstation_id) {
            $lineId = $user->workstation?->line_id;
            if ($lineId) {
                return redirect()->route('operator.queue', ['line' => $lineId]);
            }
        }

        return redirect()->route('operator.select-line');
    }
}
