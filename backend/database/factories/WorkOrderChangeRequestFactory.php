<?php

namespace Database\Factories;

use App\Enums\ChangeEffectivePoint;
use App\Enums\ChangeRequestStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderChangeRequest>
 */
class WorkOrderChangeRequestFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 1;

        return [
            'code' => 'CR/'.now()->year.'/'.str_pad((string) $counter++, 4, '0', STR_PAD_LEFT),
            'work_order_id' => WorkOrder::factory(),
            'title' => fake()->sentence(4),
            'reason' => fake()->sentence(),
            'status' => ChangeRequestStatus::Draft,
            // Something harmless by default: a schedule change touches no execution
            // data, so a factory-made request never accidentally needs a BOM.
            'proposed' => ['description' => fake()->sentence()],
            'effective_from' => ChangeEffectivePoint::NextBatch,
            'requested_by_id' => User::factory(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => ChangeRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->submitted()->state(fn () => [
            'status' => ChangeRequestStatus::Approved,
            'approved_by_id' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
