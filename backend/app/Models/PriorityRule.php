<?php

namespace App\Models;

use App\Enums\PriorityCondition;
use App\Enums\PriorityRuleSource;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriorityRule extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'name',
        'field_source',
        'condition_type',
        'condition_value',
        'condition_value_max',
        'points',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'field_source' => PriorityRuleSource::class,
            'condition_type' => PriorityCondition::class,
            'points' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
