<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use Illuminate\Http\Request;

class WorkstationController extends Controller
{
    /**
     * Workstation production view — flat table with inline quantity entry.
     */
    public function index(Request $request)
    {
        $lineId = $request->session()->get('selected_line_id')
            ?? $request->query('line');

        if (!$lineId && auth()->user()->account_type === 'workstation') {
            $lineId = auth()->user()->workstation?->line_id;
        }

        if (!$lineId) {
            return redirect()->route('operator.select-line');
        }

        $request->session()->put('selected_line_id', $lineId);

        $line = Line::with(['viewColumns', 'viewTemplate'])->findOrFail($lineId);

        $query = WorkOrder::where('line_id', $lineId)
            ->whereNotIn('status', [WorkOrder::STATUS_REJECTED, WorkOrder::STATUS_CANCELLED])
            ->with(['productType'])
            ->orderByRaw("CASE WHEN status = 'IN_PROGRESS' THEN 0 WHEN status IN ('PENDING','ACCEPTED') THEN 1 ELSE 2 END")
            ->orderBy('priority', 'desc')
            ->orderBy('due_date', 'asc');

        // Week filter
        $weekFilter = $request->query('week');
        $availableWeeks = WorkOrder::where('line_id', $lineId)
            ->whereNotIn('status', [WorkOrder::STATUS_REJECTED, WorkOrder::STATUS_CANCELLED])
            ->whereNotNull('week_number')
            ->distinct()
            ->orderBy('week_number')
            ->pluck('week_number');

        if ($weekFilter && $weekFilter !== 'all') {
            $query->where('week_number', (int) $weekFilter);
        }

        // Search
        $search = $request->query('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'ilike', "%{$search}%")
                  ->orWhereHas('productType', fn($pt) => $pt->where('name', 'ilike', "%{$search}%"))
                  ->orWhereRaw("extra_data::text ilike ?", ["%{$search}%"]);
            });
        }

        $workOrders = $query->get();

        $issueTypes = IssueType::where('is_active', true)->orderBy('name')->get();

        // Build all available columns: system fields + extra_data keys
        $allColumns = $this->buildAllColumns($workOrders, $line);

        // Load active shifts for shift columns
        $shifts = Shift::active()->get();

        // Load today's shift entries for these work orders
        $today = now()->toDateString();
        $woIds = $workOrders->pluck('id');
        $shiftEntries = WorkOrderShiftEntry::whereIn('work_order_id', $woIds)
            ->where('production_date', $today)
            ->get()
            ->groupBy(fn($e) => $e->work_order_id . '_' . $e->shift_id);

        return view('operator.workstation', compact(
            'workOrders', 'line', 'availableWeeks', 'weekFilter', 'search',
            'issueTypes', 'allColumns', 'shifts', 'shiftEntries', 'today'
        ));
    }

    /**
     * Build complete column list: system fields first, then all extra_data keys.
     */
    private function buildAllColumns($workOrders, Line $line): array
    {
        // System columns — always available
        $systemColumns = [
            ['label' => 'Order No',    'key' => 'order_no',    'source' => 'field',        'default' => true],
            ['label' => 'Product',     'key' => 'product_name','source' => 'product_type', 'default' => true],
            ['label' => 'Description', 'key' => 'description', 'source' => 'field',        'default' => false],
            ['label' => 'Status',      'key' => 'status',      'source' => 'field',        'default' => true],
            ['label' => 'Priority',    'key' => 'priority',    'source' => 'field',        'default' => false],
            ['label' => 'Week',        'key' => 'week_number', 'source' => 'field',        'default' => false],
            ['label' => 'Due Date',    'key' => 'due_date',    'source' => 'field',        'default' => false],
        ];

        // Extra data columns — auto-detected from work orders
        $extraKeys = collect();
        foreach ($workOrders as $wo) {
            if (is_array($wo->extra_data)) {
                foreach (array_keys($wo->extra_data) as $key) {
                    if (!$extraKeys->contains($key)) {
                        $extraKeys->push($key);
                    }
                }
            }
        }

        $extraColumns = $extraKeys->map(fn ($key) => [
            'label'   => str_replace('_', ' ', ucfirst($key)),
            'key'     => $key,
            'source'  => 'extra_data',
            'default' => true, // extra_data columns shown by default
        ])->values()->toArray();

        return array_merge($systemColumns, $extraColumns);
    }

    /**
     * Start production on a work order (set IN_PROGRESS).
     */
    public function start(Request $request, WorkOrder $workOrder)
    {
        $lineId = $request->session()->get('selected_line_id');

        if ($workOrder->line_id != $lineId) {
            return back()->with('error', 'Work order does not belong to this line.');
        }

        if (!in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED])) {
            return back()->with('error', 'Work order cannot be started from current status.');
        }

        $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        return back()->with('success', "Production started: {$workOrder->order_no}");
    }

    /**
     * Add produced quantity to a work order.
     * Auto-starts if PENDING/ACCEPTED, auto-completes if produced >= planned.
     */
    public function complete(Request $request, WorkOrder $workOrder)
    {
        $lineId = $request->session()->get('selected_line_id');

        if ($workOrder->line_id != $lineId) {
            return back()->with('error', 'Work order does not belong to this line.');
        }

        if ($workOrder->status === WorkOrder::STATUS_DONE) {
            return back()->with('error', 'Work order is already completed.');
        }

        $validated = $request->validate([
            'produced_qty' => 'required|numeric|min:1',
        ]);

        $addedQty = (float) $validated['produced_qty'];
        $newProduced = (float) $workOrder->produced_qty + $addedQty;
        $planned = (float) $workOrder->planned_qty;

        $updates = ['produced_qty' => $newProduced];

        // Auto-start if not yet in progress
        if (in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED])) {
            $updates['status'] = WorkOrder::STATUS_IN_PROGRESS;
        }

        // Auto-complete if produced >= planned
        if ($newProduced >= $planned) {
            $updates['status'] = WorkOrder::STATUS_DONE;
            $updates['completed_at'] = now();
        }

        $workOrder->update($updates);

        $remaining = max(0, $planned - $newProduced);
        return back()->with('success', "{$workOrder->order_no}: +{$addedQty} produced (remaining: {$remaining})");
    }

    /**
     * Record production quantity for a specific shift.
     */
    public function shiftEntry(Request $request, WorkOrder $workOrder)
    {
        $lineId = $request->session()->get('selected_line_id');

        if ($workOrder->line_id != $lineId) {
            return back()->with('error', 'Work order does not belong to this line.');
        }

        if ($workOrder->status === WorkOrder::STATUS_DONE) {
            return back()->with('error', 'Work order is already completed.');
        }

        $validated = $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'quantity' => 'required|numeric|min:1',
        ]);

        $today = now()->toDateString();
        $qty = (float) $validated['quantity'];

        // Find or create shift entry for today, then add quantity
        $entry = WorkOrderShiftEntry::firstOrCreate(
            [
                'work_order_id'   => $workOrder->id,
                'shift_id'        => $validated['shift_id'],
                'production_date' => $today,
            ],
            [
                'quantity' => 0,
                'user_id'  => auth()->id(),
            ]
        );

        $entry->increment('quantity', $qty);
        $entry->update(['user_id' => auth()->id()]);

        // Recalculate total produced from all shift entries
        $totalProduced = WorkOrderShiftEntry::where('work_order_id', $workOrder->id)
            ->sum('quantity');

        $planned = (float) $workOrder->planned_qty;
        $updates = ['produced_qty' => $totalProduced];

        // Auto-start
        if (in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED])) {
            $updates['status'] = WorkOrder::STATUS_IN_PROGRESS;
        }

        // Auto-complete
        if ($totalProduced >= $planned) {
            $updates['status'] = WorkOrder::STATUS_DONE;
            $updates['completed_at'] = now();
        }

        $workOrder->update($updates);

        $shift = Shift::find($validated['shift_id']);
        $remaining = max(0, $planned - $totalProduced);
        return back()->with('success', "{$workOrder->order_no} [{$shift->code}]: +{$qty} (remaining: {$remaining})");
    }
}
