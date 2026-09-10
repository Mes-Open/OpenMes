<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\MachineEvent;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * A shift's worth of machine life for the live shift monitor: a state timeline
 * with real stops, a per-minute counter feed, and a couple of stops left
 * deliberately unclassified so the "needs a cause" flow has something to do.
 *
 * Regenerates every run — the data is pinned to the shift in progress, so
 * yesterday's copy would be off-screen anyway. Only touches stations it
 * seeds; other workstations are left alone.
 */
class ShiftMonitorDemoSeeder extends Seeder
{
    /** Batch numbers at or above this belong to the seeder and are its to reset. */
    private const BATCH_NUMBER_BASE = 9000;

    /**
     * How much shift history to lay down behind the live one, so paging back
     * through the monitor keeps finding shifts instead of empty days.
     *
     * Only backwards: the monitor draws what the machines actually did, and
     * there are no actuals for a shift that has not run yet. The forward two
     * weeks are the planner's job — orders and maintenance, not counters.
     */
    private const DAYS_BACK = 14;

    /**
     * Stations to bring to life, with their nameplate rate in pcs/hour. Covers
     * both demo datasets — whichever one is installed; a station that does not
     * exist is skipped with a warning.
     */
    private const STATIONS = [
        // Print shop (PrintShopDemoSeeder).
        'DTG-1' => 1200,
        'DTG-2' => 1200,
        'SITO-1' => 900,
        'HAFT-1' => 600,
        // Air filter line (AirFilterDemoSeeder).
        'WS-FR-01' => 120,
        'WS-PA-01' => 80,
        'WS-AB-01' => 75,
        'WS-PK-01' => 200,
    ];

    public function run(): void
    {
        foreach (self::STATIONS as $code => $ratePerHour) {
            $workstation = Workstation::where('code', $code)->first();
            if (! $workstation) {
                $this->command?->warn("Workstation {$code} not found — skipped.");

                continue;
            }

            $workstation->update(['ideal_rate_per_hour' => $ratePerHour]);

            // Without this, every state slice and stop would push a live nudge
            // — a few hundred synchronous Reverb calls per run, for a shift
            // nobody is watching yet.
            Model::withoutEvents(fn () => $this->seedStation($workstation, $ratePerHour));
        }
    }

    private function seedStation(Workstation $workstation, int $ratePerHour): void
    {
        // One station-wide reset before the loop: the per-window cleanup below
        // cannot see batches, which are scoped by the seeder's number range.
        Batch::where('workstation_id', $workstation->id)
            ->where('batch_number', '>=', self::BATCH_NUMBER_BASE)
            ->forceDelete();

        $windows = $this->windows($workstation);
        $now = Carbon::now();
        $stops = 0;
        $unclassified = 0;

        // Oldest first when writing. clear() wipes everything at or after the
        // window it is given, so working newest-first would have each older
        // shift delete the history already laid down above it. array_reverse
        // keeps the keys, so index 0 stays the live shift.
        foreach (array_reverse($windows, true) as $index => $window) {
            // A finished shift is filled end to end; the one in progress stops
            // at the current minute so its last slice stays open.
            $isLive = $window->contains($now);
            $elapsed = (int) min(
                $window->durationMinutes(),
                max(1, floor($window->start->diffInMinutes($now)))
            );

            $this->clear($workstation, $window);

            // Vary the story per shift, or every day would be a carbon copy.
            $plan = $this->plan($elapsed, $workstation->id + $index * 101);

            $this->writeStates($workstation, $window, $plan, $elapsed, $isLive);
            $this->seedBatches($workstation, $window, $elapsed, $ratePerHour, $index);
            $this->writeCounters($workstation, $window, $plan, $elapsed, $ratePerHour);

            $stops += count(array_filter($plan, fn ($s) => $s['kind'] === 'down'));
            $unclassified += count(array_filter($plan, fn ($s) => ($s['unclassified'] ?? false)));
        }

        $this->command?->info(sprintf(
            '%s — %d shift(s) seeded back to %s (%d stops, %d unclassified).',
            $workstation->code,
            count($windows),
            $windows ? end($windows)->start->format('D d.m') : '—',
            $stops,
            $unclassified,
        ));
    }

    /**
     * The shifts to fill: the one running now, then backwards through
     * DAYS_BACK. Newest first, so the live shift keeps index 0 and its
     * generated story stays stable as history grows behind it.
     *
     * @return list<ShiftWindow>
     */
    private function windows(Workstation $workstation): array
    {
        $now = Carbon::now();
        // Only the shifts this station actually works: its own line's, plus the
        // ones that apply everywhere. Taking every active shift gave a station
        // on line 1 the other four lines' rosters too — fifteen shifts a day
        // instead of six, and a history five times longer than the plant's.
        $shifts = Shift::where('is_active', true)
            ->where(fn ($q) => $q->where('line_id', $workstation->line_id)->orWhereNull('line_id'))
            ->orderBy('start_time')
            ->get();

        // No shifts defined — fall back to the single synthetic window, which is
        // what the monitor itself falls back to.
        if ($shifts->isEmpty()) {
            return [$this->window($workstation)];
        }

        $windows = [];

        for ($day = 0; $day <= self::DAYS_BACK; $day++) {
            $date = $now->copy()->subDays($day)->startOfDay();

            foreach ($shifts as $shift) {
                $window = ShiftWindow::startingOn($shift, $date);

                // Not started yet — nothing to record.
                if ($window->start > $now) {
                    continue;
                }

                $windows[] = $window;
            }
        }

        // Newest first.
        usort($windows, fn ($a, $b) => $b->start <=> $a->start);

        return $windows;
    }

    /** The shift occurrence to fill: whichever one the station's line is running. */
    private function window(Workstation $workstation): ShiftWindow
    {
        return ShiftWindow::at($workstation->line_id, Carbon::now())
            ?? ShiftWindow::current($workstation->line_id);
    }

    private function clear(Workstation $workstation, ShiftWindow $window): void
    {
        // Anything overlapping the window, not merely starting inside it. A
        // previous run leaves its final slice open-ended, and an open slice from
        // an earlier window still covers this one — the reader would then see
        // two states at once and count the same minute twice, inflating every
        // hourly target.
        $overlapping = fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $window->start);

        WorkstationState::where('workstation_id', $workstation->id)
            ->where(fn ($q) => $q->where('started_at', '>=', $window->start)->orWhere($overlapping))
            ->delete();

        MachineEvent::where('workstation_id', $workstation->id)
            ->where('event_timestamp', '>=', $window->start)->delete();

        ProductionDowntime::where('workstation_id', $workstation->id)
            ->where(fn ($q) => $q->where('started_at', '>=', $window->start)->orWhere($overlapping))
            ->delete();
    }

    /**
     * The shape of the shift: a repeating rhythm of setup, running, the odd
     * stop and a break, laid out until the elapsed time is covered. Seeded from
     * the workstation id so each station gets its own — but stable — story.
     *
     * @return array<int, array{kind: string, from: int, to: int, reason?: string, unclassified?: bool}>
     */
    private function plan(int $elapsed, int $seed): array
    {
        mt_srand($seed);

        $segments = [];
        $cursor = 0;

        // Every shift opens with a changeover.
        $segments[] = ['kind' => 'setup', 'from' => 0, 'to' => 8];
        $cursor = 8;

        // Unclassified stops are the point of the screen, so guarantee two of
        // them rather than leaving it to the dice.
        $unclassifiedBudget = 2;

        $stops = [
            ['reason' => 'no_material', 'min' => 6, 'max' => 14],
            ['reason' => 'breakdown', 'min' => 8, 'max' => 20],
            ['reason' => 'quality_issue', 'min' => 5, 'max' => 10],
            ['reason' => 'no_operator', 'min' => 4, 'max' => 9],
        ];

        $breakDone = false;

        while ($cursor < $elapsed) {
            $run = mt_rand(14, 32);
            $segments[] = ['kind' => 'run', 'from' => $cursor, 'to' => min($elapsed, $cursor + $run)];
            $cursor += $run;
            if ($cursor >= $elapsed) {
                break;
            }

            // Mid-shift break, once.
            if (! $breakDone && $cursor > 200) {
                $segments[] = ['kind' => 'break', 'from' => $cursor, 'to' => min($elapsed, $cursor + 26)];
                $cursor += 26;
                $breakDone = true;

                continue;
            }

            $stop = $stops[mt_rand(0, count($stops) - 1)];
            $duration = mt_rand($stop['min'], $stop['max']);
            $unclassified = $unclassifiedBudget > 0 && mt_rand(0, 2) === 0;
            if ($unclassified) {
                $unclassifiedBudget--;
            }

            $segments[] = [
                'kind' => 'down',
                'from' => $cursor,
                'to' => min($elapsed, $cursor + $duration),
                'reason' => $stop['reason'],
                'unclassified' => $unclassified,
            ];
            $cursor += $duration;
        }

        // Any budget left over goes on the last stop, so the screen always has
        // something waiting on a cause.
        if ($unclassifiedBudget > 0) {
            for ($i = count($segments) - 1; $i >= 0 && $unclassifiedBudget > 0; $i--) {
                if ($segments[$i]['kind'] === 'down' && ! ($segments[$i]['unclassified'] ?? false)) {
                    $segments[$i]['unclassified'] = true;
                    $unclassifiedBudget--;
                }
            }
        }

        return array_values(array_filter($segments, fn ($s) => $s['to'] > $s['from']));
    }

    /**
     * @param  array<int, array<string, mixed>>  $plan
     */
    private function writeStates(
        Workstation $workstation,
        ShiftWindow $window,
        array $plan,
        int $elapsed,
        bool $isLive = true,
    ): void {
        $reasons = DowntimeReason::pluck('id', 'code');

        foreach ($plan as $segment) {
            $state = match ($segment['kind']) {
                'setup' => WorkstationState::SETUP,
                'break' => WorkstationState::CLEANING,
                'down' => WorkstationState::STOPPED,
                default => WorkstationState::RUNNING,
            };

            $from = $window->start->copy()->addMinutes($segment['from']);
            // Only the shift actually in progress ends on an open slice. A
            // finished shift left one open would read as a machine still in that
            // state days later — and the next shift's cleanup would delete it.
            $isOpen = $isLive && $segment['to'] >= $elapsed;
            $to = $isOpen ? null : $window->start->copy()->addMinutes($segment['to']);

            WorkstationState::create([
                'workstation_id' => $workstation->id,
                'state' => $state,
                'started_at' => $from,
                'ended_at' => $to,
                'duration_seconds' => $to ? (int) $from->diffInSeconds($to) : null,
                'source' => 'machine',
            ]);

            MachineEvent::create([
                'workstation_id' => $workstation->id,
                'event_type' => MachineEvent::TYPE_STATE_CHANGE,
                'state_to' => $state,
                'event_timestamp' => $from,
                'payload' => ['source' => 'demo'],
            ]);

            if ($segment['kind'] === 'down' || $segment['kind'] === 'break') {
                $this->writeDowntime($workstation, $window, $segment, $reasons, $from, $to);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $segment
     * @param  \Illuminate\Support\Collection<string, int>  $reasons
     */
    private function writeDowntime(
        Workstation $workstation,
        ShiftWindow $window,
        array $segment,
        $reasons,
        Carbon $from,
        ?Carbon $to,
    ): void {
        $unclassified = (bool) ($segment['unclassified'] ?? false);

        // An unclassified stop carries the machine's own placeholder reason —
        // the arithmetic still works, but nobody has said what happened.
        $code = $segment['kind'] === 'break'
            ? 'scheduled_break'
            : ($unclassified ? 'AUTO-STOP' : $segment['reason']);

        $reasonId = $reasons[$code] ?? DowntimeReason::firstOrCreate(
            ['code' => 'AUTO-STOP'],
            ['name' => 'Machine stopped (auto)', 'kind' => 'unplanned', 'is_active' => true],
        )->id;

        ProductionDowntime::create([
            'line_id' => $workstation->line_id,
            'workstation_id' => $workstation->id,
            'downtime_reason_id' => $reasonId,
            'needs_reason' => $unclassified,
            'shift_id' => $window->shift?->id,
            'started_at' => $from,
            'ended_at' => $to,
            'duration_minutes' => $to ? (int) ceil($from->diffInSeconds($to) / 60) : null,
            'notes' => $unclassified ? null : __('Auto-recorded from machine state STOPPED'),
        ]);
    }

    /**
     * The batches the station worked through this shift: three finished ones
     * behind it and one still running. Attached to real work orders on the
     * station's line so the panel links back to something that exists.
     */
    private function seedBatches(
        Workstation $workstation,
        ShiftWindow $window,
        int $elapsed,
        int $ratePerHour,
        int $windowIndex = 0,
    ): void {
        $workOrders = WorkOrder::where('line_id', $workstation->line_id)
            ->orderBy('id')
            ->limit(4)
            ->get();

        if ($workOrders->isEmpty()) {
            return;
        }

        mt_srand($workstation->id + 4231 + $windowIndex * 101);

        // Split the elapsed shift into four stretches; the last one is open.
        $slice = max(30, intdiv($elapsed, 4));
        $lotDate = $window->start->format('y-md');
        $shiftMark = $window->shift?->code ?? 'X';

        foreach ($workOrders->values() as $i => $workOrder) {
            $from = $window->start->copy()->addMinutes($i * $slice);
            $isCurrent = $i === $workOrders->count() - 1;
            $to = $isCurrent ? null : $window->start->copy()->addMinutes(min($elapsed, ($i + 1) * $slice));

            $ranMinutes = (int) $from->diffInMinutes($to ?? $window->start->copy()->addMinutes($elapsed));
            $target = max(1, (int) round($ratePerHour * $slice / 60));
            $produced = (int) round($ratePerHour * $ranMinutes / 60 * (mt_rand(78, 96) / 100));

            Batch::create([
                'work_order_id' => $workOrder->id,
                'workstation_id' => $workstation->id,
                // Stations on the same line draw from the same work orders, and
                // every shift in the history reuses them again, so the number
                // has to be unique per station AND per shift — not just index.
                //
                // The station bucket is 100000 wide because the window term has
                // to fit inside it. At 1000 it did not: a fortnight of shifts
                // overflowed past index 100, so station 3 window 121 and
                // station 4 window 21 both produced 13210 and the seeder died
                // on batches_work_order_id_batch_number_unique. Room now for
                // 10000 windows of 10 batches each.
                'batch_number' => self::BATCH_NUMBER_BASE + $workstation->id * 100000 + $windowIndex * 10 + $i,
                'lot_number' => sprintf('LOT %s-%s%s%s', $lotDate, $shiftMark, $workstation->id, chr(65 + $i)),
                'target_qty' => $target,
                'produced_qty' => $produced,
                'scrap_qty' => (int) round($produced * mt_rand(1, 3) / 100),
                'status' => $isCurrent ? Batch::STATUS_IN_PROGRESS : Batch::STATUS_DONE,
                'started_at' => $from,
                'completed_at' => $to,
            ]);
        }
    }

    /**
     * A counter pulse per producing minute. Running minutes land near nameplate
     * with a little jitter, interrupted by occasional slow spells of a few
     * minutes — a machine drifts off pace for a while, it does not lose exactly
     * one minute — so the monitor's speed-loss detection finds runs it can draw.
     *
     * @param  array<int, array<string, mixed>>  $plan
     */
    private function writeCounters(
        Workstation $workstation,
        ShiftWindow $window,
        array $plan,
        int $elapsed,
        int $ratePerHour,
    ): void {
        mt_srand($workstation->id + 977);

        $perMinute = $ratePerHour / 60;
        $rows = [];
        // The batch this shift's pulses belong to. Scoped to the window: with
        // history behind it the station has a batch per shift, and an unscoped
        // lookup would pin every day's counters to the oldest one.
        $batchId = Batch::where('workstation_id', $workstation->id)
            ->where('started_at', '>=', $window->start)
            ->orderBy('started_at')
            ->value('id');
        $slowFor = 0;

        foreach ($plan as $segment) {
            if ($segment['kind'] !== 'run') {
                continue;
            }

            for ($m = $segment['from']; $m < min($segment['to'], $elapsed); $m++) {
                if ($slowFor > 0) {
                    $slowFor--;
                } elseif (mt_rand(0, 27) === 0) {
                    $slowFor = mt_rand(3, 5);
                }

                $factor = $slowFor > 0
                    ? mt_rand(45, 78) / 100      // clearly under the 85% threshold
                    : mt_rand(92, 108) / 100;

                $good = max(0, (int) round($perMinute * $factor));
                if ($good === 0) {
                    continue;
                }

                $at = $window->start->copy()->addMinutes($m)->addSeconds(mt_rand(0, 59));

                $rows[] = [
                    'workstation_id' => $workstation->id,
                    'event_type' => MachineEvent::TYPE_COUNTER,
                    'payload' => json_encode(['delta' => $good, 'kind' => 'good', 'batch_id' => $batchId]),
                    'event_timestamp' => $at,
                    'created_at' => $at,
                    'updated_at' => $at,
                ];

                // Scrap trickles in — a reject pulse every ~25 producing minutes.
                if (mt_rand(0, 24) === 0) {
                    $rows[] = [
                        'workstation_id' => $workstation->id,
                        'event_type' => MachineEvent::TYPE_COUNTER,
                        'payload' => json_encode(['delta' => mt_rand(1, 4), 'kind' => 'reject', 'batch_id' => $batchId]),
                        'event_timestamp' => $at,
                        'created_at' => $at,
                        'updated_at' => $at,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            MachineEvent::insert($chunk);
        }
    }
}
