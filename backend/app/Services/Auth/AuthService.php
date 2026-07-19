<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\TabRegistry;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate a user and generate API token.
     *
     * @throws ValidationException
     */
    public function login(string $username, string $password): array
    {
        $user = User::where('username', $username)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Generate API token
        $tokenTtl = config('openmmes.default_token_ttl_minutes', 15);
        $token = $user->createToken(
            'api-token',
            ['*'],
            now()->addMinutes($tokenTtl)
        )->plainTextToken;

        $user->load('roles', 'lines');
        // Nav-filtering tabs, same list the web sidebar uses — lets the mobile
        // app filter its sidebar identically straight from the login payload.
        $user->setAttribute('accessible_tabs', TabRegistry::accessibleFor($user));

        return [
            'user' => $user,
            'token' => $token,
            'force_password_change' => $user->force_password_change,
        ];
    }

    /**
     * Logout a user by revoking their tokens.
     */
    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Change user password.
     *
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'force_password_change' => false,
        ]);
    }

    /**
     * Get authenticated user with relationships.
     */
    public function me(User $user): User
    {
        $user->load(['roles.permissions', 'lines']);
        // Nav-filtering tabs, same list the web sidebar uses (see HandleInertiaRequests).
        $user->setAttribute('accessible_tabs', TabRegistry::accessibleFor($user));

        return $user;
    }
}
