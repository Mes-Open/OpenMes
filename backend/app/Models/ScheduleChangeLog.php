<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One planner edit: the full placement snapshot of a work order before and
 * after the write. Undo restores `before` (and is itself logged as a new
 * entry, so history stays append-only and an undo can be undone).
 */
class ScheduleChangeLog extends Model
{
    protected $fillable = [
        'work_order_id',
        'user_id',
        'action',
        'before',
        'after',
        'undone_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
