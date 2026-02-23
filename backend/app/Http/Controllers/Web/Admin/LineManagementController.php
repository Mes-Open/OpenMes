<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\LineStatus;
use Illuminate\Http\Request;

class LineManagementController extends Controller
{
    /**
     * Display a listing of production lines
     */
    public function index()
    {
        $lines = Line::withCount(['workstations', 'workOrders', 'users'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        return view('admin.lines.index', compact('lines'));
    }

    /**
     * Show the form for creating a new line
     */
    public function create()
    {
        return view('admin.lines.create');
    }

    /**
     * Store a newly created line
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:lines',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Line::create($validated);

        return redirect()->route('admin.lines.index')
            ->with('success', 'Production line created successfully.');
    }

    /**
     * Display the specified line
     */
    public function show(Line $line)
    {
        $line->load(['workstations', 'users.roles']);
        $workOrders = $line->workOrders()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get operators not assigned to this line
        $availableOperators = \App\Models\User::role('Operator')
            ->whereNotIn('id', $line->users->pluck('id'))
            ->orderBy('name')
            ->get();

        $lineStatuses = LineStatus::forLine($line->id)->get();

        return view('admin.lines.show', compact('line', 'workOrders', 'availableOperators', 'lineStatuses'));
    }

    /**
     * Show the form for editing a line
     */
    public function edit(Line $line)
    {
        return view('admin.lines.edit', compact('line'));
    }

    /**
     * Update the specified line
     */
    public function update(Request $request, Line $line)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:lines,code,' . $line->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $line->update($validated);

        return redirect()->route('admin.lines.index')
            ->with('success', 'Production line updated successfully.');
    }

    /**
     * Remove the specified line
     */
    public function destroy(Line $line)
    {
        // Check if line has work orders
        if ($line->workOrders()->count() > 0) {
            return redirect()->route('admin.lines.index')
                ->with('error', 'Cannot delete line with existing work orders. Deactivate it instead.');
        }

        $line->delete();

        return redirect()->route('admin.lines.index')
            ->with('success', 'Production line deleted successfully.');
    }

    /**
     * Toggle line active status
     */
    public function toggleActive(Line $line)
    {
        $line->update(['is_active' => !$line->is_active]);

        $status = $line->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.lines.index')
            ->with('success', "Production line {$status} successfully.");
    }

    /**
     * Assign an operator to the line
     */
    public function assignOperator(Request $request, Line $line)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = \App\Models\User::findOrFail($validated['user_id']);

        // Check if user is an operator
        if (!$user->hasRole('Operator')) {
            return redirect()->route('admin.lines.show', $line)
                ->with('error', 'Only operators can be assigned to production lines.');
        }

        // Check if already assigned
        if ($line->users()->where('user_id', $user->id)->exists()) {
            return redirect()->route('admin.lines.show', $line)
                ->with('error', 'Operator is already assigned to this line.');
        }

        $line->users()->attach($user->id);

        return redirect()->route('admin.lines.show', $line)
            ->with('success', "Operator {$user->name} assigned successfully.");
    }

    /**
     * Unassign an operator from the line
     */
    public function unassignOperator(Line $line, $userId)
    {
        $user = \App\Models\User::findOrFail($userId);

        $line->users()->detach($user->id);

        return redirect()->route('admin.lines.show', $line)
            ->with('success', "Operator {$user->name} unassigned successfully.");
    }
}
