<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlendFormulaComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'blend_formula_id',
        'tea_base_id',
        'component_name',
        'standard_ratio',
        'tolerance',
        'small_actual',
        'large_actual',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'standard_ratio' => 'decimal:2',
            'tolerance' => 'decimal:2',
            'small_actual' => 'decimal:2',
            'large_actual' => 'decimal:2',
        ];
    }

    public function blendFormula(): BelongsTo
    {
        return $this->belongsTo(BlendFormula::class);
    }

    public function teaBase(): BelongsTo
    {
        return $this->belongsTo(TeaBase::class);
    }

    public function smallDeviation(): ?float
    {
        return $this->small_actual === null ? null : (float) $this->small_actual - (float) $this->standard_ratio;
    }

    public function largeDeviation(): ?float
    {
        return $this->large_actual === null ? null : (float) $this->large_actual - (float) $this->standard_ratio;
    }

    public function isOutOfTolerance(float $actual): bool
    {
        return abs($actual - (float) $this->standard_ratio) > (float) $this->tolerance + 0.001;
    }

    public function verdict(): string
    {
        $small = $this->smallDeviation();
        $large = $this->largeDeviation();

        if ($small === null && $large === null) {
            return BlendFormula::RESULT_PENDING;
        }

        if (($small !== null && $this->isOutOfTolerance((float) $this->small_actual))
            || ($large !== null && $this->isOutOfTolerance((float) $this->large_actual))) {
            return BlendFormula::RESULT_FAIL;
        }

        return BlendFormula::RESULT_PASS;
    }
}
