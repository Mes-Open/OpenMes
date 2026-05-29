<?php

namespace Database\Seeders;

use App\Models\EmployeeActivity;
use App\Models\EmployeeActivityCustomType;
use App\Models\Worker;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Demo tachograph-style activities — one realistic A-shift schedule per
 * active worker for the current week and the prior week. Activities link
 * to real WO records when available so the day-planner detail panel has
 * something to show. Idempotent: re-running wipes today→prior week first.
 *
 * Run with: `php artisan db:seed --class=EmployeeActivitiesDemoSeeder`.
 */
class EmployeeActivitiesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCustomTypes();

        $workers = Worker::where('is_active', true)->get();
        if ($workers->isEmpty()) {
            $this->command?->warn('No active workers — skipping activity seeding.');
            return;
        }

        $workOrders = WorkOrder::orderBy('id')->limit(20)->get();
        $woCycle = $workOrders->isEmpty() ? collect([null]) : $workOrders->values();

        // Seed range: previous 7 days + today + next 6 days
        $from = Carbon::today()->subDays(7);
        $to = Carbon::today()->addDays(6);

        // Clear existing seed activities in that range to keep it idempotent.
        EmployeeActivity::whereIn('worker_id', $workers->pluck('id'))
            ->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->delete();

        $shiftTemplates = $this->shiftTemplates();

        foreach ($workers as $i => $worker) {
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                // Skip weekends for every 3rd worker — gives the team-day view some variety.
                $isWeekend = in_array($cursor->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true);
                if ($isWeekend && ($i % 3 === 0)) {
                    $cursor->addDay();
                    continue;
                }

                $tpl = $shiftTemplates[($i + $cursor->day) % count($shiftTemplates)];
                $woForDay = $woCycle[$cursor->day % $woCycle->count()] ?? null;

                foreach ($tpl as $block) {
                    EmployeeActivity::create([
                        'worker_id'     => $worker->id,
                        'type'          => $block['type'],
                        'custom_code'   => $block['custom_code'] ?? null,
                        'label'         => $block['label'] ?? null,
                        'starts_at'     => $cursor->copy()->setTimeFromTimeString($block['from']),
                        'ends_at'       => $cursor->copy()->setTimeFromTimeString($block['to']),
                        'work_order_id' => isset($block['link_wo']) && $woForDay ? $woForDay->id : null,
                        'line_id'       => $worker->workstation?->line_id,
                        'step_name'     => $block['step'] ?? null,
                        'notes'         => $block['notes'] ?? null,
                    ]);
                }

                $cursor->addDay();
            }
        }

        $count = EmployeeActivity::whereIn('worker_id', $workers->pluck('id'))
            ->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->count();

        $this->command?->info("Seeded {$count} activities across {$workers->count()} workers.");
    }

    private function seedCustomTypes(): void
    {
        foreach ([
            ['code' => 'cleaning-5s',  'label' => 'Cleaning · 5S',   'color' => '#06b6d4'],
            ['code' => 'material-prep','label' => 'Material prep',   'color' => '#84cc16'],
        ] as $row) {
            EmployeeActivityCustomType::updateOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true],
            );
        }
    }

    /**
     * @return array<int, array<int, array{type:string, from:string, to:string, step?:string, label?:string, link_wo?:bool, notes?:string, custom_code?:string}>>
     */
    private function shiftTemplates(): array
    {
        // Template A — standard production worker, A-shift 06:00–14:00
        $a = [
            ['type' => 'travel',  'from' => '05:45', 'to' => '06:00'],
            ['type' => 'setup',   'from' => '06:00', 'to' => '06:18', 'label' => 'Line warm-up'],
            ['type' => 'work',    'from' => '06:18', 'to' => '08:42', 'step' => 'Pleat assembly', 'link_wo' => true],
            ['type' => 'break',   'from' => '08:42', 'to' => '09:00'],
            ['type' => 'work',    'from' => '09:00', 'to' => '11:30', 'step' => 'Adhesive bonding', 'link_wo' => true],
            ['type' => 'qc',      'from' => '11:30', 'to' => '12:00', 'step' => 'Inline QC sample', 'link_wo' => true],
            ['type' => 'rest',    'from' => '12:00', 'to' => '12:30', 'label' => 'Lunch'],
            ['type' => 'work',    'from' => '12:30', 'to' => '13:48', 'step' => 'Cure & seal', 'link_wo' => true],
            ['type' => 'maint',   'from' => '13:48', 'to' => '14:10', 'label' => 'Pleater P-04 unplanned', 'notes' => 'Cutter blade slipped during cycle 88, halted line, swapped insert from spare bin. Back online at 14:10.'],
            ['type' => 'travel',  'from' => '14:10', 'to' => '14:20'],
        ];

        // Template B — line-2 worker, longer shift with custom activities
        $b = [
            ['type' => 'travel',  'from' => '05:50', 'to' => '06:00'],
            ['type' => 'custom',  'from' => '06:00', 'to' => '06:30', 'custom_code' => 'cleaning-5s', 'label' => '5S walkdown'],
            ['type' => 'work',    'from' => '06:30', 'to' => '09:00', 'step' => 'Frame welding', 'link_wo' => true],
            ['type' => 'break',   'from' => '09:00', 'to' => '09:15'],
            ['type' => 'work',    'from' => '09:15', 'to' => '12:00', 'step' => 'Sub-assembly', 'link_wo' => true],
            ['type' => 'rest',    'from' => '12:00', 'to' => '12:30'],
            ['type' => 'meeting', 'from' => '12:30', 'to' => '13:00', 'label' => 'Daily stand-up'],
            ['type' => 'work',    'from' => '13:00', 'to' => '15:30', 'step' => 'Sub-assembly', 'link_wo' => true],
            ['type' => 'travel',  'from' => '15:30', 'to' => '15:40'],
        ];

        // Template C — QC inspector heavy on QC blocks
        $c = [
            ['type' => 'travel',  'from' => '05:45', 'to' => '06:00'],
            ['type' => 'qc',      'from' => '06:00', 'to' => '08:00', 'step' => 'Receiving inspection'],
            ['type' => 'meeting', 'from' => '08:00', 'to' => '08:30', 'label' => 'Quality huddle'],
            ['type' => 'qc',      'from' => '08:30', 'to' => '11:30', 'step' => 'In-process audits'],
            ['type' => 'rest',    'from' => '11:30', 'to' => '12:00'],
            ['type' => 'qc',      'from' => '12:00', 'to' => '14:00', 'step' => 'Final inspection'],
            ['type' => 'travel',  'from' => '14:00', 'to' => '14:10'],
        ];

        // Template D — maintenance tech
        $d = [
            ['type' => 'travel',  'from' => '06:00', 'to' => '06:15'],
            ['type' => 'maint',   'from' => '06:15', 'to' => '08:00', 'label' => 'Preventive maintenance L-01'],
            ['type' => 'work',    'from' => '08:00', 'to' => '10:00', 'step' => 'Assist line-1 setup'],
            ['type' => 'break',   'from' => '10:00', 'to' => '10:15'],
            ['type' => 'maint',   'from' => '10:15', 'to' => '13:48', 'label' => 'Compressor overhaul'],
            ['type' => 'maint',   'from' => '13:48', 'to' => '14:10', 'label' => 'Pleater P-04 callout'],
            ['type' => 'meeting', 'from' => '14:10', 'to' => '14:40', 'label' => 'Shift handover'],
            ['type' => 'travel',  'from' => '14:40', 'to' => '14:55'],
        ];

        // Template E — afternoon worker, B-shift 14:00–22:00
        $e = [
            ['type' => 'travel',  'from' => '13:45', 'to' => '14:00'],
            ['type' => 'setup',   'from' => '14:00', 'to' => '14:18'],
            ['type' => 'work',    'from' => '14:18', 'to' => '17:00', 'step' => 'Pleat assembly', 'link_wo' => true],
            ['type' => 'break',   'from' => '17:00', 'to' => '17:15'],
            ['type' => 'work',    'from' => '17:15', 'to' => '19:00', 'step' => 'Adhesive bonding', 'link_wo' => true],
            ['type' => 'qc',      'from' => '19:00', 'to' => '19:30', 'step' => 'Inline QC sample'],
            ['type' => 'work',    'from' => '19:30', 'to' => '22:00', 'step' => 'Cure & seal', 'link_wo' => true],
            ['type' => 'travel',  'from' => '22:00', 'to' => '22:15'],
        ];

        return [$a, $b, $c, $d, $e];
    }
}
