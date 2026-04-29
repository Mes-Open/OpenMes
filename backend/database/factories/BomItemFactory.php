<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\ProcessTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'process_template_id' => ProcessTemplate::factory(),
            'material_id' => Material::factory(),
            'quantity_per_unit' => fake()->randomFloat(4, 0.001, 100),
            'scrap_percentage' => 0,
            'consumed_at' => 'start',
            'sort_order' => 0,
        ];
    }
}
