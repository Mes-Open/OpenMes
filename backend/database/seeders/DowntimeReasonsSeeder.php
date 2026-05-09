<?php

namespace Database\Seeders;

use App\Models\DowntimeReason;
use Illuminate\Database\Seeder;

class DowntimeReasonsSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['code' => 'breakdown', 'name' => 'Machine Breakdown', 'is_planned' => false],
            ['code' => 'changeover', 'name' => 'Changeover / Setup', 'is_planned' => false],
            ['code' => 'no_material', 'name' => 'No Material Available', 'is_planned' => false],
            ['code' => 'no_operator', 'name' => 'No Operator Available', 'is_planned' => false],
            ['code' => 'quality_issue', 'name' => 'Quality Issue / Rework', 'is_planned' => false],
            ['code' => 'planned_maintenance', 'name' => 'Planned Maintenance', 'is_planned' => true],
            ['code' => 'scheduled_break', 'name' => 'Scheduled Break', 'is_planned' => true],
            ['code' => 'cleaning', 'name' => 'Cleaning / Sanitation', 'is_planned' => true],
            ['code' => 'waiting_approval', 'name' => 'Waiting for Approval', 'is_planned' => false],
            ['code' => 'other', 'name' => 'Other', 'is_planned' => false],
        ];

        foreach ($reasons as $reason) {
            DowntimeReason::firstOrCreate(
                ['code' => $reason['code']],
                $reason
            );
        }
    }
}
