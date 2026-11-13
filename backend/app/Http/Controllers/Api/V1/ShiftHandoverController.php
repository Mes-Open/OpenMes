<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListShiftHandoversRequest;
use App\Http\Requests\ShiftHandoverRequest;
use App\Models\Line;
use App\Models\ShiftHandover;
use App\Services\Packaging\ShiftHandoverCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * REST twin of the web Supervisor\ShiftHandoverController — exposes the live
 * shift balance + recent snapshots, one snapshot by id, and closes a shift
 * (snapshots the balance). Behind auth:sanctum + role:Supervisor|Admin (see
 * routes/api.php).
 */
class ShiftHandoverController extends Controller
{
    public function __construct(private ShiftHandoverCalculator $calculator) {}

    /**
     * GET /api/v1/shift-handover — live shift balance + active lines + recent snapshots.
     *
     * `line_id` scopes the balance only (as on the web page, `recent` spans all
     * lines). `recent_line_id` and `business_date_from`/`_to` filter `recent`;
     * `per_page` opts it into pagination and adds `recent_meta`. Without
     * `per_page` the list stays capped at the 20 most recent handovers.
     */
    public function index(ListShiftHandoversRequest $request): JsonResponse
    {
        $lineId = $request->integer('line_id') ?: null;

        $data = [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'selected_line_id' => $lineId,
            'balance' => $this->calculator->compute($lineId),
        ];

        $query = $this->recentQuery($request);

        if ($request->filled('per_page')) {
            $page = $query->paginate($request->integer('per_page'));
            $data['recent'] = collect($page->items())->map(fn (ShiftHandover $h) => $this->present($h))->all();
            $data['recent_meta'] = [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ];
        } else {
            $data['recent'] = $query->limit(20)->get()->map(fn (ShiftHandover $h) => $this->present($h))->all();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/shift-handover/{shiftHandover} — one handover snapshot.
     * Route model binding goes through the tenant global scope, so another
     * tenant's handover resolves to 404.
     */
    public function show(ShiftHandover $shiftHandover): JsonResponse
    {
        $shiftHandover->load($this->relations());

        return response()->json(['data' => $this->present($shiftHandover)]);
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

    private function recentQuery(ListShiftHandoversRequest $request): Builder
    {
        return ShiftHandover::with($this->relations())
            ->when($request->filled('recent_line_id'), fn (Builder $q) => $q->where('line_id', $request->integer('recent_line_id')))
            ->when($request->filled('business_date_from'), fn (Builder $q) => $q->whereDate('business_date', '>=', $request->input('business_date_from')))
            ->when($request->filled('business_date_to'), fn (Builder $q) => $q->whereDate('business_date', '<=', $request->input('business_date_to')))
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id');
    }

    /**
     * A handover is a historical record, so its line, shift and confirming
     * user are resolved even when they have since been soft-deleted.
     */
    private function relations(): array
    {
        return [
            'line' => fn ($q) => $q->withTrashed()->select('id', 'name'),
            'shift' => fn ($q) => $q->withTrashed()->select('id', 'name'),
            'confirmedBy' => fn ($q) => $q->withTrashed()->select('id', 'name'),
        ];
    }

    /**
     * One handover as exposed by the API. The keys the web supervisor page
     * shows come first, in the same formats; the rest is the full snapshot.
     */
    private function present(ShiftHandover $h): array
    {
        return [
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
            'shift_end' => $h->shift_end?->format('Y-m-d H:i'),
            'line_id' => $h->line_id,
            'shift_id' => $h->shift_id,
            // Current shift name, falling back to the name captured in the snapshot.
            'shift_name' => $h->shift?->name ?? data_get($h->breakdown, 'shift.name'),
            'scrap_qty' => $h->scrap_qty,
            'wip_open_pallets_qty' => $h->wip_open_pallets_qty,
            'wip_unpacked_qty' => $h->wip_unpacked_qty,
            'discrepancies' => $h->discrepancies ?? [],
            'breakdown' => $h->breakdown ?? [],
            'notes' => $h->notes,
            'confirmed_by_id' => $h->confirmed_by,
        ];
    }
}
