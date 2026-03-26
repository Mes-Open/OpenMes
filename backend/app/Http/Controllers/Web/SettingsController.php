<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class SettingsController extends Controller
{
    /**
     * Show settings page
     */
    public function index()
    {
        return view('settings.index');
    }

    /**
     * Set application language
     */
    public function setLanguage(Request $request)
    {
        $validated = $request->validate([
            'locale' => 'required|in:en,tr',
        ]);

        $request->session()->put('locale', $validated['locale']);

        return back()->with('success', __('Language changed successfully.'));
    }

    /**
     * Show change password form
     */
    public function showChangePasswordForm()
    {
        return view('settings.change-password');
    }

    /**
     * Update user's password
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = auth()->user();

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['password']),
            'force_password_change' => false,
        ]);

        return redirect()->route('settings.index')
            ->with('success', 'Password changed successfully.');
    }

    /**
     * Show profile edit form
     */
    public function showProfileForm()
    {
        return view('settings.profile');
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . auth()->id(),
        ]);

        auth()->user()->update($validated);

        return redirect()->route('settings.index')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Show admin-only system settings page.
     */
    public function showSystemSettings()
    {
        $rows = DB::table('system_settings')->get()->keyBy('key');

        $settings = [
            'production_period'     => json_decode($rows['production_period']->value ?? '"none"', true) ?? 'none',
            'allow_overproduction'  => json_decode($rows['allow_overproduction']->value ?? 'false', true) ?? false,
            'force_sequential_steps' => json_decode($rows['force_sequential_steps']->value ?? 'true', true) ?? true,
            'workflow_mode'         => json_decode($rows['workflow_mode']->value ?? '"status"', true) ?? 'status',
        ];

        return view('settings.system', compact('settings'));
    }

    /**
     * Show API tokens management page (admin only).
     */
    public function showApiTokens()
    {
        $tokens = PersonalAccessToken::where('tokenable_type', 'App\Models\User')
            ->with('tokenable')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('settings.api-tokens', compact('tokens'));
    }

    /**
     * Create a new API token (admin only).
     */
    public function createApiToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $user = auth()->user();
        $token = $user->createToken($validated['name']);

        return redirect()->route('settings.api-tokens')
            ->with('new_token', $token->plainTextToken)
            ->with('new_token_name', $validated['name']);
    }

    /**
     * Revoke (delete) an API token (admin only).
     */
    public function revokeApiToken(Request $request, PersonalAccessToken $token)
    {
        $token->delete();

        return redirect()->route('settings.api-tokens')
            ->with('success', 'Token revoked successfully.');
    }

    /**
     * Load sample data (admin only).
     */
    public function loadSampleData()
    {
        Artisan::call('db:seed', ['--class' => 'PrintShopDemoSeeder', '--force' => true]);

        return redirect()->route('settings.system')
            ->with('success', 'Sample data loaded successfully. Lines, work orders, operators and product types have been created.');
    }

    /**
     * Update system settings (admin only).
     */
    public function updateSystemSettings(Request $request)
    {
        $validated = $request->validate([
            'production_period'      => 'required|in:none,weekly,monthly',
            'allow_overproduction'   => 'nullable|boolean',
            'force_sequential_steps' => 'nullable|boolean',
            'workflow_mode'          => 'required|in:status,board_status',
        ]);

        $map = [
            'production_period'      => $validated['production_period'],
            'allow_overproduction'   => (bool) ($validated['allow_overproduction'] ?? false),
            'force_sequential_steps' => (bool) ($validated['force_sequential_steps'] ?? false),
            'workflow_mode'          => $validated['workflow_mode'],
        ];

        foreach ($map as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value)]
            );
        }

        return redirect()->route('settings.system')
            ->with('success', 'System settings updated.');
    }
}
