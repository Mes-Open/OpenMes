<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensoryEvaluation extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const DIMENSIONS = [
        'appearance_score' => '外形',
        'color_score' => '汤色',
        'aroma_score' => '香气',
        'taste_score' => '滋味',
        'leaf_score' => '叶底',
    ];

    protected $fillable = [
        'blind_code',
        'blend_formula_id',
        'evaluator_id',
        'sample_sequence',
        'status',
        'appearance_score',
        'color_score',
        'aroma_score',
        'taste_score',
        'leaf_score',
        'comment',
        'standard_sample_code',
        'standard_scores',
        'submitted_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'appearance_score' => 'integer',
            'color_score' => 'integer',
            'aroma_score' => 'integer',
            'taste_score' => 'integer',
            'leaf_score' => 'integer',
            'standard_scores' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function blendFormula(): BelongsTo
    {
        return $this->belongsTo(BlendFormula::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function scoreTotal(): ?int
    {
        $scores = [];
        foreach (array_keys(self::DIMENSIONS) as $field) {
            if ($this->{$field} === null) {
                return null;
            }
            $scores[] = (int) $this->{$field};
        }

        return array_sum($scores);
    }

    public function standardTotal(): ?int
    {
        if (! is_array($this->standard_scores)) {
            return null;
        }

        $scores = [];
        foreach (array_keys(self::DIMENSIONS) as $field) {
            if (! isset($this->standard_scores[$field])) {
                return null;
            }
            $scores[] = (int) $this->standard_scores[$field];
        }

        return array_sum($scores);
    }
}
