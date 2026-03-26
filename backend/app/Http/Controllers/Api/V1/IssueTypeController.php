<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IssueType;
use App\Http\Resources\IssueTypeResource;
use App\Traits\StandardApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueTypeController extends Controller
{
    use StandardApiResponse;

    /**
     * Get all issue types (active by default).
     */
    public function index(Request $request): JsonResponse
    {
        $query = IssueType::query();

        // Filter active only unless explicitly requested
        if (!$request->boolean('include_inactive')) {
            $query->active();
        }

        $issueTypes = $query->orderBy('severity', 'desc')
            ->orderBy('name')
            ->get();

        return IssueTypeResource::collection($issueTypes)->response();
    }

    /**
     * Get a single issue type.
     */
    public function show(IssueType $issueType): JsonResponse
    {
        return (new IssueTypeResource($issueType))->response();
    }

    /**
     * Create a new issue type (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', IssueType::class);
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:issue_types,code',
            'name' => 'required|string|max:255',
            'severity' => 'required|string|in:LOW,MEDIUM,HIGH,CRITICAL',
            'is_blocking' => 'required|boolean',
            'is_active' => 'boolean',
        ]);

        $issueType = IssueType::create($validated);

        return $this->success(
            new IssueTypeResource($issueType),
            __('Issue type created successfully'),
            201
        );
    }

    /**
     * Update an issue type (Admin only).
     */
    public function update(Request $request, IssueType $issueType): JsonResponse
    {
        $this->authorize('update', $issueType);
        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:issue_types,code,' . $issueType->id,
            'name' => 'sometimes|string|max:255',
            'severity' => 'sometimes|string|in:LOW,MEDIUM,HIGH,CRITICAL',
            'is_blocking' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $issueType->update($validated);

        return $this->success(
            new IssueTypeResource($issueType->fresh()),
            __('Issue type updated successfully')
        );
    }

    /**
     * Delete an issue type (Admin only).
     */
    public function destroy(IssueType $issueType): JsonResponse
    {
        $this->authorize('delete', $issueType);
        // Soft delete by setting is_active to false
        $issueType->update(['is_active' => false]);

        return $this->success(null, __('Issue type deactivated successfully'));
    }
}
