<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Sync\Shape;

/**
 * OEE records for the dashboard "OEE Overview" panel. Recent window only —
 * older records are historical and don't need to live-sync.
 *
 * The window is a literal date computed in PHP at request time rather than a
 * SQL value function like `current_date`, so the snapshot and the broadcast
 * filter agree on one boundary for the request. Crossing midnight means the next
 * page load gets a different window; already-open dashboards keep theirs until
 * they refresh.
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
