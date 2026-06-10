<?php

namespace Database\Factories;

use App\Models\AdditionalCost;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdditionalCost>
 */
class AdditionalCostFactory extends Factory
{
    protected $model = AdditionalCost::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 5, 500),
            'currency' => 'PLN',
        ];
    }
}
