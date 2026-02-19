<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Factory;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    /**
     * Display a listing of divisions.
     */
    public function index(Request $request)
    {
        $query = Division::with('factory')
            ->withCount('crews')
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($factoryId = $request->input('factory_id')) {
            $query->where('factory_id', $factoryId);
        }

        $divisions = $query->paginate(25)->withQueryString();
        $factories = Factory::orderBy('name')->get();

        return view('admin.divisions.index', compact('divisions', 'factories'));
    }

    /**
     * Show the form for creating a new division.
     */
    public function create()
    {
        $factories = Factory::active()->orderBy('name')->get();

        return view('admin.divisions.create', compact('factories'));
    }

    /**
     * Store a newly created division.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'factory_id'  => 'nullable|exists:factories,id',
            'code'        => 'required|string|max:50|unique:divisions',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Division::create($validated);

        return redirect()->route('admin.divisions.index')
            ->with('success', 'Division created successfully.');
    }

    /**
     * Show the form for editing a division.
     */
    public function edit(Division $division)
    {
        $factories = Factory::active()->orderBy('name')->get();

        return view('admin.divisions.edit', compact('division', 'factories'));
    }

    /**
     * Update the specified division.
     */
    public function update(Request $request, Division $division)
    {
        $validated = $request->validate([
            'factory_id'  => 'nullable|exists:factories,id',
            'code'        => 'required|string|max:50|unique:divisions,code,' . $division->id,
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $division->update($validated);

        return redirect()->route('admin.divisions.index')
            ->with('success', 'Division updated successfully.');
    }

    /**
     * Remove the specified division.
     */
    public function destroy(Division $division)
    {
        if ($division->crews()->count() > 0) {
            return redirect()->route('admin.divisions.index')
                ->with('error', 'Cannot delete division with existing crews. Deactivate it instead.');
        }

        if ($division->lines()->count() > 0) {
            return redirect()->route('admin.divisions.index')
                ->with('error', 'Cannot delete division with assigned production lines. Deactivate it instead.');
        }

        $division->delete();

        return redirect()->route('admin.divisions.index')
            ->with('success', 'Division deleted successfully.');
    }

    /**
     * Toggle division active status.
     */
    public function toggleActive(Division $division)
    {
        $division->update(['is_active' => ! $division->is_active]);

        $status = $division->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.divisions.index')
            ->with('success', "Division {$status} successfully.");
    }
}
