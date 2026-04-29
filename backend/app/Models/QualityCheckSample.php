<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityCheckSample extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'quality_check_id',
        'sample_number',
        'parameter_name',
        'parameter_type',
        'value_numeric',
        'value_boolean',
        'is_passed',
    ];

    protected function casts(): array
    {
        return [
            'sample_number' => 'integer',
            'value_numeric' => 'decimal:4',
            'value_boolean' => 'boolean',
            'is_passed' => 'boolean',
        ];
    }

    public function qualityCheck(): BelongsTo
    {
        return $this->belongsTo(QualityCheck::class);
    }
}
