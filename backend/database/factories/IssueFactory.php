<?php

namespace Database\Factories;

use App\Enums\IssueDisposition;
use App\Models\IssueType;
use App\Models\User;
use App\Models\WorkOrder;
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
            'disposition' => IssueDisposition::Pending->value,
            'reported_by_id' => User::factory(),
            'assigned_to_id' => null,
            'reported_at' => now(),
        ];
    }

    public function scrap(): static
    {
        return $this->state(fn () => ['disposition' => IssueDisposition::Scrap->value]);
    }

    public function rework(): static
    {
        return $this->state(fn () => ['disposition' => IssueDisposition::Rework->value]);
    }

    public function returnToSupplier(): static
    {
        return $this->state(fn () => ['disposition' => IssueDisposition::ReturnToSupplier->value]);
    }

    public function useAsIs(): static
    {
        return $this->state(fn () => ['disposition' => IssueDisposition::UseAsIs->value]);
    }
}
