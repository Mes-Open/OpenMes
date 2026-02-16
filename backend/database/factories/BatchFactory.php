<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Batch>
 */
class BatchFactory extends Factory
{
    public function definition(): array
    {
        static $batchCounter = 1;

        return [
            'work_order_id' => WorkOrder::factory(),
            'batch_number' => $batchCounter++,
            'target_qty' => fake()->numberBetween(10, 100),
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Batch::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
        ]);
    }

    public function done(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Batch::STATUS_DONE,
                'produced_qty' => $attributes['target_qty'],
                'started_at' => now()->subHours(2),
                'completed_at' => now(),
            ];
        });
    }
}
