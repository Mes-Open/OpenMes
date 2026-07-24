<?php

namespace Database\Factories;

use App\Enums\RevisionLifecycle;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductRevisionFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 0;
        $codes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        return [
            'product_type_id' => ProductType::factory(),
            'revision_code' => $codes[$counter++ % count($codes)].fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->optional()->sentence(),
            'lifecycle_status' => RevisionLifecycle::Draft,
            'process_template_id' => null,
        ];
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => RevisionLifecycle::Released,
            'released_at' => now(),
        ]);
    }

    public function obsolete(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => RevisionLifecycle::Obsolete,
            'released_at' => now()->subMonth(),
            'obsolete_at' => now(),
        ]);
    }
}
