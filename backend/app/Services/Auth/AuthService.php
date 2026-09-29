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
                'username' => [__('The provided credentials are incorrect.')],
            ]);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // An account that owes a password change gets a token that can do one
        // thing: change the password. Handing out '*' and trusting the client
        // to respect the flag in the response body is not a control — a mobile
        // app, an ERP integration or curl has no reason to honour it.
        $tokenTtl = config('openmmes.default_token_ttl_minutes', 15);
        $abilities = $user->force_password_change ? ['password:change'] : ['*'];

        $token = $user->createToken(
            'api-token',
            $abilities,
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
                'current_password' => [__('The current password is incorrect.')],
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'force_password_change' => false,
        ]);

        // Retire every token issued against the old password. The API's own
        // resetPassword endpoint already did this; changePassword did not, so a
        // token stolen before the change kept working after it.
        $user->tokens()->delete();
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
