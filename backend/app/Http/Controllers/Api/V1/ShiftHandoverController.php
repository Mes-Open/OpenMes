<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShiftHandoverHistoryRequest;
use App\Http\Requests\ShiftHandoverRequest;
use App\Models\Line;
use App\Models\ShiftHandover;
use App\Services\Packaging\ShiftHandoverCalculator;
use App\Support\ShiftHandoverPresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST twin of the web Supervisor\ShiftHandoverController — exposes the live
 * shift balance + recent snapshots, the filterable snapshot history, one
 * snapshot by id, and closes a shift (snapshots the balance).
 * Behind auth:sanctum + role:Supervisor|Admin (see routes/api.php).
 */
class ShiftHandoverController extends Controller
{
    public function __construct(private ShiftHandoverCalculator $calculator) {}

    /**
     * GET /api/v1/shift-handover — live shift balance + active lines + the 20
     * most recent snapshots. `line_id` scopes the balance only; `recent` spans
     * all lines, as on the web page.
     */
    public function index(Request $request): JsonResponse
    {
        // Lenient on purpose: junk such as `line_id=all` falls back to all lines.
        $lineId = $request->integer('line_id') ?: null;

        return response()->json(['data' => [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'selected_line_id' => $lineId,
            'balance' => $this->calculator->compute($lineId),
            'recent' => $this->recentHandovers(),
        ]]);
    }

    /**
     * GET /api/v1/shift-handover/history — paginated snapshots, newest first,
     * filterable by line and business date range. Does not compute the live
     * balance, so paging through history stays cheap.
     */
    public function history(ShiftHandoverHistoryRequest $request): JsonResponse
    {
        $page = ShiftHandover::with(ShiftHandoverPresenter::relations())
            ->when($request->filled('line_id'), fn ($q) => $q->where('line_id', $request->integer('line_id')))
            ->when($request->filled('business_date_from'), fn ($q) => $q->where('business_date', '>=', $request->input('business_date_from')))
            // Inclusive upper bound as "before the next day": stays index-friendly
            // and also matches dates stored with a 00:00:00 time part.
            ->when($request->filled('business_date_to'), fn ($q) => $q->where(
                'business_date', '<', Carbon::parse($request->input('business_date_to'))->addDay()->toDateString()
            ))
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => collect($page->items())->map(ShiftHandoverPresenter::present(...))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/shift-handover/{shiftHandover} — one snapshot. Route model
     * binding goes through the tenant global scope, so another tenant's
     * handover resolves to 404.
     */
    public function show(ShiftHandover $shiftHandover): JsonResponse
    {
        $shiftHandover->load(ShiftHandoverPresenter::relations());

        return response()->json(['data' => ShiftHandoverPresenter::present($shiftHandover)]);
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

    private function recentHandovers(): array
    {
        return ShiftHandover::with(ShiftHandoverPresenter::relations())
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(ShiftHandoverPresenter::present(...))
            ->all();
    }
}
