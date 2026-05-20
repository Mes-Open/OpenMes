<?php

namespace Database\Seeders;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use Illuminate\Database\Seeder;

class DowntimeReasonsSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['code' => 'breakdown', 'name' => 'Machine Breakdown', 'kind' => DowntimeKind::Unplanned->value],
            ['code' => 'changeover', 'name' => 'Changeover / Setup', 'kind' => DowntimeKind::Changeover->value],
            ['code' => 'no_material', 'name' => 'No Material Available', 'kind' => DowntimeKind::Unplanned->value],
            ['code' => 'no_operator', 'name' => 'No Operator Available', 'kind' => DowntimeKind::Unplanned->value],
            ['code' => 'quality_issue', 'name' => 'Quality Issue / Rework', 'kind' => DowntimeKind::Unplanned->value],
            ['code' => 'planned_maintenance', 'name' => 'Planned Maintenance', 'kind' => DowntimeKind::Planned->value],
            ['code' => 'scheduled_break', 'name' => 'Scheduled Break', 'kind' => DowntimeKind::Planned->value],
            ['code' => 'cleaning', 'name' => 'Cleaning / Sanitation', 'kind' => DowntimeKind::Planned->value],
            ['code' => 'waiting_approval', 'name' => 'Waiting for Approval', 'kind' => DowntimeKind::Unplanned->value],
            ['code' => 'other', 'name' => 'Other', 'kind' => DowntimeKind::Unplanned->value],
        ];

        foreach ($reasons as $reason) {
            DowntimeReason::firstOrCreate(
                ['code' => $reason['code']],
                $reason
            );
        }
    }
}
