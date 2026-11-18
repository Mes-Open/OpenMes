<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\ShipmentScan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentScan>
 */
class ShipmentScanFactory extends Factory
{
    protected $model = ShipmentScan::class;

    /** Rows of a shipment belong to the shipment's tenant. */
    public function configure(): static
    {
        return $this->afterMaking(function (ShipmentScan $row): void {
            $row->tenant_id ??= Shipment::withoutGlobalScopes()->whereKey($row->shipment_id)->value('tenant_id');
        });
    }

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'scanned_code' => 'PAL-'.fake()->numerify('######'),
            'result' => ShipmentScan::RESULT_UNKNOWN,
            'scanned_at' => now(),
        ];
    }
}
