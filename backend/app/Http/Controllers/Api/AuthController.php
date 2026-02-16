<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Login and generate API token.
     *
     * @param Request $request
     * @return JsonResponse
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
     *
     * @param Request $request
     * @return JsonResponse
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
     *
     * @param Request $request
     * @return JsonResponse
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
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $user = $this->authService->me($request->user());

        return response()->json([
            'data' => $user,
        ]);
    }

    /**
     * Refresh token (extend expiration).
     *
     * @param Request $request
     * @return JsonResponse
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
