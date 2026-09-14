<?php

namespace App\Models;

use App\Enums\DowntimeKind;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DowntimeReason extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'name',
        'code',
        'kind',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DowntimeKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Downtime recorded against this reason. */
    public function downtimes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionDowntime::class, 'downtime_reason_id');
    }
}
