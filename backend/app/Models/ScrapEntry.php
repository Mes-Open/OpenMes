<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapEntry extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'work_order_id',
        'scrap_reason_id',
        'quantity',
        'batch_step_id',
        'shift_id',
        'notes',
        'reported_by',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'reported_at' => 'datetime',
        ];
    }

    /**
     * The work order this scrap was reported against.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * The reason code classifying this scrap.
     */
    public function scrapReason(): BelongsTo
    {
        return $this->belongsTo(ScrapReason::class);
    }

    /**
     * The batch step the scrap was attributed to (optional).
     */
    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    /**
     * The shift the scrap was reported on (optional).
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * The user who reported the scrap.
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
