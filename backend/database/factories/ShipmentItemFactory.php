<?php

namespace Database\Factories;

use App\Models\Pallet;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\UnitCarton;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentItem>
 */
class ShipmentItemFactory extends Factory
{
    protected $model = ShipmentItem::class;

    /** Rows of a shipment belong to the shipment's tenant. */
    public function configure(): static
    {
        return $this->afterMaking(function (ShipmentItem $row): void {
            $row->tenant_id ??= Shipment::withoutGlobalScopes()->whereKey($row->shipment_id)->value('tenant_id');
        });
    }

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'unit_type' => ShipmentItem::TYPE_PALLET,
            'identifier' => 'PAL-'.fake()->unique()->numerify('9#####'),
        ];
    }

    /** Expect this pallet on the shipment. */
    public function forPallet(Pallet $pallet): static
    {
        return $this->state(fn () => [
            'unit_type' => ShipmentItem::TYPE_PALLET,
            'identifier' => $pallet->pallet_no,
            'pallet_id' => $pallet->id,
        ]);
    }

    /** Expect this carton on the shipment. */
    public function forCarton(UnitCarton $carton): static
    {
        return $this->state(fn () => [
            'unit_type' => ShipmentItem::TYPE_CARTON,
            'identifier' => $carton->carton_no,
            'carton_id' => $carton->id,
        ]);
    }
}
