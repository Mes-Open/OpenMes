<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApiTokenScope;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Login and generate API token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $result = $this->authService->login(
            $request->input('username'),
            $request->input('password')
        );

        return response()->json([
            'message' => 'Login successful',
            'data' => $result,
        ]);
    }

    /**
     * Logout and revoke all tokens.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $this->authService->changePassword(
            $request->user(),
            $request->input('current_password'),
            $request->input('new_password')
        );

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Get authenticated user info.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $this->authService->me($request->user());

        // What the calling token may do, so a client (e.g. an MCP server) can
        // offer only the actions that will succeed. Null for session auth.
        $token = $request->user()->currentAccessToken();

        return response()->json([
            'data' => $user,
            'token' => $token instanceof PersonalAccessToken ? [
                'scope' => ApiTokenScope::fromAbilities($token->abilities)->value,
                'abilities' => $token->abilities,
            ] : null,
        ]);
    }

    /**
     * Refresh token (extend expiration).
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete old token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $tokenTtl = config('openmmes.default_token_ttl_minutes', 15);
        $token = $user->createToken(
            'api-token',
            ['*'],
            now()->addMinutes($tokenTtl)
        )->plainTextToken;

        return response()->json([
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token,
            ],
        ]);
    }
}
