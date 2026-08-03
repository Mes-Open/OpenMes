<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\StockDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockDocumentLine>
 */
class StockDocumentLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stock_document_id' => StockDocument::factory(),
            'material_id' => Material::factory(),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'unit_of_measure' => 'kg',
            'sort_order' => 0,
        ];
    }
}
