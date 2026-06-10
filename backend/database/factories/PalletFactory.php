<?php

namespace Database\Factories;

use App\Enums\PalletStatus;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pallet>
 */
class PalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            // pallet_no is left unset so the model's creating hook draws it from
            // the Postgres sequence — mirroring real inserts.
            'work_order_id' => WorkOrder::factory(),
            'qty' => 0,
            'status' => PalletStatus::Open->value,
            'location' => fake()->optional()->bothify('A-##-##'),
            'erp_reference' => fake()->optional()->bothify('ERP-#####'),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PalletStatus::Closed->value]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PalletStatus::Shipped->value]);
    }
}
