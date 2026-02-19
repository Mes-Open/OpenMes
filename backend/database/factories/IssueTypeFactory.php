<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IssueType>
 */
class IssueTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??-????')),
            'name' => fake()->words(3, true),
            'severity' => fake()->randomElement(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL']),
            'is_blocking' => false,
            'is_active' => true,
        ];
    }

    public function blocking(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blocking' => true,
        ]);
    }
}
