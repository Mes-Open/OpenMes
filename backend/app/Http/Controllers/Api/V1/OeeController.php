<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexDowntimesRequest;
use App\Http\Requests\Api\V1\IndexOeeRecordsRequest;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\OeeRecord;
use App\Models\ProductionDowntime;
use App\Services\Production\DowntimeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OeeController extends Controller
{
    /** Rows the unpaginated GET /oee has always returned; also its default page size. */
    private const OEE_LEGACY_CAP = 100;

    /** Rows the unpaginated GET /downtimes has always returned; also its default page size. */
    private const DOWNTIME_LEGACY_CAP = 50;

    public function __construct(
        protected DowntimeService $downtimeService,
    ) {}

    /**
     * Get OEE records with filters.
     *
     * Without `page`/`per_page` this is the legacy response: the newest 100
     * records, no `meta`. Sending either switches to a paginated response.
     */
    public function index(IndexOeeRecordsRequest $request): JsonResponse
    {
        $query = OeeRecord::with($request->boolean('lean')
            ? ['line:id,name,code', 'shift:id,name']
            : ['line', 'shift']);

        if ($request->line_id) {
            $query->where('line_id', $request->line_id);
        }
        if ($request->filled('workstation_id')) {
            $query->where('workstation_id', $request->integer('workstation_id'));
        }
        // `record_date` is a DATE: compared as calendar days, no timezone
        // involved. Half-open (`< day after date_to`) keeps the column bare for
        // the (record_date, line_id) index and stays inclusive on SQLite, which
        // stores the cast value as 'Y-m-d 00:00:00' — a plain `<= 'Y-m-d'`
        // would drop the last day there.
        if ($request->date_from) {
            $query->where('record_date', '>=', $this->calendarDay($request, 'date_from'));
        }
        if ($request->date_to) {
            $query->where('record_date', '<', $this->calendarDay($request, 'date_to', 1));
        }

        // `id` breaks ties between records of the same day so pages never
        // overlap or skip a row.
        $query->orderByDesc('record_date')->orderByDesc('id');

        return $this->listResponse($request, $query, self::OEE_LEGACY_CAP);
    }

    /**
     * Get OEE for a specific line.
     */
    public function show(Line $line, Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 7);

        // -($days - 1) keeps the window inclusive of today (e.g. days=7 → today + 6 prior).
        $records = OeeRecord::where('line_id', $line->id)
            ->where('record_date', '>=', today()->subDays(max(0, $days - 1)))
            ->with('shift')
            ->orderByDesc('record_date')
            ->get();

        return response()->json(['data' => $records]);
    }

    /**
     * List downtime reasons.
     */
    public function reasons(): JsonResponse
    {
        return response()->json([
            'data' => DowntimeReason::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Start a downtime event.
     */
    public function startDowntime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'workstation_id' => 'nullable|exists:workstations,id',
            'downtime_reason_id' => 'required|exists:downtime_reasons,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $line = Line::findOrFail($validated['line_id']);

        $downtime = $this->downtimeService->start(
            $line,
            $validated['downtime_reason_id'],
            $request->user(),
            $validated['workstation_id'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Downtime started',
            'data' => $downtime->load('reason'),
        ], 201);
    }

    /**
     * Stop an active downtime event.
     */
    public function stopDowntime(ProductionDowntime $downtime): JsonResponse
    {
        if ($downtime->ended_at) {
            return response()->json(['message' => 'Downtime already stopped.'], 422);
        }

        $this->downtimeService->stop($downtime);

        return response()->json([
            'message' => 'Downtime stopped',
            'data' => $downtime->fresh()->load('reason'),
        ]);
    }

    /**
     * List downtimes with filters.
     *
     * Without `page`/`per_page` this is the legacy response: the newest 50
     * downtimes, no `meta`. Sending either switches to a paginated response.
     * Date filters match on the day the downtime started.
     */
    public function downtimes(IndexDowntimesRequest $request): JsonResponse
    {
        $query = ProductionDowntime::with($request->boolean('lean')
            ? [
                'reason:id,code,name,kind',
                'line:id,name,code',
                'workstation:id,name,code',
                'reportedBy:id,name,username',
            ]
            : ['reason', 'line', 'workstation', 'reportedBy']);

        if ($request->line_id) {
            $query->where('line_id', $request->line_id);
        }
        if ($request->filled('workstation_id')) {
            $query->where('workstation_id', $request->integer('workstation_id'));
        }
        // All three are plant-local days, as half-open ranges on the bare
        // column so the (line_id, started_at) / (workstation_id, started_at)
        // indexes stay usable. `date=X` is `date_from=X&date_to=X`, and returns
        // exactly what its old `started_at::date = X` did (see plantDayStart).
        if ($request->date) {
            $query->where('started_at', '>=', $this->plantDayStart($this->calendarDay($request, 'date')))
                ->where('started_at', '<', $this->plantDayStart($this->calendarDay($request, 'date', 1)));
        }
        if ($request->filled('date_from')) {
            $query->where('started_at', '>=', $this->plantDayStart($this->calendarDay($request, 'date_from')));
        }
        if ($request->filled('date_to')) {
            $query->where('started_at', '<', $this->plantDayStart($this->calendarDay($request, 'date_to', 1)));
        }
        if ($request->filled('active')) {
            $request->boolean('active')
                ? $query->whereNull('ended_at')
                : $query->whereNotNull('ended_at');
        }

        // `id` breaks ties between downtimes started in the same second so
        // pages never overlap or skip a row.
        $query->orderByDesc('started_at')->orderByDesc('id');

        return $this->listResponse($request, $query, self::DOWNTIME_LEGACY_CAP);
    }

    /**
     * The calendar day a date parameter names, optionally shifted by whole
     * days, as 'Y-m-d'. Only the date part counts; a time sent along with it
     * is ignored, as it always was for these filters.
     */
    private function calendarDay(IndexOeeRecordsRequest|IndexDowntimesRequest $request, string $key, int $addDays = 0): string
    {
        return $request->date($key)->addDays($addDays)->toDateString();
    }

    /**
     * Midnight that starts a plant-local day, as a bound for a stored timestamp.
     *
     * Timestamps such as `started_at` are stored as plant wall-clock time, with
     * no zone in the column (`timestamp without time zone`): the plant zone is
     * PHP's default zone (TimezoneRegistry, re-applied per request by
     * ApplyPlantTimezone), writers stamp with now() in it, and Eloquent writes a
     * Carbon as 'Y-m-d H:i:s' without converting it. Plant zone and storage zone
     * are therefore the same, the midnight below needs no conversion, and
     * `>= day 00:00 AND < next day 00:00` selects exactly the stops the old
     * `started_at::date = day` did.
     */
    private function plantDayStart(string $day): Carbon
    {
        return Carbon::parse($day, (string) config('app.timezone'))->startOfDay();
    }

    /**
     * The legacy capped list, or — when the caller sent `page`/`per_page` —
     * one page plus the `meta` block the other paginated v1 endpoints use.
     */
    private function listResponse(IndexOeeRecordsRequest|IndexDowntimesRequest $request, Builder $query, int $legacyCap): JsonResponse
    {
        if (! $request->wantsPagination()) {
            return response()->json([
                'data' => $query->limit($legacyCap)->get(),
            ]);
        }

        $page = $query->paginate($request->perPage($legacyCap), ['*'], 'page', $request->pageNumber());

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
