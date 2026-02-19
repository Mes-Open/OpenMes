<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnomalyReason;
use Illuminate\Http\Request;

class AnomalyReasonController extends Controller
{
    /**
     * Display a listing of anomaly reasons.
     */
    public function index(Request $request)
    {
        $query = AnomalyReason::withCount('anomalies')
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $anomalyReasons = $query->paginate(25)->withQueryString();

        return view('admin.anomaly-reasons.index', compact('anomalyReasons'));
    }

    /**
     * Show the form for creating a new anomaly reason.
     */
    public function create()
    {
        return view('admin.anomaly-reasons.create');
    }

    /**
     * Store a newly created anomaly reason.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:anomaly_reasons',
            'name'        => 'required|string|max:255',
            'category'    => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        AnomalyReason::create($validated);

        return redirect()->route('admin.anomaly-reasons.index')
            ->with('success', 'Anomaly reason created successfully.');
    }

    /**
     * Show the form for editing an anomaly reason.
     */
    public function edit(AnomalyReason $anomalyReason)
    {
        return view('admin.anomaly-reasons.edit', compact('anomalyReason'));
    }

    /**
     * Update the specified anomaly reason.
     */
    public function update(Request $request, AnomalyReason $anomalyReason)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:anomaly_reasons,code,' . $anomalyReason->id,
            'name'        => 'required|string|max:255',
            'category'    => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $anomalyReason->update($validated);

        return redirect()->route('admin.anomaly-reasons.index')
            ->with('success', 'Anomaly reason updated successfully.');
    }

    /**
     * Remove the specified anomaly reason.
     */
    public function destroy(AnomalyReason $anomalyReason)
    {
        if ($anomalyReason->anomalies()->count() > 0) {
            return redirect()->route('admin.anomaly-reasons.index')
                ->with('error', 'Cannot delete anomaly reason with existing anomaly records. Deactivate it instead.');
        }

        $anomalyReason->delete();

        return redirect()->route('admin.anomaly-reasons.index')
            ->with('success', 'Anomaly reason deleted successfully.');
    }

    /**
     * Toggle anomaly reason active status.
     */
    public function toggleActive(AnomalyReason $anomalyReason)
    {
        $anomalyReason->update(['is_active' => ! $anomalyReason->is_active]);

        $status = $anomalyReason->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.anomaly-reasons.index')
            ->with('success', "Anomaly reason {$status} successfully.");
    }
}
