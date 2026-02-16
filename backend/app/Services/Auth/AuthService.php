<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate a user and generate API token.
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws ValidationException
     */
    public function login(string $username, string $password): array
    {
        $user = User::where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
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

        return [
            'user' => $user->load('roles', 'lines'),
            'token' => $token,
            'force_password_change' => $user->force_password_change,
        ];
    }

    /**
     * Logout a user by revoking their tokens.
     *
     * @param User $user
     * @return void
     */
    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Change user password.
     *
     * @param User $user
     * @param string $currentPassword
     * @param string $newPassword
     * @return void
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
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
     *
     * @param User $user
     * @return User
     */
    public function me(User $user): User
    {
        return $user->load(['roles.permissions', 'lines']);
    }
}
