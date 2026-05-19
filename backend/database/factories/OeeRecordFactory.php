<?php

namespace Database\Factories;

use App\Models\Line;
use App\Models\OeeRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class OeeRecordFactory extends Factory
{
    protected $model = OeeRecord::class;

    public function definition(): array
    {
        $availability = $this->faker->randomFloat(2, 70, 99);
        $performance = $this->faker->randomFloat(2, 70, 99);
        $quality = $this->faker->randomFloat(2, 90, 100);
        $oee = round($availability * $performance * $quality / 10000, 2);

        return [
            'line_id' => Line::factory(),
            'record_date' => now()->toDateString(),
            'planned_minutes' => 480,
            'operating_minutes' => 420,
            'downtime_minutes' => 60,
            'ideal_cycle_minutes' => 1.0,
            'total_produced' => 400,
            'good_produced' => 390,
            'scrap_qty' => 10,
            'availability_pct' => $availability,
            'performance_pct' => $performance,
            'quality_pct' => $quality,
            'oee_pct' => $oee,
        ];
    }
}
