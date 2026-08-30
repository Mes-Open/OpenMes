<?php

namespace Database\Factories;

use App\Enums\WorkOrderStopType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderStop>
 */
class WorkOrderStopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'type' => WorkOrderStopType::Operational,
            'reason' => fake()->sentence(),
            'requires_change' => false,
            'produced_qty_at_stop' => 0,
            'snapshot_version_at_stop' => 1,
            'stopped_by_id' => User::factory(),
            'stopped_at' => now(),
        ];
    }

    /** A stop raised because the configuration must change before work continues. */
    public function requiringChange(): static
    {
        return $this->state(fn () => [
            'type' => WorkOrderStopType::EngineeringChange,
            'requires_change' => true,
        ]);
    }

    public function resumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'resumed_by_id' => User::factory(),
            'resumed_at' => now(),
            'duration_minutes' => 30,
            'resulting_status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
    }
}
