<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Models\Line;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['roles', 'lines', 'workstation', 'worker']);

        if ($role = $request->query('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $role));
        }
        if ($lineId = $request->query('line_id')) {
            $query->whereHas('lines', fn($q) => $q->where('lines.id', $lineId));
        }
        if ($accountType = $request->query('account_type')) {
            $query->where('account_type', $accountType);
        }
        if ($q = $request->query('q')) {
            $needle = '%' . strtolower($q) . '%';
            $query->where(function ($qb) use ($needle) {
                $qb->whereRaw('LOWER(username) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $users = $query->orderBy('username')->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'lines', 'workstation', 'worker']);

        return response()->json(['data' => $user]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'account_type' => $validated['account_type'],
                'workstation_id' => $validated['account_type'] === 'workstation'
                    ? ($validated['workstation_id'] ?? null)
                    : null,
                'worker_id' => $validated['worker_id'] ?? null,
                'force_password_change' => $validated['force_password_change'] ?? false,
            ]);

            if ($validated['account_type'] === 'user' && !empty($validated['role'])) {
                $user->assignRole($validated['role']);
            } elseif ($validated['account_type'] === 'workstation') {
                $user->assignRole('Operator');
            }

            if (!empty($validated['line_ids'])) {
                $user->lines()->sync($validated['line_ids']);
            }

            return $user;
        });

        $user->load(['roles', 'lines', 'workstation', 'worker']);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user,
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated) {
            $updateData = collect($validated)
                ->only(['name', 'username', 'email', 'account_type', 'worker_id', 'force_password_change'])
                ->toArray();

            // Handle workstation_id based on account_type
            if (array_key_exists('account_type', $validated)) {
                $updateData['workstation_id'] = $validated['account_type'] === 'workstation'
                    ? ($validated['workstation_id'] ?? null)
                    : null;
            } elseif (array_key_exists('workstation_id', $validated)) {
                $updateData['workstation_id'] = $validated['workstation_id'];
            }

            $user->update($updateData);

            // Role sync
            if (array_key_exists('role', $validated)) {
                $accountType = $validated['account_type'] ?? $user->account_type;
                if ($accountType === 'user') {
                    if (!empty($validated['role'])) {
                        $user->syncRoles([$validated['role']]);
                    } else {
                        $user->syncRoles([]);
                    }
                } elseif ($accountType === 'workstation') {
                    $user->syncRoles(['Operator']);
                }
            }

            // Line sync
            if (array_key_exists('line_ids', $validated)) {
                $user->lines()->sync($validated['line_ids'] ?? []);
            }
        });

        $user->load(['roles', 'lines', 'workstation', 'worker']);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user,
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('resetPassword', $user);

        $validated = $request->validated();

        $user->update([
            'password' => Hash::make($validated['password']),
            'force_password_change' => $validated['force_password_change'] ?? true,
        ]);

        // Revoke all existing tokens so they have to re-authenticate.
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    public function lines(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => $user->lines()->orderBy('name')->get(),
        ]);
    }

    public function syncLines(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageLines', $user);

        $validated = $request->validate([
            'line_ids' => ['required', 'array'],
            'line_ids.*' => ['integer', 'exists:lines,id'],
        ]);

        $user->lines()->sync($validated['line_ids']);

        return response()->json([
            'message' => 'Lines updated successfully',
            'data' => $user->lines()->orderBy('name')->get(),
        ]);
    }
}
