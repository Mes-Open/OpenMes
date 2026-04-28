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

        Shift::create($validated);

        return redirect()->route('admin.shifts.index')->with('success', 'Shift created.');
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

        $shift->update($validated);

        return redirect()->route('admin.shifts.index')->with('success', 'Shift updated.');
    }

    public function destroy(Shift $shift)
    {
        if ($shift->shiftEntries()->exists()) {
            return back()->with('error', 'Cannot delete shift with production entries. Deactivate it instead.');
        }

        $shift->delete();

        return redirect()->route('admin.shifts.index')->with('success', 'Shift deleted.');
    }
}
