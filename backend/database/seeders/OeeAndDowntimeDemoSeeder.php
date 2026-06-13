<?php

namespace Database\Seeders;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\OeeRecord;
use App\Models\ProductionDowntime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo data for the OEE Command (tablet) + Downtime screens. Builds on the
 * lines created by AirFilterDemoSeeder — run that first.
 *
 *  - 7 days × 4 lines = 28 OeeRecord rows with realistic A/P/Q values that
 *    drift over the week (worse mid-week, better Fri/Sat).
 *  - 4 DowntimeReason rows (planned + unplanned) seeded once.
 *  - ~12 ProductionDowntime entries from the last 24h, one active "now".
 *
 * Run with: `php artisan db:seed --class=OeeAndDowntimeDemoSeeder`
 *
 * Idempotent: each row keyed on a stable composite so re-runs upsert in place.
 */
class OeeAndDowntimeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $lines = Line::orderBy('code')->limit(4)->get();
        if ($lines->isEmpty()) {
            $this->command?->warn('OeeAndDowntimeDemoSeeder: no lines found — run AirFilterDemoSeeder first.');

            return;
        }

        $reasons = $this->seedDowntimeReasons();
        $reporter = User::orderBy('id')->first();

        $this->seedOeeRecords($lines);
        $this->seedDowntimes($lines, $reasons, $reporter);
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
     * 7-day OEE history per line. Values stay in realistic bands (60–95%)
     * with one bad day for narrative interest. Date+line is the unique key.
     */
    private function seedOeeRecords($lines): void
    {
        // Per-line baseline A/P/Q so each line tells a different story.
        $baselines = [
            'L-01' => ['a' => 88, 'p' => 84, 'q' => 96],
            'L-02' => ['a' => 92, 'p' => 81, 'q' => 94],
            'L-03' => ['a' => 76, 'p' => 79, 'q' => 91],
            'L-04' => ['a' => 90, 'p' => 86, 'q' => 97],
        ];
        // Day index 0 = today, 6 = a week ago. Negative deltas = worse days.
        $dailyShift = [0 => 0, 1 => -3, 2 => -8, 3 => -12, 4 => -2, 5 => 4, 6 => 2];

        $today = CarbonImmutable::now()->startOfDay();

        foreach ($lines as $line) {
            $b = $baselines[$line->code] ?? ['a' => 85, 'p' => 80, 'q' => 95];
            for ($i = 0; $i < 7; $i++) {
                $date = $today->subDays($i);
                $shift = $dailyShift[$i] ?? 0;
                $a = max(40, min(99, $b['a'] + $shift + random_int(-2, 2)));
                $p = max(40, min(99, $b['p'] + $shift + random_int(-3, 3)));
                $q = max(70, min(100, $b['q'] + intdiv($shift, 2) + random_int(-1, 1)));
                $oee = round(($a * $p * $q) / 10000, 1);

                // Planned minutes = a 16h day (two shifts) for L-01..03,
                // 8h for L-04. Operating minutes derive from availability.
                $planned = in_array($line->code, ['L-04'], true) ? 480 : 960;
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
     * Recent + active downtimes. The active one is open (ended_at = null) and
     * scoped to L-03 — mirrors the "Line 3 down 12 min" card from the design.
     */
    private function seedDowntimes($lines, array $reasons, ?User $reporter): void
    {
        $byCode = $lines->keyBy('code');

        // Anchor every offset to "today at midnight" so re-runs on the same
        // day produce identical started_at values — the upsert key stays
        // stable. The active row drifts forward by a few minutes each day so
        // it reads as "just now" without polluting history with new rows.
        $todayMidnight = CarbonImmutable::now()->startOfDay();
        $minute = CarbonImmutable::now()->minute;

        // The "active now" row uses a moving started_at (now - 12m), so its
        // composite upsert key drifts between runs — we'd accumulate stale
        // open rows. Wipe any open downtime on the target line first.
        $activeLine = $byCode->get('L-03');
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
            $line = $byCode->get($row['line']);
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
