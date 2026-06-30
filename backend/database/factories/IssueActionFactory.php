<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueAction;
use Illuminate\Database\Eloquent\Factories\Factory;

class IssueActionFactory extends Factory
{
    protected $model = IssueAction::class;

    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'type' => IssueAction::TYPE_CORRECTIVE,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->sentence(),
            'assigned_to_id' => null,
            'due_date' => null,
            'status' => IssueAction::STATUS_OPEN,
        ];
    }

    public function preventive(): static
    {
        return $this->state(fn () => ['type' => IssueAction::TYPE_PREVENTIVE]);
    }

    public function containment(): static
    {
        return $this->state(fn () => ['type' => IssueAction::TYPE_CONTAINMENT]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => IssueAction::STATUS_VERIFIED,
            'completed_at' => now(),
            'verified_at' => now(),
        ]);
    }

    /** Outstanding action with a due date in the past. */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => IssueAction::STATUS_OPEN,
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
    }
}
