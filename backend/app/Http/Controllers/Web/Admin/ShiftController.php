<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::orderBy('sort_order')->orderBy('start_time')->get();
        return view('admin.shifts.index', compact('shifts'));
    }

    public function create()
    {
        return view('admin.shifts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:50',
            'code'       => 'required|string|max:10|unique:shifts,code',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? Shift::max('sort_order') + 1;

        // Check for overlapping shifts
        if ($this->hasOverlap($validated['start_time'], $validated['end_time'])) {
            return back()->withInput()->with('error', __('This shift overlaps with an existing shift. Adjust the times and try again.'));
        }

        Shift::create($validated);

        return redirect()->route('admin.shifts.index')->with('success', __('Shift created.'));
    }

    public function edit(Shift $shift)
    {
        return view('admin.shifts.edit', compact('shift'));
    }

    public function update(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:50',
            'code'       => 'required|string|max:10|unique:shifts,code,' . $shift->id,
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        // Check for overlapping shifts (exclude current)
        if ($this->hasOverlap($validated['start_time'], $validated['end_time'], $shift->id)) {
            return back()->withInput()->with('error', __('This shift overlaps with an existing shift. Adjust the times and try again.'));
        }

        $shift->update($validated);

        return redirect()->route('admin.shifts.index')->with('success', __('Shift updated.'));
    }

    public function destroy(Shift $shift)
    {
        if ($shift->shiftEntries()->exists()) {
            return back()->with('error', __('Cannot delete shift with production entries. Deactivate it instead.'));
        }

        $shift->delete();

        return redirect()->route('admin.shifts.index')->with('success', __('Shift deleted.'));
    }

    /**
     * Check if a time range overlaps with any existing active shift.
     */
    private function hasOverlap(string $start, string $end, ?int $excludeId = null): bool
    {
        $query = Shift::where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get()->contains(function (Shift $existing) use ($start, $end) {
            $eStart = substr($existing->start_time, 0, 5);
            $eEnd   = substr($existing->end_time, 0, 5);

            // Handle overnight shifts (e.g. 22:00 - 06:00)
            $newCrossesMidnight = $start > $end;
            $existCrossesMidnight = $eStart > $eEnd;

            if (!$newCrossesMidnight && !$existCrossesMidnight) {
                // Both within same day
                return $start < $eEnd && $end > $eStart;
            }

            // At least one crosses midnight — normalize to minutes and check
            $toMin = fn($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
            $ns = $toMin($start);
            $ne = $toMin($end) + ($newCrossesMidnight ? 1440 : 0);
            $es = $toMin($eStart);
            $ee = $toMin($eEnd) + ($existCrossesMidnight ? 1440 : 0);

            // Check overlap in both original and +24h shifted positions
            return ($ns < $ee && $ne > $es)
                || ($ns < $ee + 1440 && $ne > $es + 1440)
                || ($ns + 1440 < $ee && $ne + 1440 > $es);
        });
    }
}
