<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialLot>
 */
class MaterialLotFactory extends Factory
{
    protected $model = MaterialLot::class;

    public function definition(): array
    {
        $received = (float) fake()->numberBetween(10, 1000);

        return [
            'lot_number' => 'LOT-' . fake()->unique()->numerify('#####'),
            'material_id' => Material::factory(),
            'quantity_received' => $received,
            // Default to a fresh lot (received = available). State helpers below
            // can override when simulating partial / full consumption.
            'quantity_available' => fn (array $attrs) => $attrs['quantity_received'] ?? $received,
            'unit_of_measure' => 'pcs',
            'received_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'manufacturing_date' => now()->subDays(fake()->numberBetween(30, 90))->toDateString(),
            'expiry_date' => now()->addMonths(fake()->numberBetween(3, 24))->toDateString(),
            'status' => MaterialLot::STATUS_RELEASED,
            'supplier_lot_no' => fake()->bothify('SUP-#####'),
        ];
    }

    public function received(): static
    {
        return $this->state(fn () => ['status' => MaterialLot::STATUS_RECEIVED]);
    }

    public function quarantine(): static
    {
        return $this->state(fn () => ['status' => MaterialLot::STATUS_QUARANTINE]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expiry_date' => now()->subDay()->toDateString(),
            'status' => MaterialLot::STATUS_EXPIRED,
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => [
            'quantity_available' => 0,
            'status' => MaterialLot::STATUS_CONSUMED,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => MaterialLot::STATUS_REJECTED]);
    }
}
