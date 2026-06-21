<?php

namespace Database\Factories;

use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityControlTask>
 */
class QualityControlTaskFactory extends Factory
{
    protected $model = QualityControlTask::class;

    public function definition(): array
    {
        return [
            'quality_control_trigger_id' => QualityControlTrigger::factory(),
            'status' => QualityControlTask::STATUS_DUE,
            'due_reason' => 'Test control',
            'fired_at' => now(),
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => QualityControlTask::STATUS_DONE,
            'completed_at' => now(),
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn () => [
            'status' => QualityControlTask::STATUS_SKIPPED,
            'completed_at' => now(),
        ]);
    }
}
