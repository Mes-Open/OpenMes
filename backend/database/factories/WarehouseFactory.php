<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Warehouse>
 */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Sequenced code: the partial unique index on (code, tenant) is real.
            'code' => 'WH-'.$this->faker->unique()->numberBetween(1000, 999999),
            'name' => $this->faker->words(2, true).' warehouse',
            'kind' => Warehouse::KIND_MIXED,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function rawMaterial(): static
    {
        return $this->state(['kind' => Warehouse::KIND_RAW_MATERIAL]);
    }

    public function finishedGoods(): static
    {
        return $this->state(['kind' => Warehouse::KIND_FINISHED_GOODS]);
    }

    /**
     * Only one warehouse per kind may be the default (partial unique index), so
     * use this on a single warehouse per kind in a test.
     */
    public function isDefault(): static
    {
        return $this->state(['is_default' => true]);
    }
}
