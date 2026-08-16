<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WarehouseStock>
 */
class WarehouseStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'material_id' => Material::factory(),
            'product_type_id' => null,
            'material_lot_id' => null,
            'quantity' => $this->faker->randomFloat(2, 0, 500),
            'unit_of_measure' => 'kg',
        ];
    }
}
