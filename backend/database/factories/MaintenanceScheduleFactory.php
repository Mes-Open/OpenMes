<?php

namespace Database\Factories;

use App\Models\MaintenanceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    protected $model = MaintenanceSchedule::class;

    public function definition(): array
    {
        return [
            'name'             => fake()->words(3, true) . ' Schedule',
            'description'      => fake()->sentence(),
            'event_type'       => 'planned',
            'frequency'        => MaintenanceSchedule::FREQ_WEEKLY,
            'interval_value'   => 1,
            'preferred_time'   => null,
            'lead_time_days'   => 0,
            'last_executed_at' => null,
            'next_due_at'      => now()->addWeek(),
            'is_active'        => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function due(): static
    {
        return $this->state(fn () => ['next_due_at' => now()->subHour()]);
    }
}
