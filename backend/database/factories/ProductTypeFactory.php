<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductType>
 */
class ProductTypeFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 1;

        return [
            'code' => 'PROD-' . str_pad($counter++, 3, '0', STR_PAD_LEFT),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'unit_of_measure' => fake()->randomElement(['pcs', 'kg', 'm', 'ft']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
