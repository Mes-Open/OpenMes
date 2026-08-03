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
            'unit_price' => fake()->randomFloat(4, 0.5, 50),
            'price_currency' => 'PLN',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * A subassembly made in-house. Without a producing template it is still a
     * leaf to BOM explosion — pass one to make it expandable.
     */
    public function manufactured(?int $producingTemplateId = null): static
    {
        return $this->state(fn () => [
            'is_manufactured' => true,
            'producing_process_template_id' => $producingTemplateId,
        ]);
    }

    public function withExternalCode(string $system = 'subiekt_gt'): static
    {
        return $this->state(fn () => [
            'external_code' => fake()->bothify('EXT-####'),
            'external_system' => $system,
        ]);
    }
}
