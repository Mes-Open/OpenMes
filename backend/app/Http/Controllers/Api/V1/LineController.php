<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLineRequest;
use App\Http\Requests\Api\V1\UpdateLineRequest;
use App\Models\Line;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $includeInactive = $request->boolean('include_inactive');

        if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
            $query = Line::query()->with('workstations');
            if (!$includeInactive) {
                $query->where('is_active', true);
            }
        } else {
            $query = $user->lines()
                ->where('lines.is_active', true)
                ->with('workstations')
                ->getQuery();
        }

        if ($divisionId = $request->query('division_id')) {
            $query->where('division_id', $divisionId);
        }
        if ($q = $request->query('q')) {
            $needle = '%' . strtolower($q) . '%';
            $query->where(function ($qb) use ($needle) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function show(Line $line): JsonResponse
    {
        $this->authorize('view', $line);

        $line->loadCount(['workstations', 'workOrders', 'users']);
        $line->load(['workstations', 'users.roles', 'productTypes', 'division']);

        return response()->json(['data' => $line]);
    }

    public function store(StoreLineRequest $request): JsonResponse
    {
        $this->authorize('create', Line::class);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $line = Line::create($data);

        return response()->json([
            'message' => 'Line created successfully',
            'data' => $line->fresh(['workstations', 'division']),
        ], 201);
    }

    public function update(UpdateLineRequest $request, Line $line): JsonResponse
    {
        $this->authorize('update', $line);

        $line->update($request->validated());

        return response()->json([
            'message' => 'Line updated successfully',
            'data' => $line->fresh(['workstations', 'division']),
        ]);
    }

    public function destroy(Line $line): JsonResponse
    {
        $this->authorize('delete', $line);

        if ($line->workOrders()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete line with existing work orders. Deactivate it instead.',
            ], 422);
        }

        $line->delete();

        return response()->json(['message' => 'Line deleted successfully']);
    }

    public function toggleActive(Line $line): JsonResponse
    {
        $this->authorize('update', $line);

        $line->update(['is_active' => !$line->is_active]);

        return response()->json([
            'message' => $line->is_active ? 'Line activated' : 'Line deactivated',
            'data' => $line,
        ]);
    }

    // ── Users (line ↔ user pivot) ────────────────────────────────────────────

    public function users(Line $line): JsonResponse
    {
        $this->authorize('view', $line);

        return response()->json([
            'data' => $line->users()->with('roles')->orderBy('username')->get(),
        ]);
    }

    public function syncUsers(Request $request, Line $line): JsonResponse
    {
        $this->authorize('manageAssignments', $line);

        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $line->users()->sync($validated['user_ids']);

        return response()->json([
            'message' => 'Users updated successfully',
            'data' => $line->users()->with('roles')->orderBy('username')->get(),
        ]);
    }

    public function unassignUser(Line $line, User $user): JsonResponse
    {
        $this->authorize('manageAssignments', $line);

        $line->users()->detach($user->id);

        return response()->json(['message' => 'User unassigned']);
    }

    // ── Product types (line ↔ product_type pivot) ────────────────────────────

    public function productTypes(Line $line): JsonResponse
    {
        $this->authorize('view', $line);

        return response()->json([
            'data' => $line->productTypes()->orderBy('name')->get(),
        ]);
    }

    public function syncProductTypes(Request $request, Line $line): JsonResponse
    {
        $this->authorize('manageAssignments', $line);

        $validated = $request->validate([
            'product_type_ids' => ['nullable', 'array'],
            'product_type_ids.*' => ['integer', 'exists:product_types,id'],
        ]);

        $line->productTypes()->sync($validated['product_type_ids'] ?? []);

        return response()->json([
            'message' => 'Product types updated successfully',
            'data' => $line->productTypes()->orderBy('name')->get(),
        ]);
    }
}
