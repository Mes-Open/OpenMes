<?php

namespace Database\Factories;

use App\Enums\PriorityCondition;
use App\Enums\PriorityRuleSource;
use App\Models\PriorityRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriorityRule>
 */
class PriorityRuleFactory extends Factory
{
    protected $model = PriorityRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'field_source' => PriorityRuleSource::CustomerTier->value,
            'condition_type' => PriorityCondition::Equals->value,
            'condition_value' => 'gold',
            'condition_value_max' => null,
            'points' => fake()->numberBetween(5, 50),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** Convenience state for a fully-specified rule. */
    public function rule(PriorityRuleSource $source, PriorityCondition $condition, ?string $value, int $points, ?string $max = null): static
    {
        return $this->state([
            'field_source' => $source->value,
            'condition_type' => $condition->value,
            'condition_value' => $value,
            'condition_value_max' => $max,
            'points' => $points,
        ]);
    }
}
