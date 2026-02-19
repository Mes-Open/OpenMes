<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceEvent extends Model
{
    use HasFactory, Auditable;

    const TYPE_PLANNED     = 'planned';
    const TYPE_CORRECTIVE  = 'corrective';
    const TYPE_INSPECTION  = 'inspection';

    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'title',
        'event_type',
        'status',
        'tool_id',
        'line_id',
        'workstation_id',
        'cost_source_id',
        'assigned_to_id',
        'scheduled_at',
        'started_at',
        'completed_at',
        'description',
        'resolution_notes',
        'actual_cost',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'  => 'datetime',
            'started_at'    => 'datetime',
            'completed_at'  => 'datetime',
            'actual_cost'   => 'decimal:2',
        ];
    }

    /**
     * Get the tool associated with this maintenance event.
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * Get the line associated with this maintenance event.
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * Get the workstation associated with this maintenance event.
     */
    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    /**
     * Get the cost source for this maintenance event.
     */
    public function costSource(): BelongsTo
    {
        return $this->belongsTo(CostSource::class);
    }

    /**
     * Get the user assigned to this maintenance event.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }
}
