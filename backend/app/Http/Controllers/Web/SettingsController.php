<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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
}
