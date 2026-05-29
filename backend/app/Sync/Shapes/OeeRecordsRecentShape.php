<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Sync\Shape;

/**
 * OEE records for the dashboard "OEE Overview" panel. Recent window only —
 * older records are historical and don't need to live-sync.
 *
 * Electric WHERE clauses can't contain SQL value functions like `current_date`,
 * so we embed a literal date computed in PHP at request time. Crossing midnight
 * means the next page load gets a different shape handle (handled by the client
 * automatically); already-open dashboards keep their shape until they refresh.
 */
class OeeRecordsRecentShape extends Shape
{
    public function table(): string
    {
        return 'oee_records';
    }

    public function columns(): array
    {
        return [
            'id',
            'line_id',
            'workstation_id',
            'shift_id',
            'record_date',
            'planned_minutes',
            'operating_minutes',
            'downtime_minutes',
            'total_produced',
            'good_produced',
            'scrap_qty',
            'availability_pct',
            'performance_pct',
            'quality_pct',
            'oee_pct',
            'updated_at',
        ];
    }

    public function where(User $user): ?string
    {
        $since = now()->subDay()->toDateString();

        return "record_date >= '{$since}'";
    }
}
