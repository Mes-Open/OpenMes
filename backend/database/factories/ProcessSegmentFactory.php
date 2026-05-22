<?php

namespace Database\Factories;

use App\Models\ProcessSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcessSegment>
 */
class ProcessSegmentFactory extends Factory
{
    protected $model = ProcessSegment::class;

    public function definition(): array
    {
        return [
            'code' => 'PSG-' . $this->faker->unique()->numerify('####'),
            'name' => ucfirst($this->faker->words(3, true)),
            'description' => $this->faker->sentence,
            'segment_type' => $this->faker->randomElement([
                ProcessSegment::TYPE_PRODUCTION,
                ProcessSegment::TYPE_INSPECTION,
                ProcessSegment::TYPE_MAINTENANCE,
                ProcessSegment::TYPE_SETUP,
                ProcessSegment::TYPE_CLEANING,
                ProcessSegment::TYPE_TRANSPORT,
            ]),
            'estimated_duration_minutes' => $this->faker->numberBetween(5, 240),
            'required_operators' => $this->faker->numberBetween(1, 4),
            'standard_instruction' => $this->faker->paragraph,
            'required_skill_ids' => [],
            'parameters' => [],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function inspection(): static
    {
        return $this->state(fn () => ['segment_type' => ProcessSegment::TYPE_INSPECTION]);
    }

    public function production(): static
    {
        return $this->state(fn () => ['segment_type' => ProcessSegment::TYPE_PRODUCTION]);
    }
}
