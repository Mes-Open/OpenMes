<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\LineStatus;
use Illuminate\Http\Request;

class LineStatusController extends Controller
{
    /** Global statuses (line_id = null) management page */
    public function index()
    {
        $globalStatuses = LineStatus::global()->get();

        return view('admin.line-statuses.index', compact('globalStatuses'));
    }

    /** Store a new global status */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'color'      => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['line_id']    = null;
        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        if ($validated['is_default']) {
            // Only one default global status at a time
            LineStatus::whereNull('line_id')->update(['is_default' => false]);
        }

        LineStatus::create($validated);

        return back()->with('success', 'Status created.');
    }

    /** Update a global status */
    public function update(Request $request, LineStatus $lineStatus)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'color'      => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        if ($validated['is_default']) {
            LineStatus::whereNull('line_id')->where('id', '!=', $lineStatus->id)
                ->update(['is_default' => false]);
        }

        $lineStatus->update($validated);

        return back()->with('success', 'Status updated.');
    }

    /** Delete a status */
    public function destroy(LineStatus $lineStatus)
    {
        // Clear FK on work orders (handled by nullOnDelete in migration, but let's be explicit)
        $lineStatus->workOrders()->update(['line_status_id' => null]);
        $lineStatus->delete();

        return back()->with('success', 'Status deleted.');
    }

    /** Per-line statuses: store a new status for a specific line */
    public function storeForLine(Request $request, Line $line)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'color'      => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['line_id']    = $line->id;
        $validated['is_default'] = false;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        LineStatus::create($validated);

        return back()->with('success', 'Status created for line.');
    }
}
