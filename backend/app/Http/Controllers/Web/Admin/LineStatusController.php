<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Http\Requests\LineStatusRequest;
use App\Http\Requests\ReorderLineStatusesRequest;
use App\Http\Requests\StoreLineStatusForLineRequest;
use App\Models\Line;
use App\Models\LineStatus;
use Inertia\Inertia;

class LineStatusController extends Controller
{
    use StaysOnList;

    /** Global statuses (line_id = null). Rows live-sync via the
     *  `line_statuses_global` shape, so the list itself takes no props. */
    public function index()
    {
        return Inertia::render('admin/line-statuses/Index', [
            // Only the create/edit drawer needs this, so it's counted when the
            // drawer asks rather than on every visit to the list.
            'nextSortOrder' => Inertia::optional(fn () => LineStatus::whereNull('line_id')->count() + 1),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/line-statuses/Create', [
            // The set is renumbered 1..n on every write, so the next position
            // is simply one past the end.
            'nextSortOrder' => LineStatus::whereNull('line_id')->count() + 1,
        ]);
    }

    public function edit(LineStatus $lineStatus)
    {
        $this->assertGlobal($lineStatus);

        return Inertia::render('admin/line-statuses/Edit', [
            'lineStatus' => $lineStatus->only('id', 'name', 'color', 'sort_order', 'is_default'),
        ]);
    }

    /** Store a new global status */
    public function store(LineStatusRequest $request)
    {
        $values = $request->values();

        if ($values['is_default']) {
            // Only one default global status at a time.
            LineStatus::whereNull('line_id')->update(['is_default' => false]);
        }

        $status = LineStatus::create([
            ...$values,
            'line_id' => null,
            // The column is NOT NULL; this is only what the row is born with,
            // and placeGlobalAt renumbers the whole set on the next line.
            'sort_order' => $values['sort_order'] ?? 0,
        ]);
        LineStatus::placeGlobalAt($status, $values['sort_order']);

        return $this->saved($request, redirect()->route('admin.line-statuses.index'), 'Status created.');
    }

    /** Update a global status */
    public function update(LineStatusRequest $request, LineStatus $lineStatus)
    {
        $this->assertGlobal($lineStatus);

        $values = $request->values();

        if ($values['is_default']) {
            LineStatus::whereNull('line_id')->where('id', '!=', $lineStatus->id)
                ->update(['is_default' => false]);
        }

        // `sort_order` is settled by placeGlobalAt, which renumbers the whole
        // set — writing it here first would make the row briefly share a
        // position with its new neighbour.
        $lineStatus->update([...$values, 'sort_order' => $lineStatus->sort_order]);
        LineStatus::placeGlobalAt($lineStatus, $values['sort_order']);

        return $this->saved($request, redirect()->route('admin.line-statuses.index'), 'Status updated.');
    }

    /**
     * These two edit the *global* set: they clear `is_default` across it and
     * hand the row to `placeGlobalAt`, which renumbers every global status. A
     * line-scoped id reaching them would splice a status belonging to one line
     * into that sequence — the same damage `ReorderLineStatusesRequest` already
     * refuses. Route-model binding will hand over any id, so it is checked here.
     */
    private function assertGlobal(LineStatus $lineStatus): void
    {
        abort_unless($lineStatus->line_id === null, 404);
    }

    /**
     * Drag-to-reorder from the list: the ids in their new order.
     *
     * 204, not a redirect. The rows are a synced collection, so the renumbering
     * reaches the list over the websocket on its own — an Inertia visit would
     * re-render a page that is already correct, and remount the toast provider
     * that was about to confirm the drop.
     */
    public function reorder(ReorderLineStatusesRequest $request)
    {
        LineStatus::applyGlobalOrder($request->validated()['ids']);

        return response()->noContent();
    }

    /**
     * Delete a status.
     *
     * `back()`, not a redirect to the list: this is reachable from the list and
     * from a line's detail page, and the caller should stay where it was.
     */
    public function destroy(LineStatus $lineStatus)
    {
        $wasGlobal = $lineStatus->line_id === null;

        // Clear FK on work orders (handled by nullOnDelete in migration, but let's be explicit)
        $lineStatus->workOrders()->update(['line_status_id' => null]);
        $lineStatus->delete();

        if ($wasGlobal) {
            LineStatus::resequenceGlobal();
        }

        return back()->with('success', 'Status deleted.');
    }

    /** Per-line statuses: store a new status for a specific line */
    public function storeForLine(StoreLineStatusForLineRequest $request, Line $line)
    {
        LineStatus::create([
            ...$request->validated(),
            'line_id' => $line->id,
            'is_default' => false,
            'sort_order' => $request->validated()['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Status created for line.');
    }
}
