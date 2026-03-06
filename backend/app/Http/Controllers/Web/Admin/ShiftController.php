<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::with('line')->orderBy('start_time')->get();
        $lines  = Line::where('is_active', true)->orderBy('name')->get();

        return view('admin.shifts.index', compact('shifts', 'lines'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i',
            'days_of_week' => 'required|array|min:1',
            'days_of_week.*' => 'integer|between:1,7',
            'line_id'      => 'nullable|exists:lines,id',
            'is_active'    => 'boolean',
        ]);

        $validated['days_of_week'] = $validated['days_of_week'];
        $validated['is_active']    = $request->boolean('is_active', true);

        Shift::create($validated);

        return redirect()->route('admin.shifts.index')
            ->with('success', "Shift \"{$validated['name']}\" created.");
    }

    public function update(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i',
            'days_of_week'   => 'required|array|min:1',
            'days_of_week.*' => 'integer|between:1,7',
            'line_id'        => 'nullable|exists:lines,id',
            'is_active'      => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $shift->update($validated);

        return redirect()->route('admin.shifts.index')
            ->with('success', "Shift \"{$shift->name}\" updated.");
    }

    public function destroy(Shift $shift)
    {
        $name = $shift->name;
        $shift->delete();

        return redirect()->route('admin.shifts.index')
            ->with('success', "Shift \"{$name}\" deleted.");
    }
}
