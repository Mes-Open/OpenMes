<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One extra schedule segment of a work order beyond its primary placement:
 * the order also runs on `line_id` from `due_date`/`shift_number` through
 * `end_date`/`end_shift_number`. Segments are coarse (day + shift) — the
 * minute-level plan always belongs to the primary placement.
 */
class WorkOrderPlacement extends Model
{
    protected $fillable = [
        'work_order_id',
        'line_id',
        'due_date',
        'shift_number',
        'end_date',
        'end_shift_number',
    ];

    /**
     * Bump the parent order's updated_at on every placement change so the
     * planner's live refresh (which watches work_orders) picks it up.
     */
    protected $touches = ['workOrder'];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'end_date' => 'datetime',
            'shift_number' => 'integer',
            'end_shift_number' => 'integer',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }
}
