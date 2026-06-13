<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnomalyReason extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the production anomalies for this reason.
     */
    public function anomalies(): HasMany
    {
        return $this->hasMany(ProductionAnomaly::class);
    }

    /**
     * Scope to get only active anomaly reasons.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
