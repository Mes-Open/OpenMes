<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class OeeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $reasons = DowntimeReason::all();
        $lines = Line::where('is_active', true)->get();

        if ($lines->isEmpty() || $reasons->isEmpty()) {
            $this->command?->warn('No lines or downtime reasons — run DowntimeReasonsSeeder first.');
            return;
        }

        $period = CarbonPeriod::create(now()->subDays(6)->startOfDay(), now()->startOfDay());

        foreach ($lines as $line) {
            $workOrder = $line->workOrders()->first();

            foreach ($period as $date) {
                // 2-4 downtimes per day, mix of kinds
                $nDowntimes = random_int(2, 4);
                for ($i = 0; $i < $nDowntimes; $i++) {
                    $reason = $reasons->random();
                    $duration = random_int(5, 75);
                    $start = $date->copy()->setTime(random_int(6, 17), random_int(0, 59));

                    ProductionDowntime::create([
                        'line_id' => $line->id,
                        'downtime_reason_id' => $reason->id,
                        'started_at' => $start,
                        'ended_at' => $start->copy()->addMinutes($duration),
                        'duration_minutes' => $duration,
                        'reported_by' => $user?->id,
                        'notes' => 'Demo: ' . $reason->name,
                    ]);
                }

                // 1-3 batches per day, mostly DONE with production + small scrap
                if ($workOrder) {
                    $nBatches = random_int(1, 3);
                    for ($i = 0; $i < $nBatches; $i++) {
                        $produced = random_int(80, 400);
                        $scrap = random_int(0, (int) ($produced * 0.05));
                        $started = $date->copy()->setTime(7, random_int(0, 30));
                        $completed = $date->copy()->setTime(random_int(13, 16), random_int(0, 59));

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

        foreach ($period as $date) {
            Artisan::call('oee:calculate', ['--date' => $date->toDateString()]);
        }

        $this->command?->info('OEE demo data seeded for ' . $period->count() . ' days × ' . $lines->count() . ' lines.');
    }
}
