<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityCheckTemplate extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'process_template_id',
        'name',
        'min_checks_per_batch',
        'min_checks_per_day',
        'samples_per_check',
        'parameters',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'min_checks_per_batch' => 'integer',
            'min_checks_per_day' => 'integer',
            'samples_per_check' => 'integer',
            'parameters' => 'array',
        ];
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }

    public function qualityChecks(): HasMany
    {
        return $this->hasMany(QualityCheck::class);
    }
}
