<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\BatchStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BatchStep>
 */
class BatchStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'step_number' => fake()->unique()->numberBetween(1, 9999),
            'name' => fake()->words(3, true),
            'instruction' => fake()->sentence(),
            'requires_confirmation' => false,
            'status' => BatchStep::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
            'started_by_id' => null,
            'completed_by_id' => null,
            'duration_minutes' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BatchStep::STATUS_DONE,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'duration_minutes' => 60,
        ]);
    }

    /** A step whose critical instructions must be read-acknowledged before completion. */
    public function requiresConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_confirmation' => true,
        ]);
    }
}
