<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The staffing an order was planned against: who was assigned to its lines
 * and who of them was away on its days, captured on every placement. See
 * {@see \App\Services\Schedule\Insights\StaffingSnapshots}.
 */
class WorkOrderStaffingSnapshot extends Model
{
    use HasTenant;

    protected $fillable = [
        'work_order_id',
        'fingerprint',
        'roster',
        'absences',
        'window_start',
        'window_end',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'roster' => 'array',
            'absences' => 'array',
            'window_start' => 'datetime',
            'window_end' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
