<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnomalyReason;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnomalyReasonController extends Controller
{
    /**
     * Display a listing of anomaly reasons. Rows live-sync via the
     * `anomaly_reasons` shape; usage counts come as a prop.
     */
    public function index()
    {
        $counts = AnomalyReason::withCount('anomalies')
            ->get(['id'])
            ->mapWithKeys(fn ($r) => [$r->id => $r->anomalies_count]);

        return Inertia::render('admin/anomaly-reasons/Index', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new anomaly reason.
     */
    public function create()
    {
        return Inertia::render('admin/anomaly-reasons/Create');
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
        return Inertia::render('admin/anomaly-reasons/Edit', [
            'anomalyReason' => $anomalyReason->only('id', 'code', 'name', 'category', 'description', 'is_active'),
        ]);
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
