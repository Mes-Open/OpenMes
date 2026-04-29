<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityCheck extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'batch_id',
        'quality_check_template_id',
        'checked_by',
        'checked_at',
        'production_quantity',
        'all_passed',
        'notes',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'production_quantity' => 'decimal:2',
            'all_passed' => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QualityCheckTemplate::class, 'quality_check_template_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(QualityCheckSample::class)->orderBy('sample_number')->orderBy('parameter_name');
    }
}
