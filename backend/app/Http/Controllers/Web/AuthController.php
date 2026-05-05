<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\OnboardingController;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Attempt authentication
        if (!Auth::attempt([
            'username' => $request->input('username'),
            'password' => $request->input('password'),
        ], $request->filled('remember'))) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Regenerate session to prevent session fixation
        $request->session()->regenerate();

        // Update last login
        auth()->user()->update(['last_login_at' => now()]);

        // Check if user needs to change password
        if (auth()->user()->force_password_change) {
            return redirect()->route('change-password')
                ->with('error', 'You must change your password before continuing.');
        }

        // Redirect to appropriate dashboard based on role
        return $this->redirectToDashboard();
    }

    /**
     * Handle PIN login request.
     */
    public function loginWithPin(\App\Http\Requests\PinLoginRequest $request)
    {
        $pinEnabled = json_decode(
            DB::table('system_settings')->where('key', 'pin_login_enabled')->value('value') ?? 'false',
            true
        );

        if (!$pinEnabled) {
            throw ValidationException::withMessages([
                'pin' => ['PIN login is not enabled.'],
            ]);
        }

        $user = User::where('username', $request->input('username'))->first();

        if (!$user || empty($user->pin) || !Hash::check($request->input('pin'), $user->pin)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid username or PIN.'],
            ]);
        }

        Auth::login($user, false);

        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]);

        if ($user->force_password_change) {
            return redirect()->route('change-password')
                ->with('error', 'You must change your password before continuing.');
        }

        return $this->redirectToDashboard();
    }

    /**
     * Show the change password form.
     */
    public function showChangePasswordForm()
    {
        return view('auth.change-password');
    }

    /**
     * Handle password change request.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $this->authService->changePassword(
                auth()->user(),
                $request->input('current_password'),
                $request->input('new_password')
            );

            return redirect()->route('operator.select-line')
                ->with('success', 'Password changed successfully.');
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out successfully.');
    }

    /**
     * Redirect user to appropriate dashboard based on their role.
     */
    protected function redirectToDashboard()
    {
        $user = auth()->user();

        if ($user->hasRole('Admin')) {
            if (OnboardingController::shouldShowWizard()) {
                return redirect()->route('onboarding.index');
            }
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('Supervisor')) {
            return redirect()->route('supervisor.dashboard');
        }

        // Workstation accounts skip line selection — go straight to queue
        if ($user->account_type === 'workstation' && $user->workstation_id) {
            $lineId = $user->workstation?->line_id;
            if ($lineId) {
                return redirect()->route('operator.queue', ['line' => $lineId]);
            }
        }

        // Default to operator workflow
        return redirect()->route('operator.select-line');
    }
}
