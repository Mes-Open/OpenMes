<?php

namespace App\Http\Controllers\Web\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftHandoverRequest;
use App\Models\Line;
use App\Models\ShiftHandover;
use App\Services\Packaging\ShiftHandoverCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftHandoverController extends Controller
{
    public function __construct(private ShiftHandoverCalculator $calculator) {}

    public function index(Request $request)
    {
        $lineId = $request->integer('line_id') ?: null;

        return Inertia::render('supervisor/shift-handover/Index', [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'selectedLineId' => $lineId,
            'balance' => $this->calculator->compute($lineId),
            'recent' => $this->recentHandovers(),
        ]);
    }

    public function preview(Request $request)
    {
        $lineId = $request->integer('line_id') ?: null;

        return response()->json(['balance' => $this->calculator->compute($lineId)]);
    }

    public function store(ShiftHandoverRequest $request)
    {
        $lineId = $request->integer('line_id') ?: null;

        // Recompute server-side — the snapshot is the source of truth, not any
        // figure the client may have displayed.
        $b = $this->calculator->compute($lineId);

        ShiftHandover::create([
            'shift_id' => $b['shift_id'],
            'line_id' => $b['line_id'],
            'business_date' => $b['window']['business_date'],
            'shift_start' => Carbon::parse($b['window']['start']),
            'shift_end' => Carbon::parse($b['window']['end']),
            'produced_qty' => $b['produced_qty'],
            'scrap_qty' => $b['scrap_qty'],
            'good_qty' => $b['good_qty'],
            'packed_qty' => $b['packed_qty'],
            'wip_open_pallets_qty' => $b['wip_open_pallets_qty'],
            'wip_unpacked_qty' => $b['wip_unpacked_qty'],
            'shipped_qty' => $b['shipped_qty'],
            'discrepancies' => $b['discrepancies'],
            'breakdown' => [
                'wip_total_qty' => $b['wip_total_qty'],
                'open_pallets_count' => $b['wip_open_pallets_count'],
                'open_pallets' => $b['open_pallets'],
                'shift' => $b['shift'],
            ],
            'notes' => $request->input('notes'),
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        return redirect()->route('supervisor.shift-handover.index', $lineId ? ['line_id' => $lineId] : [])
            ->with('success', __('Shift closed and snapshot saved.'));
    }

    private function recentHandovers()
    {
        return ShiftHandover::with(['line:id,name', 'confirmedBy:id,name'])
            ->orderByDesc('confirmed_at')
            ->limit(20)
            ->get()
            ->map(fn (ShiftHandover $h) => [
                'id' => $h->id,
                'business_date' => $h->business_date?->toDateString(),
                'shift_start' => $h->shift_start?->format('Y-m-d H:i'),
                'line_name' => $h->line?->name,
                'produced_qty' => $h->produced_qty,
                'good_qty' => $h->good_qty,
                'packed_qty' => $h->packed_qty,
                'shipped_qty' => $h->shipped_qty,
                'confirmed_by' => $h->confirmedBy?->name,
                'confirmed_at' => $h->confirmed_at?->format('Y-m-d H:i'),
            ]);
    }
}
