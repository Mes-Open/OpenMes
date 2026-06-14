<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Shift;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/shifts/Index', [
            'lineNames' => Line::pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/shifts/Create', [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customFields' => app(CustomFieldService::class)->clientConfig('shift'),
        ]);
    }

    public function store(Request $request)
    {
        $cf = app(CustomFieldService::class);
        $validated = $request->validate(array_merge([
            'name'       => 'required|string|max:50',
            'code'       => 'required|string|max:10|unique:shifts,code',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'sort_order' => 'nullable|integer|min:0',
            'line_id'    => 'nullable|exists:lines,id',
        ], $cf->rules('shift')), [], $cf->attributeNames('shift'));

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? Shift::max('sort_order') + 1;
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'shift') ?: null;
        }

        // Overlap is scoped to the same line (or both global) — same-time shifts
        // on different lines are fine.
        if ($this->hasOverlap($validated['start_time'], $validated['end_time'], $validated['line_id'] ?? null)) {
            return back()->withInput()->with('error', __('This shift overlaps with an existing shift on this line. Adjust the times and try again.'));
        }

        Shift::create($validated);

        return redirect()->route('admin.shifts.index')->with('success', __('Shift created.'));
    }

    public function edit(Shift $shift)
    {
        return Inertia::render('admin/shifts/Edit', [
            'shift' => $shift->only('id', 'code', 'name', 'start_time', 'end_time', 'sort_order', 'line_id', 'is_active', 'custom_fields'),
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customFields' => app(CustomFieldService::class)->clientConfig('shift'),
        ]);
    }

    public function update(Request $request, Shift $shift)
    {
        $cf = app(CustomFieldService::class);
        $validated = $request->validate(array_merge([
            'name'       => 'required|string|max:50',
            'code'       => 'required|string|max:10|unique:shifts,code,' . $shift->id,
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'sort_order' => 'nullable|integer|min:0',
            'line_id'    => 'nullable|exists:lines,id',
        ], $cf->rules('shift')), [], $cf->attributeNames('shift'));

        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'shift', $shift->custom_fields) ?: null;
        }

        if ($this->hasOverlap($validated['start_time'], $validated['end_time'], $validated['line_id'] ?? null, $shift->id)) {
            return back()->withInput()->with('error', __('This shift overlaps with an existing shift on this line. Adjust the times and try again.'));
        }

        // sort_order is NOT NULL DEFAULT 0; preserve the existing value if the
        // field is cleared rather than passing an explicit null.
        $validated['sort_order'] ??= $shift->sort_order;

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
     * Check if a time range overlaps with an existing active shift on the same
     * line (global shifts, line_id = null, only conflict with other globals).
     */
    private function hasOverlap(string $start, string $end, ?int $lineId = null, ?int $excludeId = null): bool
    {
        $query = Shift::where('is_active', true);

        if ($lineId === null) {
            $query->whereNull('line_id');
        } else {
            $query->where('line_id', $lineId);
        }

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
