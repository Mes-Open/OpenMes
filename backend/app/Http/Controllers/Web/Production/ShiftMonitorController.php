<?php

namespace App\Http\Controllers\Web\Production;

use App\Http\Controllers\Concerns\ServesBothSections;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassifyDowntimeRequest;
use App\Http\Requests\EscalateDowntimeRequest;
use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\Workstation;
use App\Services\Production\ShiftMonitorService;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live shift monitor — one workstation's shift as it happens: state timeline,
 * hourly output against target, and the stops that still need a cause.
 *
 * Reachable from both the supervisor and admin sections; the same screen serves
 * both, so the routes differ only in prefix and the page takes its endpoints
 * from props rather than hardcoding either.
 */
class ShiftMonitorController extends Controller
{
    use ServesBothSections;

    public function __construct(private readonly ShiftMonitorService $monitor) {}

    public function index(Request $request): Response
    {
        [$workstation, $window] = $this->resolveTarget($request);

        if (! $workstation) {
            return Inertia::render('production/shift-monitor/Index', [
                'stations' => [],
                'snapshot' => null,
                'basePath' => $this->basePath('/shift-monitor'),
            ]);
        }

        return Inertia::render('production/shift-monitor/Index', [
            'stations' => $this->stations(),
            'selected' => [
                'workstationId' => $workstation->id,
                'shiftId' => $window->shift?->id,
                'date' => $window->start->toDateString(),
            ],
            'snapshot' => $this->monitor->snapshot($workstation, $window),
            // Reference data for the cause picker: sent with the page, not with
            // every polled refresh.
            'reasonGroups' => $this->monitor->reasonGroups(),
            'basePath' => $this->basePath('/shift-monitor'),
        ]);
    }

    /** Polled snapshot — same payload the page was rendered with. */
    public function check(Request $request): JsonResponse
    {
        [$workstation, $window] = $this->resolveTarget($request);

        if (! $workstation) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => $this->monitor->snapshot($workstation, $window),
            'selected' => [
                'workstationId' => $workstation->id,
                'shiftId' => $window->shift?->id,
                'date' => $window->start->toDateString(),
            ],
        ]);
    }

    /**
     * Give an automatically-logged stop its cause. This is the screen's whole
     * point: the machine reports that it stopped, a supervisor says why.
     */
    public function classify(ClassifyDowntimeRequest $request, ProductionDowntime $downtime): JsonResponse
    {
        $reason = DowntimeReason::findOrFail($request->validated()['downtime_reason_id']);

        $downtime->classify($reason, $request->user(), $request->validated()['notes'] ?? null);

        // The reason's name is a translatable source string like any other
        // seeded default — see ShiftMonitorService::reasonLabel().
        return response()->json(['message' => __('Cause set · :reason', ['reason' => __($reason->name)])]);
    }

    /**
     * Raise an issue against the stop so maintenance picks it up. Anchored to
     * the work order the station was running — an issue with no order has
     * nothing to be about.
     */
    public function escalate(EscalateDowntimeRequest $request, ProductionDowntime $downtime): JsonResponse
    {
        $validated = $request->validated();

        $batch = $this->batchRunningDuring($downtime);

        if (! $batch?->work_order_id) {
            // Better to refuse than to file the escalation against whatever ran
            // here last: an issue on a closed order is invisible to everyone
            // watching the live one, so the supervisor would be told it was
            // raised and nothing would come of it.
            throw ValidationException::withMessages([
                'note' => __('No work order was running at this station when the stop began, so there is nothing to escalate against.'),
            ]);
        }

        $issueType = IssueType::query()->where('is_active', true)->orderBy('id')->first();

        if (! $issueType) {
            throw ValidationException::withMessages([
                'note' => __('No issue type is configured.'),
            ]);
        }

        // One stop, one ticket. The button stays live while the drawer is open
        // and its busy flag is per-browser, so a missed toast or a second
        // supervisor on the same stop used to file duplicates that maintenance
        // could only reconcile by reading the description prose.
        $existing = Issue::where('production_downtime_id', $downtime->id)
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => __('Already escalated · issue #:id is still open', ['id' => $existing->id]),
            ]);
        }

        $issue = Issue::create([
            'work_order_id' => $batch->work_order_id,
            'production_downtime_id' => $downtime->id,
            'issue_type_id' => $issueType->id,
            'title' => __('Stop escalated from shift monitor'),
            'description' => trim(($validated['note'] ?? '')."\n".__('Downtime :id · :minutes min', [
                'id' => $downtime->id,
                'minutes' => $downtime->duration_minutes ?? 0,
            ])),
            'status' => Issue::STATUS_OPEN,
            'reported_by_id' => $request->user()->id,
            'reported_at' => now(),
        ]);

        return response()->json([
            'message' => __('Escalated to maintenance · issue #:id opened', ['id' => $issue->id]),
        ]);
    }

    /**
     * The batch this station was actually working when the stop began.
     *
     * Bounded by the stop's own time: a batch that started after it cannot have
     * caused it, and one that finished before it is somebody else's problem. An
     * unbounded "latest batch here" would happily return an order that closed
     * weeks ago on a station that has been idle since.
     */
    private function batchRunningDuring(ProductionDowntime $downtime): ?Batch
    {
        $startedAt = $downtime->started_at;

        return Batch::where('workstation_id', $downtime->workstation_id)
            ->whereIn('status', [Batch::STATUS_IN_PROGRESS, Batch::STATUS_DONE])
            ->where('started_at', '<=', $startedAt)
            ->where(fn ($q) => $q->whereNull('completed_at')->orWhere('completed_at', '>=', $startedAt))
            ->latest('started_at')
            ->first();
    }

    /**
     * Which station and shift the request is asking about. Falls back to the
     * first station that has any state history — landing on a station that has
     * never reported anything would show an empty screen for no reason.
     *
     * Both entries are null together or set together: a resolved workstation
     * always resolves a window, falling back to the generic one.
     *
     * @return array{0: ?Workstation, 1: ?ShiftWindow}
     */
    private function resolveTarget(Request $request): array
    {
        $workstation = $request->integer('workstation')
            ? Workstation::with('line')->find($request->integer('workstation'))
            : null;

        $workstation ??= Workstation::with('line')
            ->where('is_active', true)
            ->whereHas('states')
            ->orderBy('code')
            ->first()
            ?? Workstation::with('line')->where('is_active', true)->orderBy('code')->first();

        if (! $workstation) {
            return [null, null];
        }

        // A hand-edited ?date= shouldn't 500 the page — an unparseable one just
        // means "no date given", which lands on the shift running now.
        $date = null;
        if ($request->filled('date')) {
            try {
                $date = Carbon::parse($request->string('date')->toString())->startOfDay();
            } catch (\Throwable) {
                // keep null
            }
        }

        $shift = $request->integer('shift')
            ? Shift::find($request->integer('shift'))
            : null;

        // A date is a business date — the day the shift *opened* — not an
        // instant. Combining it with the current clock time (as this used to)
        // makes `occurrence()` roll back a day whenever now's time-of-day falls
        // before the shift's start: at 03:00, asking for the 27th's night shift
        // returned the 26th's, and every press of ‹ then skipped one.
        if ($shift) {
            return [$workstation, $date
                ? ShiftWindow::startingOn($shift, $date)
                : ShiftWindow::occurrence($shift, Carbon::now())];
        }

        if ($date) {
            // A day usually has several shifts, and "the 10th" alone does not
            // say which. If one is running right now and it opened on the day
            // asked for, that is the one meant: stepping to another station
            // keeps the date but drops the shift (shift ids belong to a line),
            // and answering with the day's *first* shift would quietly move a
            // supervisor watching the afternoon back to the morning.
            $live = ShiftWindow::at($workstation->line_id, Carbon::now());

            if ($live && $live->start->isSameDay($date)) {
                return [$workstation, $live];
            }

            // Nothing scheduled that day — a Sunday on a Mon–Fri line, say.
            // The answer is still *that day*: falling through to the shift
            // running now would show live data under the date the supervisor
            // asked for, and leave the ‹ › arrows apparently dead.
            return [$workstation, ShiftWindow::startingOnDate($workstation->line_id, $date)
                ?? ShiftWindow::fallbackAround($date->copy()->setTime(12, 0))];
        }

        // No date and no shift: whatever this line is running now.
        return [$workstation, ShiftWindow::current($workstation->line_id)];
    }

    /**
     * The stations the ‹ › selector pages through, in the order it walks them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stations(): array
    {
        return Workstation::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->all();
    }
}
