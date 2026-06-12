<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResult extends Model
{
    use HasFactory;

    public const TYPE_VISUAL = 'visual';

    public const TYPE_MEASUREMENT = 'measurement';

    public const TYPE_FUNCTIONAL = 'functional';

    public const TYPE_PASS_FAIL = 'pass_fail';

    protected $fillable = [
        'inspection_id',
        'criterion_name',
        'criterion_type',
        'required',
        'unit',
        'spec_min',
        'spec_max',
        'value_numeric',
        'value_boolean',
        'value_text',
        'is_passed',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'spec_min' => 'decimal:4',
            'spec_max' => 'decimal:4',
            'value_numeric' => 'decimal:4',
            'value_boolean' => 'boolean',
            'is_passed' => 'boolean',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * Evaluate pass/fail based on the criterion type and recorded value.
     * Returns null when there is not enough data to decide.
     */
    public function evaluate(): ?bool
    {
        return match ($this->criterion_type) {
            self::TYPE_MEASUREMENT => $this->evaluateMeasurement(),
            self::TYPE_PASS_FAIL, self::TYPE_VISUAL, self::TYPE_FUNCTIONAL => $this->value_boolean,
            default => null,
        };
    }

    private function evaluateMeasurement(): ?bool
    {
        if ($this->value_numeric === null) {
            return null;
        }

        $value = (float) $this->value_numeric;

        if ($this->spec_min !== null && $value < (float) $this->spec_min) {
            return false;
        }

        if ($this->spec_max !== null && $value > (float) $this->spec_max) {
            return false;
        }

        return true;
    }
}
