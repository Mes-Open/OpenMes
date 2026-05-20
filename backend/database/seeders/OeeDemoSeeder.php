<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class OeeDemoSeeder extends Seeder
{
    /**
     * Default shifts seeded for each line when none are configured yet.
     * Together they cover the full 24h with no gaps.
     */
    private const DEFAULT_SHIFTS = [
        ['name' => 'Day',   'code' => 'D', 'start' => '06:00:00', 'end' => '14:00:00', 'sort' => 1],
        ['name' => 'Late',  'code' => 'L', 'start' => '14:00:00', 'end' => '22:00:00', 'sort' => 2],
        ['name' => 'Night', 'code' => 'N', 'start' => '22:00:00', 'end' => '06:00:00', 'sort' => 3],
    ];

    public function run(): void
    {
        $user = User::first();
        $reasons = DowntimeReason::all();
        $lines = Line::where('is_active', true)->get();

        if ($lines->isEmpty() || $reasons->isEmpty()) {
            $this->command?->warn('No lines or downtime reasons — run DowntimeReasonsSeeder first.');
            return;
        }

        $this->ensureShifts($lines);

        // Inclusive 7-day window ending today (matches the dashboard default).
        $period = CarbonPeriod::create(now()->subDays(6)->startOfDay(), now()->startOfDay());

        foreach ($lines as $line) {
            $workOrder = $line->workOrders()->first();
            $lineShifts = Shift::where('line_id', $line->id)->where('is_active', true)->get();

            foreach ($period as $date) {
                $shiftsToday = $lineShifts->isNotEmpty()
                    ? $lineShifts
                    : collect([null]); // fall back to one "All-day" slot if no shifts defined

                foreach ($shiftsToday as $shift) {
                    // 1-2 downtimes per shift
                    foreach (range(1, random_int(1, 2)) as $_) {
                        $reason = $reasons->random();
                        $duration = random_int(5, 60);
                        $start = $this->randomTimeWithinShift($date, $shift);

                        ProductionDowntime::create([
                            'line_id' => $line->id,
                            'downtime_reason_id' => $reason->id,
                            'shift_id' => $shift?->id,
                            'started_at' => $start,
                            'ended_at' => $start->copy()->addMinutes($duration),
                            'duration_minutes' => $duration,
                            'reported_by' => $user?->id,
                            'notes' => 'Demo: ' . $reason->name,
                        ]);
                    }

                    // 1-2 batches per shift so each shift has real production data
                    if ($workOrder) {
                        foreach (range(1, random_int(1, 2)) as $_) {
                            $produced = random_int(40, 200);
                            $scrap = random_int(0, (int) ($produced * 0.05));
                            $started = $this->randomTimeWithinShift($date, $shift);
                            $completed = $started->copy()->addMinutes(random_int(45, 180));

                            Batch::create([
                                'work_order_id' => $workOrder->id,
                                'batch_number' => (Batch::where('work_order_id', $workOrder->id)->max('batch_number') ?? 0) + 1,
                                'status' => Batch::STATUS_DONE,
                                'target_qty' => $produced,
                                'produced_qty' => $produced,
                                'scrap_qty' => $scrap,
                                'started_at' => $started,
                                'completed_at' => $completed,
                            ]);
                        }
                    }
                }
            }
        }

        foreach ($period as $date) {
            Artisan::call('oee:calculate', ['--date' => $date->toDateString()]);
        }

        $this->command?->info(sprintf(
            'OEE demo seeded: %d days × %d lines × %d shifts.',
            $period->count(),
            $lines->count(),
            Shift::count()
        ));
    }

    private function ensureShifts(Collection $lines): void
    {
        foreach ($lines as $line) {
            if (Shift::where('line_id', $line->id)->exists()) {
                continue;
            }
            foreach (self::DEFAULT_SHIFTS as $spec) {
                Shift::create([
                    'line_id' => $line->id,
                    'name' => $spec['name'],
                    'code' => $spec['code'] . $line->id,
                    'start_time' => $spec['start'],
                    'end_time' => $spec['end'],
                    'is_active' => true,
                    'sort_order' => $spec['sort'],
                ]);
            }
        }
    }

    private function randomTimeWithinShift(Carbon $date, ?Shift $shift): Carbon
    {
        if (! $shift) {
            return $date->copy()->setTime(random_int(6, 17), random_int(0, 59));
        }

        $start = Carbon::parse($shift->start_time);
        $end = Carbon::parse($shift->end_time);

        if ($end->lte($start)) {
            $end = $end->copy()->addDay();
        }

        $minutes = (int) $start->diffInMinutes($end);
        $offset = random_int(0, max(0, $minutes - 30));

        return $date->copy()
            ->setTime((int) $start->format('H'), (int) $start->format('i'))
            ->addMinutes($offset);
    }
}
