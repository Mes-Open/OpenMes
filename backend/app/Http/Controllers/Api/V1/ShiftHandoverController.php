<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftHandoverRequest;
use App\Models\Line;
use App\Models\ShiftHandover;
use App\Services\Packaging\ShiftHandoverCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST twin of the web Supervisor\ShiftHandoverController — exposes the live
 * shift balance + recent snapshots, and closes a shift (snapshots the balance).
 * Behind auth:sanctum + role:Supervisor|Admin (see routes/api.php).
 */
class ShiftHandoverController extends Controller
{
    public function __construct(private ShiftHandoverCalculator $calculator) {}

    /** GET /api/v1/shift-handover — live shift balance + active lines + recent snapshots. */
    public function index(Request $request): JsonResponse
    {
        $lineId = $request->integer('line_id') ?: null;

        return response()->json(['data' => [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'selected_line_id' => $lineId,
            'balance' => $this->calculator->compute($lineId),
            'recent' => $this->recentHandovers(),
        ]]);
    }

    /** POST /api/v1/shift-handover — close the shift; the server-computed balance is the snapshot. */
    public function store(ShiftHandoverRequest $request): JsonResponse
    {
        $lineId = $request->integer('line_id') ?: null;

        // Recompute server-side — the snapshot is the source of truth, not any
        // figure the client may have displayed.
        $b = $this->calculator->compute($lineId);

        $handover = ShiftHandover::create([
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

        return response()->json([
            'data' => $handover->load(['line:id,name', 'confirmedBy:id,name']),
        ], 201);
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
