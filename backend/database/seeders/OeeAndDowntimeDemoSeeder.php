<?php

namespace Database\Seeders;

use App\Enums\DowntimeKind;
use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\OeeRecord;
use App\Models\ProductionDowntime;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Demo data for the OEE dashboard + Downtime screens. Works on any line set.
 *
 *  - HISTORY_DAYS of OeeRecord rows per active line with realistic, drifting
 *    A/P/Q (a mid-week dip) for the historical part of the window.
 *  - REAL DONE production batches for today + yesterday on every line, because
 *    the OEE page RECOMPUTES those two days from production on every load
 *    (OeeController::ensureOeeCalculated). Without real batches the recompute
 *    overwrites the seeded "today/yesterday" rows with nulls — so the dashboard
 *    looks empty. Seeding production makes the recompute yield real OEE that
 *    persists.
 *  - Downtime reasons + recent/active ProductionDowntime entries.
 *
 * Run with: `php artisan db:seed --class=OeeAndDowntimeDemoSeeder`
 *
 * Idempotent: OeeRecords upsert on (line, date); production is skipped for a
 * line that already has recent DONE batches, so re-runs don't duplicate.
 */
class OeeAndDowntimeDemoSeeder extends Seeder
{
    /** How many days of OEE history to seed, ending today. */
    private const HISTORY_DAYS = 14;

    /**
     * Days back (0 = today) that the OEE page recomputes from production on
     * load, and therefore need REAL batches to survive.
     */
    private const RECOMPUTED_DAYS = [0, 1];

    public function run(): void
    {
        // Every active line gets OEE history so the dashboard is fully populated
        // regardless of the line set (air-filter L-0x, or any other demo data).
        $lines = Line::where('is_active', true)->orderBy('code')->get();
        if ($lines->isEmpty()) {
            $this->command?->warn('OeeAndDowntimeDemoSeeder: no lines found — run a line seeder first.');

            return;
        }

        $reasons = $this->seedDowntimeReasons();
        $reporter = User::orderBy('id')->first();
        $sequence = $this->ensureLotSequence();

        $this->seedOeeRecords($lines);
        $this->seedDowntimes($lines, $reasons, $reporter);

        // Real production for the recomputed days, then compute OEE from it so
        // today/yesterday hold real values instead of being nulled on page load.
        $produced = $this->seedRecentProduction($lines, $reasons, $reporter);

        // Give every finished batch a LOT number so reports, the work-order
        // detail and traceability show real lots instead of blanks.
        $lots = $this->assignLots($sequence);

        $today = CarbonImmutable::now()->startOfDay();
        foreach (self::RECOMPUTED_DAYS as $back) {
            $date = $today->subDays($back);
            Artisan::call('oee:calculate', ['--date' => $date->toDateString()]);
            // Let the controller recompute on next load too (clear its day cache).
            Cache::forget('oee_calculated_'.$date->toDateString());
        }

        $this->command?->info(sprintf(
            'OEE demo seeded: %d lines × %d days history + production on today/yesterday; %d LOT(s) assigned.',
            $lines->count(),
            self::HISTORY_DAYS,
            $lots,
        ));
    }

    /**
     * Seed REAL DONE batches on today + yesterday for every line, so the OEE
     * recompute produces non-null values. Each line is given a work order if it
     * has none. Returns the number of lines that received production.
     */
    private function seedRecentProduction($lines, array $reasons, ?User $reporter): int
    {
        $today = CarbonImmutable::now()->startOfDay();
        $unplanned = $reasons['MACH_BREAK'] ?? $reasons['MAT_SHORTAGE'] ?? null;
        $count = 0;

        foreach ($lines as $line) {
            $workOrder = $this->workOrderFor($line);
            if (! $workOrder) {
                continue;
            }

            // Idempotency: skip a line that already has DONE batches today.
            $already = Batch::where('work_order_id', $workOrder->id)
                ->where('status', Batch::STATUS_DONE)
                ->whereDate('completed_at', $today->toDateString())
                ->exists();
            if ($already) {
                $count++;

                continue;
            }

            foreach (self::RECOMPUTED_DAYS as $back) {
                $date = $today->subDays($back);

                // 2 batches per day spread across the shift.
                foreach ([7, 13] as $startHour) {
                    $producedQty = random_int(60, 220);
                    $scrap = random_int(0, (int) round($producedQty * 0.05));
                    $started = $date->setTime($startHour, random_int(0, 59));
                    $completed = $started->addMinutes(random_int(120, 220));

                    Batch::create([
                        'work_order_id' => $workOrder->id,
                        'batch_number' => (Batch::where('work_order_id', $workOrder->id)->max('batch_number') ?? 0) + 1,
                        'status' => Batch::STATUS_DONE,
                        'target_qty' => $producedQty,
                        'produced_qty' => $producedQty,
                        'scrap_qty' => $scrap,
                        'started_at' => $started,
                        'completed_at' => $completed,
                    ]);
                }

                // One unplanned downtime so availability isn't a flat 100%.
                if ($unplanned) {
                    $dtStart = $date->setTime(random_int(8, 16), random_int(0, 59));
                    ProductionDowntime::updateOrCreate(
                        ['line_id' => $line->id, 'downtime_reason_id' => $unplanned->id, 'started_at' => $dtStart],
                        ['ended_at' => $dtStart->addMinutes(random_int(15, 55)), 'duration_minutes' => random_int(15, 55), 'reported_by' => $reporter?->id, 'notes' => 'Demo production-day downtime'],
                    );
                }
            }

            $count++;
        }

        return $count;
    }

    /** The line's first work order, or a minimal demo one created on the fly. */
    private function workOrderFor(Line $line): ?WorkOrder
    {
        $existing = WorkOrder::where('line_id', $line->id)->first();
        if ($existing) {
            return $existing;
        }

        $productTypeId = ProductType::value('id');
        if (! $productTypeId) {
            return null;
        }

        return WorkOrder::create([
            'order_no' => 'WO-OEE-'.$line->id,
            'line_id' => $line->id,
            'product_type_id' => $productTypeId,
            'planned_qty' => 1000,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
    }

    /** Ensure a default LOT sequence exists so finished batches can get LOTs. */
    private function ensureLotSequence(): LotSequence
    {
        return LotSequence::firstOrCreate(
            ['product_type_id' => null],
            [
                'name' => 'Default',
                'prefix' => 'LOT',
                'pad_size' => 5,
                'year_prefix' => true,
                'reset_period' => 'yearly',
                'next_number' => 1,
            ],
        );
    }

    /**
     * Assign a LOT number to every finished batch that still lacks one (the OEE
     * production batches are created without lots), so reports / work-order
     * detail / traceability render real lots. Idempotent — only fills blanks.
     */
    private function assignLots(LotSequence $sequence): int
    {
        $batches = Batch::where('status', Batch::STATUS_DONE)
            ->whereNull('lot_number')
            ->orderBy('completed_at')
            ->get();

        foreach ($batches as $batch) {
            $batch->update([
                'lot_number' => $sequence->generateNext(),
                'lot_assigned_at' => Batch::LOT_ON_RELEASE,
            ]);
        }

        return $batches->count();
    }

    /** @return array<string, DowntimeReason> */
    private function seedDowntimeReasons(): array
    {
        $defs = [
            ['code' => 'MAINT_PLANNED', 'name' => 'Planned maintenance',  'kind' => DowntimeKind::Planned->value],
            ['code' => 'TOOL_CHANGE',   'name' => 'Tool / die change',    'kind' => DowntimeKind::Changeover->value],
            ['code' => 'MAT_SHORTAGE',  'name' => 'Material shortage',    'kind' => DowntimeKind::Unplanned->value],
            ['code' => 'MACH_BREAK',    'name' => 'Machine breakdown',    'kind' => DowntimeKind::Unplanned->value],
            ['code' => 'QUALITY',       'name' => 'Quality hold',         'kind' => DowntimeKind::Unplanned->value],
        ];

        $result = [];
        foreach ($defs as $def) {
            $reason = DowntimeReason::updateOrCreate(
                ['code' => $def['code']],
                array_merge($def, ['is_active' => true]),
            );
            $result[$def['code']] = $reason;
        }

        return $result;
    }

    /**
     * OEE history per line over HISTORY_DAYS, ending today. Values stay in
     * realistic bands with a mid-week dip for narrative interest. Date+line is
     * the unique key, so re-runs upsert in place.
     */
    private function seedOeeRecords($lines): void
    {
        // Named baselines keep the original air-filter story; any other line
        // gets a stable, code-derived baseline so each tells its own story.
        $baselines = [
            'L-01' => ['a' => 88, 'p' => 84, 'q' => 96],
            'L-02' => ['a' => 92, 'p' => 81, 'q' => 94],
            'L-03' => ['a' => 76, 'p' => 79, 'q' => 91],
            'L-04' => ['a' => 90, 'p' => 86, 'q' => 97],
        ];

        $today = CarbonImmutable::now()->startOfDay();

        foreach ($lines as $line) {
            $b = $baselines[$line->code] ?? $this->baselineFor((string) $line->code);
            for ($i = 0; $i < self::HISTORY_DAYS; $i++) {
                $date = $today->subDays($i);
                // 0 = today; a recurring weekly dip mid-week for visual interest.
                $shift = [0 => 0, 1 => -3, 2 => -8, 3 => -12, 4 => -2, 5 => 4, 6 => 2][$i % 7] ?? 0;
                $a = max(40, min(99, $b['a'] + $shift + random_int(-2, 2)));
                $p = max(40, min(99, $b['p'] + $shift + random_int(-3, 3)));
                $q = max(70, min(100, $b['q'] + intdiv($shift, 2) + random_int(-1, 1)));
                $oee = round(($a * $p * $q) / 10000, 1);

                // Planned minutes: a 16h (two-shift) day for most lines, 8h for
                // the named single-shift L-04. Operating derives from availability.
                $planned = $line->code === 'L-04' ? 480 : 960;
                $operating = (int) round($planned * ($a / 100));
                $downtime = $planned - $operating;
                $totalProduced = (int) round(($operating / 60) * ($p / 100) * 18);
                $goodProduced = (int) round($totalProduced * ($q / 100));
                $scrap = max(0, $totalProduced - $goodProduced);

                OeeRecord::updateOrCreate(
                    ['line_id' => $line->id, 'record_date' => $date->toDateString()],
                    [
                        'planned_minutes' => $planned,
                        'operating_minutes' => $operating,
                        'downtime_minutes' => $downtime,
                        'ideal_cycle_minutes' => 0.05,
                        'total_produced' => $totalProduced,
                        'good_produced' => $goodProduced,
                        'scrap_qty' => $scrap,
                        'availability_pct' => $a,
                        'performance_pct' => $p,
                        'quality_pct' => $q,
                        'oee_pct' => $oee,
                    ],
                );
            }
        }
    }

    /**
     * A stable, code-derived A/P/Q baseline for lines without a named one, so
     * every line gets believable but distinct OEE (and re-runs are identical).
     *
     * @return array{a: int, p: int, q: int}
     */
    private function baselineFor(string $code): array
    {
        $h = crc32($code);

        return [
            'a' => 72 + ($h % 23),                 // 72–94
            'p' => 70 + (intdiv($h, 23) % 25),     // 70–94
            'q' => 90 + (intdiv($h, 575) % 9),     // 90–98
        ];
    }

    /**
     * Recent + active downtimes. The active one is open (ended_at = null) and
     * scoped to L-03 — mirrors the "Line 3 down 12 min" card from the design.
     */
    private function seedDowntimes($lines, array $reasons, ?User $reporter): void
    {
        // Map the four narrative slots onto the actual lines by position, so the
        // demo works for any line set (cycling if there are fewer than four).
        $ordered = $lines->values();
        $slots = [];
        foreach (['L-01', 'L-02', 'L-03', 'L-04'] as $i => $slot) {
            $slots[$slot] = $ordered->get($i % max(1, $ordered->count()));
        }

        // Anchor every offset to "today at midnight" so re-runs on the same
        // day produce identical started_at values — the upsert key stays
        // stable. The active row drifts forward by a few minutes each day so
        // it reads as "just now" without polluting history with new rows.
        $todayMidnight = CarbonImmutable::now()->startOfDay();
        $minute = CarbonImmutable::now()->minute;

        // The "active now" row uses a moving started_at (now - 12m), so its
        // composite upsert key drifts between runs — we'd accumulate stale
        // open rows. Wipe any open downtime on the target line first.
        $activeLine = $slots['L-03'];
        if ($activeLine) {
            ProductionDowntime::query()
                ->where('line_id', $activeLine->id)
                ->whereNull('ended_at')
                ->delete();
        }

        $rows = [
            // Active right now on L-03 (started ~12 minutes ago).
            ['line' => 'L-03', 'reason' => 'MACH_BREAK',    'anchor' => 'now', 'startMinutesAgo' => 12,        'durationMinutes' => null, 'notes' => 'Pleating roller jam — maintenance dispatched'],

            // Last 24h history — offsets from today midnight
            ['line' => 'L-01', 'reason' => 'TOOL_CHANGE',   'anchor' => 'today', 'startMinute' => 8 * 60 + 30,  'durationMinutes' => 15],
            ['line' => 'L-02', 'reason' => 'MAT_SHORTAGE',  'anchor' => 'today', 'startMinute' => 9 * 60 + 10,  'durationMinutes' => 30],
            ['line' => 'L-01', 'reason' => 'MAINT_PLANNED', 'anchor' => 'today', 'startMinute' => 11 * 60,      'durationMinutes' => 40],
            ['line' => 'L-04', 'reason' => 'QUALITY',       'anchor' => 'today', 'startMinute' => 12 * 60 + 5,  'durationMinutes' => 20],
            ['line' => 'L-03', 'reason' => 'MAT_SHORTAGE',  'anchor' => 'today', 'startMinute' => 13 * 60 + 30, 'durationMinutes' => 25],
            ['line' => 'L-02', 'reason' => 'MACH_BREAK',    'anchor' => 'today', 'startMinute' => 14 * 60,      'durationMinutes' => 50],

            // Yesterday
            ['line' => 'L-03', 'reason' => 'MACH_BREAK',    'anchor' => 'days-1', 'startMinute' => 4 * 60,      'durationMinutes' => 35],
            ['line' => 'L-01', 'reason' => 'TOOL_CHANGE',   'anchor' => 'days-1', 'startMinute' => 2 * 60,      'durationMinutes' => 20],
            ['line' => 'L-04', 'reason' => 'MAINT_PLANNED', 'anchor' => 'days-1', 'startMinute' => 1 * 60,      'durationMinutes' => 45],

            // Older — for the 7-day downtime-by-reason buckets
            ['line' => 'L-01', 'reason' => 'MACH_BREAK',    'anchor' => 'days-2', 'startMinute' => 10 * 60,     'durationMinutes' => 50],
            ['line' => 'L-02', 'reason' => 'QUALITY',       'anchor' => 'days-3', 'startMinute' => 11 * 60,     'durationMinutes' => 30],
            ['line' => 'L-03', 'reason' => 'MAT_SHORTAGE',  'anchor' => 'days-4', 'startMinute' => 9 * 60,      'durationMinutes' => 45],
            ['line' => 'L-04', 'reason' => 'TOOL_CHANGE',   'anchor' => 'days-5', 'startMinute' => 8 * 60,      'durationMinutes' => 40],
        ];

        foreach ($rows as $row) {
            $line = $slots[$row['line']] ?? null;
            $reason = $reasons[$row['reason']] ?? null;
            if (! $line || ! $reason) {
                continue;
            }

            // Compute deterministic started_at from the anchor + offset.
            if ($row['anchor'] === 'now') {
                $startedAt = CarbonImmutable::now()->subMinutes($row['startMinutesAgo']);
            } else {
                $daysBack = $row['anchor'] === 'today' ? 0 : (int) substr($row['anchor'], 5);
                $startedAt = $todayMidnight->subDays($daysBack)->addMinutes($row['startMinute']);
            }
            $endedAt = $row['durationMinutes'] !== null
                ? $startedAt->addMinutes($row['durationMinutes'])
                : null;

            ProductionDowntime::updateOrCreate(
                [
                    'line_id' => $line->id,
                    'downtime_reason_id' => $reason->id,
                    'started_at' => $startedAt,
                ],
                [
                    'ended_at' => $endedAt,
                    'duration_minutes' => $row['durationMinutes'],
                    'notes' => $row['notes'] ?? null,
                    'reported_by' => $reporter?->id,
                ],
            );
        }

        unset($minute);
    }
}
