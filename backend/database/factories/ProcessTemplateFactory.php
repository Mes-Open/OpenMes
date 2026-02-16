<?php

namespace Database\Factories;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcessTemplate>
 */
class ProcessTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_type_id' => ProductType::factory(),
            'name' => fake()->words(3, true),
            'version' => 1,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withSteps(int $count = 3): static
    {
        return $this->afterCreating(function ($template) use ($count) {
            \App\Models\TemplateStep::factory($count)->create([
                'process_template_id' => $template->id,
            ]);
        });
    }
}
