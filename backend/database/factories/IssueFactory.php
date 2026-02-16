<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\IssueType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Issue>
 */
class IssueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'batch_step_id' => null,
            'issue_type_id' => IssueType::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'OPEN',
            'reported_by_id' => User::factory(),
            'assigned_to_id' => null,
            'reported_at' => now(),
        ];
    }
}
