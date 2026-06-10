<?php

namespace Database\Factories;

use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScrapEntry>
 */
class ScrapEntryFactory extends Factory
{
    protected $model = ScrapEntry::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'scrap_reason_id' => ScrapReason::factory(),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'batch_step_id' => null,
            'shift_id' => null,
            'notes' => fake()->optional()->sentence(),
            'reported_by' => User::factory(),
            'reported_at' => now(),
        ];
    }
}
