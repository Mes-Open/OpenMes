<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaterialAllocation>
 */
class MaterialAllocationFactory extends Factory
{
    protected $model = MaterialAllocation::class;

    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'material_id' => Material::factory(),
            'work_order_id' => WorkOrder::factory(),
            'allocated_qty' => fake()->randomFloat(4, 1, 100),
            'consumed_qty' => 0,
            'status' => MaterialAllocation::STATUS_ALLOCATED,
            'allocated_at' => now(),
        ];
    }

    /**
     * Mark as consumed with a recorded quantity and a price snapshot.
     */
    public function consumed(float $qty, ?float $snapshotPrice = null, string $currency = 'PLN'): static
    {
        return $this->state(fn () => [
            'consumed_qty' => $qty,
            'status' => MaterialAllocation::STATUS_CONSUMED,
            'consumed_at' => now(),
            'unit_price_snapshot' => $snapshotPrice,
            'price_currency_snapshot' => $snapshotPrice !== null ? $currency : null,
        ]);
    }
}
