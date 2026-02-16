<?php

namespace Database\Factories;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\ProcessTemplate;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 1;

        // Create product type with process template
        $productType = ProductType::factory()->create();
        $processTemplate = ProcessTemplate::factory()
            ->withSteps(3)
            ->create(['product_type_id' => $productType->id]);

        return [
            'order_no' => 'WO-' . now()->format('Ymd') . '-' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
            'line_id' => Line::factory(),
            'product_type_id' => $productType->id,
            'process_snapshot' => $processTemplate->toSnapshot(),
            'planned_qty' => fake()->numberBetween(10, 500),
            'produced_qty' => 0,
            'status' => WorkOrder::STATUS_PENDING,
            'priority' => fake()->numberBetween(0, 10),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkOrder::STATUS_BLOCKED,
        ]);
    }

    public function done(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => WorkOrder::STATUS_DONE,
                'produced_qty' => $attributes['planned_qty'],
                'completed_at' => now(),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkOrder::STATUS_CANCELLED,
        ]);
    }
}
