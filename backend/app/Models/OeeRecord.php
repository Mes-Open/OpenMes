<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Support\OeeBand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OeeRecord extends Model
{
    use HasFactory;
    use HasTenant;

    protected $fillable = [
        'line_id',
        'workstation_id',
        'shift_id',
        'record_date',
        'planned_minutes',
        'operating_minutes',
        'downtime_minutes',
        'ideal_cycle_minutes',
        'total_produced',
        'good_produced',
        'scrap_qty',
        'availability_pct',
        'performance_pct',
        'quality_pct',
        'oee_pct',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'planned_minutes' => 'integer',
            'operating_minutes' => 'integer',
            'downtime_minutes' => 'integer',
            'ideal_cycle_minutes' => 'decimal:4',
            'total_produced' => 'decimal:2',
            'good_produced' => 'decimal:2',
            'scrap_qty' => 'decimal:2',
            'availability_pct' => 'decimal:2',
            'performance_pct' => 'decimal:2',
            'quality_pct' => 'decimal:2',
            'oee_pct' => 'decimal:2',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function getOeeColorAttribute(): string
    {
        return OeeBand::colorFor($this->oee_pct !== null ? (float) $this->oee_pct : null);
    }
}
