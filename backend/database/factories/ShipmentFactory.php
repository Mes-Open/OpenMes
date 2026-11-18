<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'shipment_no' => 'LOAD-'.fake()->unique()->numerify('######'),
            'erp_reference' => fake()->optional()->bothify('SO-#####'),
            'carrier' => fake()->company(),
            'vehicle_no' => strtoupper(fake()->bothify('??-####')),
            'destination' => fake()->city(),
            'planned_at' => now()->addHours(2),
            'status' => ShipmentStatus::Planned->value,
        ];
    }

    public function loading(): static
    {
        return $this->state(fn () => ['status' => ShipmentStatus::Loading->value, 'started_at' => now()]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => ShipmentStatus::Confirmed->value, 'confirmed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => ShipmentStatus::Cancelled->value]);
    }
}
