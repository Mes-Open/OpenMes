<?php

namespace Database\Factories;

use App\Models\Crew;
use App\Models\CrewBreakWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrewBreakWindowFactory extends Factory
{
    protected $model = CrewBreakWindow::class;

    public function definition(): array
    {
        return [
            'crew_id' => Crew::factory(),
            'name' => 'Lunch',
            'start_time' => '12:00',
            'end_time' => '12:30',
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ];
    }
}
