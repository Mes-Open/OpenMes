<?php

namespace Database\Factories;

use App\Models\MaterialType;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('MAT-####'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'material_type_id' => MaterialType::factory(),
            'unit_of_measure' => fake()->randomElement(['pcs', 'kg', 'm', 'l']),
            'tracking_type' => 'none',
            'default_scrap_percentage' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withExternalCode(string $system = 'subiekt_gt'): static
    {
        return $this->state(fn () => [
            'external_code' => fake()->bothify('EXT-####'),
            'external_system' => $system,
        ]);
    }
}
