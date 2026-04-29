<?php

namespace Database\Factories;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LotSequenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' LOT',
            'product_type_id' => null,
            'prefix' => fake()->lexify('???'),
            'suffix' => null,
            'next_number' => 1,
            'pad_size' => 4,
            'year_prefix' => true,
        ];
    }

    public function forProductType(ProductType $productType): static
    {
        return $this->state(fn () => [
            'product_type_id' => $productType->id,
        ]);
    }
}
