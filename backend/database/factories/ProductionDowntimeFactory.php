<?php

namespace Database\Factories;

use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\ProductionDowntime;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionDowntimeFactory extends Factory
{
    protected $model = ProductionDowntime::class;

    public function definition(): array
    {
        return [
            'line_id' => Line::factory(),
            'downtime_reason_id' => DowntimeReason::factory(),
            'started_at' => now(),
            'ended_at' => now()->addMinutes(15),
            'duration_minutes' => 15,
        ];
    }
}
