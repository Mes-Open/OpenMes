<?php

namespace App\Services\Production;

use App\Enums\DowntimeKind;
use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\Issue;
use App\Models\MachineEvent;
use App\Models\ProductionDowntime;
use App\Models\QualityCheck;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read model behind the shift monitor: one workstation, one shift, resolved
 * into everything the screen draws.
 *
 * Three raw sources feed it and none of them is shaped like the screen:
 *
 *  - `workstation_states` — a sparse timeline of state slices. Clipped to the
 *    shift, split at hour boundaries, and (for RUNNING) subdivided against the
 *    counter feed so a slice that produced below its nameplate rate reads as a
 *    speed loss rather than as healthy running.
 *  - `machine_events` counters — irregular pulses, bucketed to minutes for the
 *    hourly totals and to 2-minute samples for the rate chart.
 *  - `production_downtimes` — the stops, carrying the cause a supervisor
 *    assigned (or the `needs_reason` flag saying nobody has yet).
 *
 * Everything is derived per request. Nothing here writes.
 */
class ShiftMonitorService
{
    /** A RUNNING minute producing below this share of nameplate is a speed loss. */
    private const SLOW_THRESHOLD = 0.85;

    /** Rate-chart window: 28 samples of 2 minutes each, newest at the right edge. */
    private const CHART_SAMPLES = 28;

    private const CHART_SAMPLE_MINUTES = 2;

    /** Machine states rendered as one timeline colour. */
    private const SEGMENT_KIND = [
        WorkstationState::RUNNING => 'run',
        WorkstationState::SETUP => 'plan',
        WorkstationState::CLEANING => 'plan',
        WorkstationState::MAINTENANCE => 'plan',
        WorkstationState::STOPPED => 'down',
        WorkstationState::FAULT => 'down',
        WorkstationState::WAITING => 'down',
        WorkstationState::IDLE => 'idle',
    ];

    /**
     * The whole screen for one workstation and shift.
     *
     * @param  Workstation  $workstation  the station being watched
     * @param  ShiftWindow  $window  the shift occurrence, already pinned to dates
     * @return array<string, mixed>
     */
    public function snapshot(Workstation $workstation, ShiftWindow $window): array
    {
        $now = Carbon::now();
        // A shift that already ended is shown complete, not frozen mid-air.
        $asOf = $now->greaterThan($window->end) ? $window->end->copy() : $now;
        $isLive = $window->contains($now);

        $slices = $this->stateSlices($workstation, $window);
        $counters = $this->counterMinutes($workstation, $window);
        $downtimes = $this->downtimes($workstation, $window, $slices);

        $idealPerHour = (float) ($workstation->ideal_rate_per_hour ?? 0);
        $idealPerMin = $idealPerHour / 60;

        $segments = $this->buildSegments($slices, $counters, $downtimes, $idealPerMin, $window, $asOf);
        $hours = $this->buildHours($segments, $counters, $window, $asOf, $idealPerMin);
        $totals = $this->totals($segments, $counters, $window, $asOf);
        $oee = $this->oee($totals, $idealPerMin);

        return [
            'station' => $this->station($workstation),
            'shift' => $this->shift($window, $totals, $hours, $isLive, $oee),
            'batch' => $this->currentBatch($workstation, $window),
            'previousBatches' => $this->previousBatches($workstation, $window),
            'hours' => $hours,
            'chart' => $this->chart($counters, $segments, $window, $asOf, $idealPerMin),
            'events' => $this->eventPins($workstation, $window),
            'attention' => $this->attention($segments),
            'summary' => $this->summary($segments, $totals, $workstation),
            'analysis' => $this->analysis($totals, $oee),
            'clock' => ['iso' => $now->toIso8601String()],
        ];
    }

    /**
     * Every station on a line, one row each, for the same shift.
     *
     * The detail snapshot answers "explain this machine's shift". This answers
     * the question asked before that one — "which of my machines is in
     * trouble" — so it carries the timeline and the three numbers worth
     * comparing across stations, and nothing that only makes sense once you
     * have picked one (batches, the rate chart, the event pins).
     *
     * Read in three queries for the whole line rather than three per station:
     * the counter feed alone is thousands of rows a shift, and this page shows
     * six of them at once and re-fetches on every push.
     *
     * @param  Collection<int, Workstation>  $workstations
     * @return array<int, array<string, mixed>>
     */
    public function fleet(Collection $workstations, ShiftWindow $window): array
    {
        $now = Carbon::now();
        $asOf = $now->greaterThan($window->end) ? $window->end->copy() : $now;
        $ids = $workstations->pluck('id');

        $statesByStation = WorkstationState::whereIn('workstation_id', $ids)
            ->where('started_at', '<', $window->end)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $window->start))
            ->get()
            ->groupBy('workstation_id');

        $countersByStation = MachineEvent::whereIn('workstation_id', $ids)
            ->where('event_type', MachineEvent::TYPE_COUNTER)
            ->where('event_timestamp', '>=', $window->start)
            ->where('event_timestamp', '<', $window->end)
            ->toBase()
            ->get(['workstation_id', 'event_timestamp', 'payload'])
            ->groupBy('workstation_id');

        $downtimesByStation = ProductionDowntime::with('reason')
            ->whereIn('workstation_id', $ids)
            ->where('started_at', '<', $window->end)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $window->start))
            ->get()
            ->groupBy('workstation_id');

        return $workstations->map(function (Workstation $workstation) use (
            $statesByStation, $countersByStation, $downtimesByStation, $window, $asOf
        ) {
            $slices = $this->sliceRows($statesByStation->get($workstation->id) ?? collect(), $window);
            $counters = $this->counterMinutesFrom($countersByStation->get($workstation->id) ?? collect(), $window);
            $downtimes = $this->downtimeRows($downtimesByStation->get($workstation->id) ?? collect(), $slices);

            $idealPerMin = (float) ($workstation->ideal_rate_per_hour ?? 0) / 60;
            $segments = $this->buildSegments($slices, $counters, $downtimes, $idealPerMin, $window, $asOf);
            $totals = $this->totals($segments, $counters, $window, $asOf);
            $oee = $this->oee($totals, $idealPerMin);
            $elapsed = max(1, (int) floor($window->start->diffInSeconds($asOf) / 60));

            return [
                'id' => $workstation->id,
                'code' => $workstation->code,
                'name' => $workstation->name,
                // Positioned as a share of the whole shift, not of an hour: one
                // row is the shift end to end, which is what makes two stations
                // comparable at a glance.
                'span' => $window->durationMinutes(),
                'elapsed' => $elapsed,
                'segments' => $segments,
                'state' => $this->currentState($slices, $asOf),
                'produced' => $totals['produced'],
                'target' => $idealPerMin > 0
                    ? (int) round($idealPerMin * ($totals['minutes']['run'] + $totals['minutes']['slow'] + $totals['minutes']['down']))
                    : null,
                'oee' => $oee['oee'],
                'unclassified' => $totals['unclassified'],
                'ratePerMinute' => round($idealPerMin, 2),
            ];
        })->values()->all();
    }

    /**
     * What the station is doing right now — the state of the slice covering
     * `asOf`, or null when nothing has been reported for it at all.
     *
     * @param  Collection<int, array{state: string, from: Carbon, to: Carbon, id: int}>  $slices
     */
    private function currentState(Collection $slices, Carbon $asOf): ?string
    {
        return $slices->last(fn (array $slice) => $slice['from']->lessThanOrEqualTo($asOf))['state'] ?? null;
    }

    // ─────────────────────────── raw sources ───────────────────────────

    /**
     * State slices overlapping the shift, clipped to its boundaries. The open
     * slice (ended_at null) is closed at `asOf` by the caller's clipping.
     *
     * @return Collection<int, array{state: string, from: Carbon, to: Carbon, id: int}>
     */
    private function stateSlices(Workstation $workstation, ShiftWindow $window): Collection
    {
        return $this->sliceRows(
            WorkstationState::where('workstation_id', $workstation->id)
                ->where('started_at', '<', $window->end)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $window->start))
                ->orderBy('started_at')
                ->get(),
            $window,
        );
    }

    /**
     * The same clipping, over rows already in memory.
     *
     * Split out so the fleet view can read every station's states in one query
     * and derive each timeline from the result, rather than running this query
     * once per station on a screen that shows six of them.
     *
     * @param  Collection<int, WorkstationState>  $rows
     * @return Collection<int, array{state: string, from: Carbon, to: Carbon, id: int}>
     */
    private function sliceRows(Collection $rows, ShiftWindow $window): Collection
    {
        return $rows
            ->sortBy('started_at')
            ->values()
            ->map(fn (WorkstationState $s) => [
                'id' => $s->id,
                'state' => $s->state,
                'from' => $s->started_at->copy()->max($window->start),
                'to' => ($s->ended_at ?? Carbon::now())->copy()->min($window->end),
            ])
            ->pipe(fn (Collection $slices) => $this->withoutOverlap($slices));
    }

    /**
     * Trim any slice that starts before the previous one ended.
     *
     * A workstation is in exactly one state at a time, and the state machine
     * maintains that by closing the current slice before opening the next. But
     * the reader must not silently produce nonsense if the invariant is ever
     * broken — two overlapping slices would have their shared minutes counted
     * twice, which shows up as an hourly target several times the nameplate
     * rate rather than as an obvious error.
     *
     * @param  Collection<int, array{state: string, from: Carbon, to: Carbon, id: int}>  $slices
     * @return Collection<int, array{state: string, from: Carbon, to: Carbon, id: int}>
     */
    private function withoutOverlap(Collection $slices): Collection
    {
        $clipped = [];
        $previousEnd = null;

        foreach ($slices as $slice) {
            if ($previousEnd && $slice['from']->lessThan($previousEnd)) {
                $slice['from'] = $previousEnd->copy();
            }
            if ($slice['to']->lessThanOrEqualTo($slice['from'])) {
                continue;
            }

            $clipped[] = $slice;
            $previousEnd = $slice['to'];
        }

        return collect($clipped);
    }

    /**
     * Produced pieces per minute-offset from shift start. Counter events are
     * irregular pulses; bucketing them by minute gives a dense series that both
     * the hourly totals and the speed-loss detection can index directly.
     *
     * @return array{good: array<int, float>, reject: array<int, float>}
     */
    private function counterMinutes(Workstation $workstation, ShiftWindow $window): array
    {
        $good = [];
        $reject = [];

        // toBase(): a counter pulse is read once and collapsed into the minute
        // map, so nothing survives hydration — and a piece-level feed puts
        // thousands of these in an 8-hour shift, on a path that re-runs on
        // every push. Unordered on purpose: the result is a map, not a list.
        $start = $window->start;

        $rows = MachineEvent::where('workstation_id', $workstation->id)
            ->where('event_type', MachineEvent::TYPE_COUNTER)
            ->where('event_timestamp', '>=', $start)
            ->where('event_timestamp', '<', $window->end)
            ->toBase()
            ->get(['event_timestamp', 'payload']);

        return $this->counterMinutesFrom($rows, $window);
    }

    /**
     * The same bucketing, over pulses already read. See sliceRows().
     *
     * @param  Collection<int, object>  $rows  each with event_timestamp + payload
     * @return array{good: array<int, float>, reject: array<int, float>}
     */
    private function counterMinutesFrom(Collection $rows, ShiftWindow $window): array
    {
        $good = [];
        $reject = [];
        $start = $window->start;

        $rows->each(function ($e) use ($start, &$good, &$reject) {
            $payload = json_decode((string) $e->payload, true) ?: [];
            $minute = (int) floor($start->diffInSeconds(Carbon::parse($e->event_timestamp)) / 60);
            $delta = (float) ($payload['delta'] ?? 0);

            if (($payload['kind'] ?? 'good') === 'reject') {
                $reject[$minute] = ($reject[$minute] ?? 0) + $delta;
            } else {
                $good[$minute] = ($good[$minute] ?? 0) + $delta;
            }
        });

        return ['good' => $good, 'reject' => $reject];
    }

    /**
     * Stops overlapping the shift, keyed by minute-offset of their start so a
     * state slice can find the downtime row it belongs to.
     *
     * @return Collection<int, ProductionDowntime>
     */
    private function downtimes(Workstation $workstation, ShiftWindow $window, Collection $slices): Collection
    {
        return $this->downtimeRows(
            ProductionDowntime::with('reason')
                ->where('workstation_id', $workstation->id)
                ->where('started_at', '<', $window->end)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $window->start))
                ->orderBy('started_at')
                ->get(),
            $slices,
        );
    }

    /**
     * The same stamping, over rows already read. See sliceRows().
     *
     * @param  Collection<int, ProductionDowntime>  $rows
     * @param  Collection<int, array{state: string, from: Carbon, to: Carbon, id: int}>  $slices
     * @return Collection<int, ProductionDowntime>
     */
    private function downtimeRows(Collection $rows, Collection $slices): Collection
    {
        return $rows
            ->sortBy('started_at')
            ->values()
            ->each(fn (ProductionDowntime $d) => $d->setAttribute(
                'effective_ended_at',
                $d->ended_at ?? $this->openStopEndedWhen($d, $slices),
            ));
    }

    /**
     * When a stop with no `ended_at` actually stopped being true.
     *
     * The state machine closes a downtime as it leaves the down state, so an
     * open row means either "still down" or "nobody closed it" — a collector
     * killed mid-fault leaves one behind, and it stays open for good. Read
     * literally, such a row runs until now and therefore overlaps every stop on
     * every later shift, which is enough to hand them all the same id.
     *
     * The state timeline says which it is: the first non-down slice after the
     * stop opened is the moment the machine was no longer stopped, whatever the
     * downtime row says. Nothing after that instant belongs to it.
     *
     * @param  Collection<int, array{state: string, from: Carbon, to: Carbon, id: int}>  $slices
     */
    private function openStopEndedWhen(ProductionDowntime $downtime, Collection $slices): Carbon
    {
        $recovered = $slices->first(fn (array $slice) => $slice['from']->greaterThan($downtime->started_at)
            && ! in_array($slice['state'], WorkstationState::DOWNTIME_STATES, true));

        return $recovered ? $recovered['from']->copy() : Carbon::now();
    }

    // ─────────────────────────── segments ───────────────────────────

    /**
     * The timeline the screen draws: state slices clipped to the shift, split
     * at hour boundaries, RUNNING further split into run/slow runs.
     *
     * Each segment carries absolute minute offsets from shift start, so the
     * hour rows can position them without re-deriving times.
     *
     * @param  Collection<int, array{state: string, from: Carbon, to: Carbon, id: int}>  $slices
     * @param  array{good: array<int, float>, reject: array<int, float>}  $counters
     * @param  Collection<int, ProductionDowntime>  $downtimes
     * @return array<int, array<string, mixed>>
     */
    private function buildSegments(
        Collection $slices,
        array $counters,
        Collection $downtimes,
        float $idealPerMin,
        ShiftWindow $window,
        Carbon $asOf,
    ): array {
        $segments = [];
        $asOfMinute = (int) floor($window->start->diffInSeconds($asOf) / 60);
        $preRoll = $this->preRoll($window);

        foreach ($slices as $slice) {
            $from = (int) round($window->start->diffInSeconds($slice['from']) / 60);
            $to = (int) round($window->start->diffInSeconds($slice['to']) / 60);
            $to = min($to, $asOfMinute);
            if ($to <= $from) {
                continue;
            }

            $kind = self::SEGMENT_KIND[$slice['state']] ?? 'idle';
            $downtime = $kind === 'down' ? $this->downtimeFor($downtimes, $slice) : null;

            // RUNNING is not uniform: minutes below nameplate are a speed loss.
            $runs = $kind === 'run' && $idealPerMin > 0
                ? $this->splitByRate($from, $to, $counters, $idealPerMin)
                : [['kind' => $kind, 'from' => $from, 'to' => $to]];

            foreach ($runs as $run) {
                foreach ($this->splitAtHourBoundaries($run['from'], $run['to'], $preRoll) as [$segFrom, $segTo]) {
                    $segments[] = $this->segment(
                        $run['kind'], $segFrom, $segTo, $slice['state'], $downtime, $window,
                        $this->producedBetween($counters, $segFrom, $segTo), $idealPerMin,
                    );
                }
            }
        }

        return array_merge($segments, $this->unrecorded($segments, $asOfMinute, $window));
    }

    /**
     * Elapsed minutes no state slice covers.
     *
     * "The machine reported nothing" is a different fact from "the machine was
     * idle", and until these were drawn the two were indistinguishable — both
     * left bare track. A collector that dies mid-shift then reads on screen as
     * a quiet line, which is the one reading that stops anybody investigating.
     *
     * Emitted as its own kind and deliberately counted towards nothing: these
     * minutes are unknown, not planned, not lost, and folding them into any
     * bucket would put a guess into the OEE arithmetic.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<int, array<string, mixed>>
     */
    private function unrecorded(array $segments, int $asOfMinute, ShiftWindow $window): array
    {
        $covered = [];

        foreach ($segments as $segment) {
            for ($m = $segment['from']; $m < $segment['from'] + $segment['minutes']; $m++) {
                $covered[$m] = true;
            }
        }

        $gaps = [];
        $start = null;

        // One past the end so a gap running to `now` is closed by the loop.
        for ($m = 0; $m <= $asOfMinute; $m++) {
            $missing = $m < $asOfMinute && ! isset($covered[$m]);

            if ($missing && $start === null) {
                $start = $m;
            }

            if (! $missing && $start !== null) {
                foreach ($this->splitAtHourBoundaries($start, $m, $this->preRoll($window)) as [$from, $to]) {
                    $gaps[] = $this->segment('none', $from, $to, WorkstationState::IDLE, null, $window, 0.0, 0.0);
                }
                $start = null;
            }
        }

        return $gaps;
    }

    /**
     * The stop row covering a down state slice. Matched on overlap rather than
     * exact equality: the state machine opens the downtime at the transition,
     * but a manually-reported stop may straddle the slice slightly.
     *
     * @param  Collection<int, ProductionDowntime>  $downtimes
     * @param  array{state: string, from: Carbon, to: Carbon, id: int}  $slice
     */
    private function downtimeFor(Collection $downtimes, array $slice): ?ProductionDowntime
    {
        $overlapping = $downtimes->filter(function (ProductionDowntime $d) use ($slice) {
            // An open stop runs until now, but only if it opened inside this
            // slice's own window. A row left open by a killed collector days
            // ago overlaps *everything* under that rule, and taking the first
            // match would hand every stop on the timeline the same id — so the
            // supervisor's classification would land on a stop from last week
            // while the one they clicked stayed unexplained.
            $end = $d->getAttribute('effective_ended_at') ?? $d->ended_at ?? Carbon::now();

            return $d->started_at->lessThan($slice['to']) && $end->greaterThan($slice['from']);
        });

        // Nearest start wins. Overlap alone does not identify a stop when
        // several qualify; the one that began closest to this slice is the one
        // the state machine opened for it.
        return $overlapping
            ->sortBy(fn (ProductionDowntime $d) => abs($d->started_at->diffInSeconds($slice['from'])))
            ->first();
    }

    /**
     * Split a RUNNING interval into alternating normal / reduced-speed runs by
     * comparing each minute's output against the nameplate rate. Adjacent
     * minutes of the same verdict merge, so the timeline shows runs rather than
     * a stripe per minute.
     *
     * @param  array{good: array<int, float>, reject: array<int, float>}  $counters
     * @return array<int, array{kind: string, from: int, to: int}>
     */
    private function splitByRate(int $from, int $to, array $counters, float $idealPerMin): array
    {
        $runs = [];
        $floor = $idealPerMin * self::SLOW_THRESHOLD;

        for ($m = $from; $m < $to; $m++) {
            $produced = ($counters['good'][$m] ?? 0) + ($counters['reject'][$m] ?? 0);
            $kind = $produced < $floor ? 'slow' : 'run';

            $last = $runs ? $runs[count($runs) - 1] : null;
            if ($last && $last['kind'] === $kind && $last['to'] === $m) {
                $runs[count($runs) - 1]['to'] = $m + 1;
            } else {
                $runs[] = ['kind' => $kind, 'from' => $m, 'to' => $m + 1];
            }
        }

        return $runs ?: [['kind' => 'run', 'from' => $from, 'to' => $to]];
    }

    /**
     * Cut an interval wherever it crosses a clock hour, because the timeline is
     * drawn as one row per hour and a segment cannot span two rows.
     *
     * Offsets are minutes from shift start, so the boundaries are only at
     * multiples of 60 when the shift itself starts on the hour. A 06:30 shift
     * crosses 07:00 at minute 30 — cutting every 60 instead put the row break
     * half an hour off, and every row label and ruler tick with it.
     *
     * @param  int  $preRoll  minutes from the row grid's first clock hour to shift start
     * @return array<int, array{0: int, 1: int}>
     */
    private function splitAtHourBoundaries(int $from, int $to, int $preRoll = 0): array
    {
        $parts = [];
        $cursor = $from;

        while ($cursor < $to) {
            // The next clock hour, expressed in shift minutes.
            $hourEnd = (intdiv($cursor + $preRoll, 60) + 1) * 60 - $preRoll;
            $end = min($to, $hourEnd);
            $parts[] = [$cursor, $end];
            $cursor = $end;
        }

        return $parts;
    }

    /**
     * Minutes from the top of the clock hour the shift starts in to the shift
     * start: 0 for an 06:00 shift, 30 for an 06:30 one.
     *
     * The row grid is anchored to clock hours, so a shift that does not start
     * on the hour has its first row begin before it — the minutes before the
     * shift are simply empty, which is what a supervisor reading a clock-hour
     * ruler expects to see.
     */
    private function preRoll(ShiftWindow $window): int
    {
        return $window->start->minute + ($window->start->second > 0 ? 1 : 0);
    }

    /**
     * Pieces counted over a range of shift minutes.
     *
     * @param  array{good: array<int, float>, reject: array<int, float>}  $counters
     */
    private function producedBetween(array $counters, int $from, int $to): float
    {
        $total = 0.0;

        for ($m = $from; $m < $to; $m++) {
            $total += ($counters['good'][$m] ?? 0) + ($counters['reject'][$m] ?? 0);
        }

        return $total;
    }

    /**
     * What a stretch of time cost in pieces.
     *
     * A stop lost everything the station could have made; a slow run lost only
     * the shortfall against nameplate; running at rate lost nothing, and
     * planned or unrecorded time is not a loss to book against production.
     */
    private function lostPieces(string $kind, int $minutes, float $produced, float $idealPerMin): int
    {
        if ($idealPerMin <= 0 || ! in_array($kind, ['down', 'slow'], true)) {
            return 0;
        }

        return (int) round(max(0, $idealPerMin * $minutes - $produced));
    }

    /**
     * @param  float  $produced  pieces counted over this segment's own minutes
     * @return array<string, mixed>
     */
    private function segment(
        string $kind,
        int $from,
        int $to,
        string $state,
        ?ProductionDowntime $downtime,
        ShiftWindow $window,
        float $produced,
        float $idealPerMin,
    ): array {
        $needsCause = $kind === 'down' && (bool) ($downtime?->needs_reason ?? false);
        $reason = $needsCause ? null : $downtime?->reason;

        return [
            // Stable across refreshes so React keys and the open drawer survive one.
            'key' => $kind === 'down' && $downtime ? "d{$downtime->id}-{$from}" : "{$kind}-{$from}",
            'kind' => $kind,
            'from' => $from,
            'minutes' => $to - $from,
            'state' => $state,
            'downtimeId' => $downtime?->id,
            // Kept for the Pareto bucketing in totals(), which must not key on a
            // display name; the client reads `reason`/`reasonCode`.
            'downtimeReasonId' => $reason?->id,
            'reasonKind' => $reason?->kind instanceof DowntimeKind ? $reason->kind->value : null,
            'needsCause' => $needsCause,
            'reason' => $this->reasonLabel($reason),
            'reasonCode' => $reason?->code,
            // Echoed back when a cause is assigned, so the server can tell a
            // decision made on a current view from one made on a stale drawer.
            'classifiedAt' => $downtime?->classified_at?->toIso8601String(),
            'startsAt' => $window->start->copy()->addMinutes($from)->toIso8601String(),
            'produced' => (int) round($produced),
            // What this stretch cost, which is not the same question for each
            // kind: a stop lost everything it could have made, a slow run lost
            // only the shortfall, and a run at rate lost nothing at all. One
            // formula for all three reported a healthy hour as a total loss.
            'lost' => $this->lostPieces($kind, $to - $from, $produced, $idealPerMin),
            'label' => $this->segmentLabel($kind, $this->reasonLabel($reason), $state),
        ];
    }

    /**
     * A reason's display name in the operator's language.
     *
     * The seeded reasons are English source strings (hard rule 1), so they
     * translate like any other: `lang/pl.json` carries "Machine Breakdown" →
     * "Awaria maszyny". A reason an admin typed in themselves has no key and
     * comes back unchanged, which is the right answer for it — the catalog is
     * a translation of the defaults, not a rename of somebody's own codes.
     */
    private function reasonLabel(?DowntimeReason $reason): ?string
    {
        return $reason ? __($reason->name) : null;
    }

    private function segmentLabel(string $kind, ?string $reason, string $state): ?string
    {
        return match ($kind) {
            'down' => $reason,
            'plan' => $reason ?? __(ucfirst(strtolower($state))),
            default => null,
        };
    }

    // ─────────────────────────── hour rows ───────────────────────────

    /**
     * One row per clock hour the shift touches, carrying its segments, its
     * production pulses and its actual-vs-target counts.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array{good: array<int, float>, reject: array<int, float>}  $counters
     * @return array<int, array<string, mixed>>
     */
    private function buildHours(array $segments, array $counters, ShiftWindow $window, Carbon $asOf, float $idealPerMin): array
    {
        $rows = [];
        $shiftMinutes = $window->durationMinutes();
        $nowMinute = (int) floor($window->start->diffInSeconds($asOf) / 60);
        // Rows are clock hours, so the first one opens before the shift when
        // the shift does not start on the hour. Its offsets are negative; the
        // minutes before the shift simply hold no segments.
        $preRoll = $this->preRoll($window);

        for ($hourStart = -$preRoll; $hourStart < $shiftMinutes; $hourStart += 60) {
            $hourEnd = min($hourStart + 60, $shiftMinutes);
            $clock = $window->start->copy()->addMinutes($hourStart);

            $hourSegments = array_values(array_filter(
                $segments,
                fn (array $s) => $s['from'] >= $hourStart && $s['from'] < $hourEnd,
            ));

            // Target counts only minutes that were actually available to produce:
            // planned stops and unscheduled time are not a missed opportunity.
            $productiveMinutes = array_sum(array_map(
                fn (array $s) => in_array($s['kind'], ['run', 'slow', 'down'], true) ? $s['minutes'] : 0,
                $hourSegments,
            ));

            $actual = 0.0;
            for ($m = max(0, $hourStart); $m < $hourEnd; $m++) {
                $actual += ($counters['good'][$m] ?? 0) + ($counters['reject'][$m] ?? 0);
            }

            $target = $idealPerMin > 0 ? (int) round($idealPerMin * $productiveMinutes) : null;

            $rows[] = [
                'key' => $hourStart,
                'label' => $clock->format('H'),
                'from' => $hourStart,
                'segments' => $hourSegments,
                'dots' => $this->pulseDots($hourSegments, $counters, max(0, $hourStart), $hourEnd, $nowMinute),
                'actual' => (int) round($actual),
                'target' => $target,
                'isNow' => $nowMinute >= $hourStart && $nowMinute < $hourEnd,
                'nowOffset' => $nowMinute - $hourStart,
            ];
        }

        return $rows;
    }

    /**
     * The pulse strip under each hour: one dot per 2-minute bucket that
     * actually produced, so a glance shows output cadence rather than state.
     *
     * @param  array<int, array<string, mixed>>  $hourSegments
     * @param  array{good: array<int, float>, reject: array<int, float>}  $counters
     * @return array<int, array{offset: int, kind: string}>
     */
    private function pulseDots(array $hourSegments, array $counters, int $hourStart, int $hourEnd, int $nowMinute): array
    {
        $dots = [];

        for ($m = $hourStart + 1; $m < $hourEnd; $m += 2) {
            if ($m > $nowMinute) {
                break;
            }
            $segment = null;
            foreach ($hourSegments as $s) {
                if ($m >= $s['from'] && $m < $s['from'] + $s['minutes']) {
                    $segment = $s;
                    break;
                }
            }
            if (! $segment || ! in_array($segment['kind'], ['run', 'slow'], true)) {
                continue;
            }
            if ((($counters['good'][$m] ?? 0) + ($counters['reject'][$m] ?? 0)) <= 0) {
                continue;
            }

            $dots[] = ['offset' => $m - $hourStart, 'kind' => $segment['kind']];
        }

        return $dots;
    }

    // ─────────────────────────── totals & header ───────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array{good: array<int, float>, reject: array<int, float>}  $counters
     * @return array<string, mixed>
     */
    private function totals(array $segments, array $counters, ShiftWindow $window, Carbon $asOf): array
    {
        $minutes = ['run' => 0, 'slow' => 0, 'down' => 0, 'plan' => 0, 'idle' => 0, 'none' => 0];
        $byReason = [];
        // Stop ids, not segments: buildSegments cuts every stop at each hour
        // boundary, so counting segments would report one 90-minute stop as
        // three things to classify — and all three would vanish on one click.
        $unclassified = [];

        foreach ($segments as $s) {
            $minutes[$s['kind']] = ($minutes[$s['kind']] ?? 0) + $s['minutes'];

            if ($s['kind'] !== 'down') {
                continue;
            }

            // Bucketed by reason id, not by name: two reasons can share a
            // display name, and a plant that names one "Unclassified" would
            // otherwise merge into the synthetic bucket for stops with no cause
            // at all. The segment already carries the kind, so the Pareto
            // colouring needs no second lookup.
            $key = $s['needsCause'] ? 'none' : (string) $s['downtimeReasonId'];

            if ($s['needsCause']) {
                $unclassified[$s['downtimeId'] ?? $s['key']] = true;
            }

            $byReason[$key] ??= [
                'label' => $s['needsCause'] ? __('Unclassified') : $s['reason'],
                'kind' => $s['needsCause'] ? 'unclassified' : $s['reasonKind'],
                'minutes' => 0,
            ];
            $byReason[$key]['minutes'] += $s['minutes'];
        }

        $good = array_sum($counters['good']);
        $reject = array_sum($counters['reject']);

        return [
            'minutes' => $minutes,
            'elapsed' => max(1, (int) ceil($window->start->diffInSeconds($asOf) / 60)),
            'byReason' => $byReason,
            'unclassified' => count($unclassified),
            'good' => (int) round($good),
            'reject' => (int) round($reject),
            'produced' => (int) round($good + $reject),
        ];
    }

    /** @return array<string, mixed> */
    private function station(Workstation $workstation): array
    {
        return [
            'code' => $workstation->code,
            'name' => $workstation->name,
            'line' => $workstation->line?->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $totals
     * @param  array<int, array<string, mixed>>  $hours
     * @return array<string, mixed>
     */
    private function shift(ShiftWindow $window, array $totals, array $hours, bool $isLive, array $oee): array
    {
        $target = array_sum(array_map(fn (array $h) => (int) ($h['target'] ?? 0), $hours));

        return [
            'label' => $window->start->translatedFormat('l d.m').' · '.($window->shift?->name ?? __('Shift')),
            'window' => ($window->shift?->code ? $window->shift->code.' · ' : '')
                .$window->start->format('H:i').'–'.$window->end->format('H:i'),
            'quantity' => $totals['produced'],
            'target' => $target ?: null,
            'oee' => $oee['oee'],
            'isLive' => $isLive,
        ];
    }

    /**
     * OEE over the elapsed part of the shift. Planned stops shrink the
     * opportunity rather than counting against availability; speed loss shows
     * up in performance, scrap in quality.
     *
     * @param  array<string, mixed>  $totals
     * @return array{availability: ?float, performance: ?float, quality: ?float, oee: ?float}
     */
    private function oee(array $totals, float $idealPerMin): array
    {
        $m = $totals['minutes'];
        $opportunity = $m['run'] + $m['slow'] + $m['down'];

        $availability = $opportunity > 0 ? ($m['run'] + $m['slow']) / $opportunity * 100 : null;
        $running = $m['run'] + $m['slow'];

        // Actual output against what the nameplate rate would have produced in
        // the same running time — the ratio OeeCalculationService uses. Deriving
        // it from the run/slow minute split instead would make it a step
        // function of SLOW_THRESHOLD: a station holding 84% of rate all shift
        // has every minute classified slow and would read 0%, and 86% would read
        // 100%.
        $expected = $idealPerMin * $running;
        $performance = $expected > 0 ? min(100, $totals['produced'] / $expected * 100) : null;

        $quality = $totals['produced'] > 0 ? $totals['good'] / $totals['produced'] * 100 : null;

        $oee = ($availability !== null && $performance !== null && $quality !== null)
            ? $availability * $performance * $quality / 10000
            : null;

        return [
            'availability' => $availability !== null ? round($availability, 1) : null,
            'performance' => $performance !== null ? round($performance, 1) : null,
            'quality' => $quality !== null ? round($quality, 1) : null,
            'oee' => $oee !== null ? round($oee, 1) : null,
        ];
    }

    // ─────────────────────────── batches ───────────────────────────

    /** @return array<string, mixed>|null */
    private function currentBatch(Workstation $workstation, ShiftWindow $window): ?array
    {
        $batch = Batch::with('workOrder.productType')
            ->where('workstation_id', $workstation->id)
            ->where('status', Batch::STATUS_IN_PROGRESS)
            ->where('started_at', '<', $window->end)
            ->latest('started_at')
            ->first();

        return $batch ? $this->batchPayload($batch) : null;
    }

    /**
     * The batches finished before this one, most recent first.
     *
     * Two, not three: the panel is a "what came before" glance and the header
     * it sits in is capped so the timeline gets the screen. The full history
     * lives on the work order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function previousBatches(Workstation $workstation, ShiftWindow $window, int $limit = 2): array
    {
        return Batch::with('workOrder.productType')
            ->where('workstation_id', $workstation->id)
            ->where('status', Batch::STATUS_DONE)
            ->where('completed_at', '<', $window->end)
            ->latest('completed_at')
            ->limit($limit)
            ->get()
            ->map(fn (Batch $b) => $this->batchPayload($b))
            ->all();
    }

    /** @return array<string, mixed> */
    private function batchPayload(Batch $batch): array
    {
        $produced = (float) $batch->produced_qty;
        $target = (float) $batch->target_qty;

        return [
            'id' => $batch->id,
            'lot' => $batch->lot_number ?? ('#'.$batch->batch_number),
            'product' => $batch->workOrder?->productType?->name ?? $batch->workOrder?->order_no,
            'made' => (int) round($produced),
            'scrap' => (int) round((float) $batch->scrap_qty),
            'plan' => (int) round($target),
            'percent' => $target > 0 ? min(100, round($produced / $target * 100)) : 0,
        ];
    }

    // ─────────────────────────── chart ───────────────────────────

    /**
     * Rolling rate window: 28 two-minute samples ending at `asOf`. Each sample
     * reports pieces/minute and the instantaneous OEE-ish score the design's
     * second metric shows, so switching the pill needs no second request.
     *
     * @param  array{good: array<int, float>, reject: array<int, float>}  $counters
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    private function chart(array $counters, array $segments, ShiftWindow $window, Carbon $asOf, float $idealPerMin): array
    {
        $nowMinute = (int) floor($window->start->diffInSeconds($asOf) / 60);
        $span = self::CHART_SAMPLES * self::CHART_SAMPLE_MINUTES;
        $from = $nowMinute - $span;

        // Which kind of time each minute was, so an empty sample can say why.
        $kindByMinute = [];
        foreach ($segments as $s) {
            for ($m = $s['from']; $m < $s['from'] + $s['minutes']; $m++) {
                $kindByMinute[$m] = $s['kind'];
            }
        }

        $samples = [];
        for ($i = 0; $i < self::CHART_SAMPLES; $i++) {
            $sampleFrom = $from + $i * self::CHART_SAMPLE_MINUTES;
            $produced = 0.0;
            $kind = 'idle';

            for ($m = $sampleFrom; $m < $sampleFrom + self::CHART_SAMPLE_MINUTES; $m++) {
                if ($m < 0 || $m > $nowMinute) {
                    continue;
                }
                $produced += ($counters['good'][$m] ?? 0) + ($counters['reject'][$m] ?? 0);
                $kind = $kindByMinute[$m] ?? $kind;
            }

            $rate = round($produced / self::CHART_SAMPLE_MINUTES, 1);
            $samples[] = [
                'rate' => $rate,
                'ratePercent' => $idealPerMin > 0 ? (int) round(min(100, $rate / $idealPerMin * 100)) : 0,
                'kind' => $kind,
                'label' => $window->start->copy()->addMinutes(max(0, $sampleFrom))->format('H:i'),
                'inRange' => $sampleFrom >= 0 && $sampleFrom <= $nowMinute,
            ];
        }

        // Round the rate axis up to a sensible ceiling above nameplate.
        $peak = max($idealPerMin, ...array_map(fn (array $s) => $s['rate'], $samples));
        $rateMax = max(1, (int) (ceil(($peak * 1.15) / 5) * 5));

        return [
            'samples' => $samples,
            'rateMax' => $rateMax,
            'rateTicks' => [$rateMax, (int) round($rateMax * 0.75), (int) round($rateMax * 0.5), (int) round($rateMax * 0.25), 0],
            'percentTicks' => [100, 75, 50, 25, 0],
            'idealRatePerMinute' => round($idealPerMin, 2),
        ];
    }

    // ─────────────────────────── event pins ───────────────────────────

    /**
     * The balloons above the timeline. Each comes from a real record — a batch
     * changing over, an issue raised, a QC check signed off, a supervisor's
     * note on a stop — rather than from a separate annotation store.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventPins(Workstation $workstation, ShiftWindow $window): array
    {
        $pins = [];

        $batches = Batch::with('workOrder.productType')
            ->where('workstation_id', $workstation->id)
            ->where(fn ($q) => $q->whereBetween('started_at', [$window->start, $window->end])
                ->orWhereBetween('completed_at', [$window->start, $window->end]))
            ->get();

        foreach ($batches as $batch) {
            if ($batch->started_at && $window->contains($batch->started_at)) {
                $pins[] = $this->pin('batch', $batch->started_at, $window, [
                    'title' => __('Batch change'),
                    'status' => __('Started'),
                    'who' => __('system'),
                    'note' => ($batch->lot_number ?? '#'.$batch->batch_number).' · '
                        .($batch->workOrder?->productType?->name ?? ''),
                    'rows' => [
                        [__('PLANNED'), (string) (int) round((float) $batch->target_qty)],
                        [__('ORDER'), (string) ($batch->workOrder?->order_no ?? '—')],
                    ],
                ]);
            }
            if ($batch->completed_at && $window->contains($batch->completed_at)) {
                $pins[] = $this->pin('batch', $batch->completed_at, $window, [
                    'title' => __('Batch change'),
                    'status' => __('Completed'),
                    'who' => __('system'),
                    'note' => ($batch->lot_number ?? '#'.$batch->batch_number).' · '.__('closed'),
                    'rows' => [
                        [__('MADE'), (string) (int) round((float) $batch->produced_qty)],
                        [__('SCRAP'), (string) (int) round((float) $batch->scrap_qty)],
                    ],
                ]);
            }
        }

        $issues = Issue::with(['issueType:id,name', 'reportedBy:id,name'])
            ->whereHas('workOrder', fn ($q) => $q->where('line_id', $workstation->line_id))
            ->whereBetween('reported_at', [$window->start, $window->end])
            ->get();

        foreach ($issues as $issue) {
            $pins[] = $this->pin('escalate', $issue->reported_at, $window, [
                'title' => __('Escalation'),
                // OPEN / RESOLVED / … are catalog keys, so the pin reads in the
                // operator's language like every other status in the app.
                'status' => __(strtoupper((string) $issue->status)),
                'who' => $issue->reportedBy?->name ?? __('system'),
                'note' => $issue->title,
                'rows' => [
                    [__('TYPE'), (string) ($issue->issueType?->name ?? '—')],
                    [__('STATUS'), __(strtoupper((string) $issue->status))],
                ],
            ]);
        }

        // The batch is only a filter here, so it is matched but not loaded.
        $checks = QualityCheck::with('checkedBy:id,name')
            ->whereHas('batch', fn ($q) => $q->where('workstation_id', $workstation->id))
            ->whereBetween('checked_at', [$window->start, $window->end])
            ->get();

        foreach ($checks as $check) {
            $pins[] = $this->pin('qc', $check->checked_at, $window, [
                'title' => __('QC check'),
                'status' => $check->all_passed ? __('PASSED') : __('FAILED'),
                'who' => $check->checkedBy?->name ?? __('QA'),
                // A note is the checker's own prose and prints as they wrote
                // it. With none, the screen says what happened in the reader's
                // language rather than falling back to one generic line.
                'note' => $check->notes ?: ($check->all_passed
                    ? __('Quality check recorded')
                    : __('Check failed — an issue was raised')),
                'rows' => [
                    [__('QUANTITY'), (string) (int) round((float) $check->production_quantity)],
                    [__('RESULT'), $check->all_passed ? __('Pass') : __('Fail')],
                ],
            ]);
        }

        // A stop somebody classified and annotated reads as a comment on the shift.
        $notes = ProductionDowntime::with(['classifiedBy', 'reason'])
            ->where('workstation_id', $workstation->id)
            ->whereNotNull('classified_at')
            ->whereBetween('classified_at', [$window->start, $window->end])
            ->get();

        foreach ($notes as $downtime) {
            $pins[] = $this->pin('comment', $downtime->classified_at, $window, [
                'title' => __('Comment'),
                'status' => __('CLASSIFIED'),
                'who' => $downtime->classifiedBy?->name ?? __('supervisor'),
                'note' => $downtime->notes ?: (string) $this->reasonLabel($downtime->reason),
                'rows' => [
                    [__('LINKED'), (string) ($this->reasonLabel($downtime->reason) ?? '—')],
                    [__('DURATION'), ($downtime->duration_minutes ?? 0).' '.__('min')],
                ],
            ]);
        }

        usort($pins, fn (array $a, array $b) => $a['minute'] <=> $b['minute']);

        return $pins;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pin(string $type, Carbon $at, ShiftWindow $window, array $payload): array
    {
        $minute = (int) floor($window->start->diffInSeconds($at) / 60);

        return array_merge($payload, [
            'type' => $type,
            'minute' => $minute,
            'hour' => intdiv($minute, 60) * 60,
            'offset' => $minute % 60,
            'time' => $at->format('H:i'),
            'key' => $type.'-'.$minute.'-'.substr(md5((string) ($payload['note'] ?? '')), 0, 6),
        ]);
    }

    // ─────────────────────────── attention / summary ───────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    private function attention(array $segments): array
    {
        $segmentsByStop = [];

        foreach ($segments as $s) {
            if (! $s['needsCause']) {
                continue;
            }
            // Keyed by stop: a stop crossing an hour is several segments but one
            // decision, and this panel counts decisions the supervisor owes.
            $segmentsByStop[$s['downtimeId'] ?? $s['key']][] = $s;
        }

        $firstStop = reset($segmentsByStop) ?: null;
        $first = $firstStop[0] ?? null;

        return [
            'count' => count($segmentsByStop),
            'first' => $first ? [
                'key' => $first['key'],
                // The stop's whole duration, not just the slice before midnight
                // or the hour mark — that is the number worth acting on.
                'minutes' => array_sum(array_column($firstStop, 'minutes')),
                'at' => substr($first['startsAt'], 11, 5),
            ] : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    private function summary(array $segments, array $totals, Workstation $workstation): array
    {
        $changeovers = count(array_filter(
            $segments,
            fn (array $s) => $s['kind'] === 'plan' && $s['state'] === WorkstationState::SETUP,
        ));

        $operator = $workstation->workers()->first();

        return [
            'operator' => $operator?->name,
            'changeovers' => $changeovers,
            'changeoverMinutes' => array_sum(array_map(
                fn (array $s) => $s['kind'] === 'plan' && $s['state'] === WorkstationState::SETUP ? $s['minutes'] : 0,
                $segments,
            )),
            'unclassified' => $totals['unclassified'],
            'speedLossMinutes' => $totals['minutes']['slow'],
            'scrap' => $totals['reject'],
        ];
    }

    // ─────────────────────────── analysis tab ───────────────────────────

    /**
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    private function analysis(array $totals, array $oee): array
    {
        $m = $totals['minutes'];
        $downMinutes = $m['down'];

        $buckets = $totals['byReason'];
        usort($buckets, fn (array $a, array $b) => $b['minutes'] <=> $a['minutes']);
        $peak = $buckets ? $buckets[0]['minutes'] : 1;

        $pareto = array_map(fn (array $bucket) => [
            'label' => $bucket['label'],
            'minutes' => $bucket['minutes'],
            'percent' => $downMinutes > 0 ? (int) round($bucket['minutes'] / $downMinutes * 100) : 0,
            'bar' => $peak > 0 ? round($bucket['minutes'] / $peak * 100, 1) : 0,
            'kind' => $bucket['kind'] ?? 'unplanned',
        ], $buckets);

        // Elapsed rather than scheduled: mid-shift, the hours not yet run are
        // not a loss and would otherwise swamp every bar.
        $elapsed = max(1, $totals['elapsed']);

        return [
            'cards' => [
                ['key' => 'oee', 'label' => __('OEE'), 'value' => $oee['oee'], 'note' => __('Availability × Performance × Quality')],
                ['key' => 'availability', 'label' => __('Availability'), 'value' => $oee['availability'], 'note' => __('Unplanned stops against scheduled time'), 'delta' => $downMinutes.' '.__('min stopped')],
                ['key' => 'performance', 'label' => __('Performance'), 'value' => $oee['performance'], 'note' => __('Actual rate against nameplate rate'), 'delta' => $m['slow'].' '.__('min slow')],
                ['key' => 'quality', 'label' => __('Quality'), 'value' => $oee['quality'], 'note' => __('Good count against total produced'), 'delta' => $totals['reject'].' '.__('pcs scrap')],
            ],
            'pareto' => [
                'rows' => $pareto,
                'totalMinutes' => $downMinutes,
                'causeCount' => count($pareto),
            ],
            'waterfall' => [
                ['key' => 'scheduled', 'label' => __('Elapsed shift time'), 'minutes' => $elapsed, 'bar' => 100, 'tone' => 'neutral'],
                ['key' => 'planned', 'label' => __('− Planned stops'), 'minutes' => -$m['plan'], 'bar' => round($m['plan'] / $elapsed * 100, 1), 'tone' => 'planned'],
                ['key' => 'unplanned', 'label' => __('− Unplanned stops'), 'minutes' => -$m['down'], 'bar' => round($m['down'] / $elapsed * 100, 1), 'tone' => 'blocked'],
                ['key' => 'speed', 'label' => __('− Speed loss'), 'minutes' => -$m['slow'], 'bar' => round($m['slow'] / $elapsed * 100, 1), 'tone' => 'slow'],
                ['key' => 'effective', 'label' => __('Effective run time'), 'minutes' => $m['run'], 'bar' => round($m['run'] / $elapsed * 100, 1), 'tone' => 'running'],
            ],
            'quality' => [
                ['key' => 'good', 'label' => __('Good'), 'value' => $totals['good'], 'tone' => 'running'],
                ['key' => 'scrap', 'label' => __('Scrap'), 'value' => $totals['reject'], 'tone' => 'blocked'],
                ['key' => 'produced', 'label' => __('Produced'), 'value' => $totals['produced'], 'tone' => 'neutral'],
            ],
        ];
    }

    /**
     * The cause picker, grouped the way DowntimeKind splits reasons. Excludes
     * the AUTO-* placeholders: those are what the operator is replacing.
     *
     * Sent once with the page rather than on every refresh — it is admin
     * reference data that changes when somebody edits a reason code, not
     * something the shift moves.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reasonGroups(): array
    {
        return DowntimeReason::active()
            ->where('code', 'not like', 'AUTO-%')
            ->get()
            ->groupBy(fn (DowntimeReason $r) => $r->kind->value)
            ->map(fn (Collection $reasons, string $kind) => [
                'kind' => $kind,
                'label' => DowntimeKind::from($kind)->label(),
                // Sorted on the translated name, not the English one: the
                // operator reads the picker in their own language, and an
                // A–Z that only holds in English reads as no order at all.
                'items' => $reasons->map(fn (DowntimeReason $r) => [
                    'id' => $r->id,
                    'code' => $r->code,
                    'name' => $this->reasonLabel($r),
                ])->sortBy('name', SORT_LOCALE_STRING)->values()->all(),
            ])
            ->sortBy(fn (array $g) => array_search($g['kind'], [
                DowntimeKind::Unplanned->value,
                DowntimeKind::Planned->value,
                DowntimeKind::Changeover->value,
            ], true))
            ->values()
            ->all();
    }
}
