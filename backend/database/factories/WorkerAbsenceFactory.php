<?php

namespace Database\Factories;

use App\Models\Worker;
use App\Models\WorkerAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkerAbsenceFactory extends Factory
{
    protected $model = WorkerAbsence::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'type' => 'vacation',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(2)->toDateString(),
            'all_day' => true,
            'status' => 'approved',
        ];
    }
}
