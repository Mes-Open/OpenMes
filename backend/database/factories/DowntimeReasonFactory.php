<?php

namespace Database\Factories;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use Illuminate\Database\Eloquent\Factories\Factory;

class DowntimeReasonFactory extends Factory
{
    protected $model = DowntimeReason::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'name' => fake()->words(2, true),
            'code' => 'reason-' . str_pad($counter++, 3, '0', STR_PAD_LEFT),
            'kind' => DowntimeKind::Unplanned->value,
            'is_active' => true,
        ];
    }

    public function planned(): static
    {
        return $this->state(['kind' => DowntimeKind::Planned->value]);
    }

    public function unplanned(): static
    {
        return $this->state(['kind' => DowntimeKind::Unplanned->value]);
    }

    public function changeover(): static
    {
        return $this->state(['kind' => DowntimeKind::Changeover->value]);
    }
}
