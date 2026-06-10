<?php

namespace Database\Factories;

use App\Models\EmployeeActivity;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeActivity>
 */
class EmployeeActivityFactory extends Factory
{
    protected $model = EmployeeActivity::class;

    public function definition(): array
    {
        $start = now()->subHours(2);

        return [
            'worker_id' => Worker::factory(),
            'type' => 'work',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
        ];
    }

    /**
     * Span an exact number of hours (used to make labor-cost math deterministic).
     */
    public function hours(float $hours): static
    {
        return $this->state(function () use ($hours) {
            $start = now()->subHours(8);

            return [
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes((int) round($hours * 60)),
            ];
        });
    }

    public function type(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
