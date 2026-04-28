<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderShiftEntry extends Model
{
    protected $fillable = [
        'work_order_id',
        'shift_id',
        'quantity',
        'user_id',
        'production_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'production_date' => 'date',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
