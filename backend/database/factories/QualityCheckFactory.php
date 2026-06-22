<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\QualityCheck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityCheck>
 */
class QualityCheckFactory extends Factory
{
    protected $model = QualityCheck::class;

    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'pallet_id' => null,
            'checked_by' => User::factory(),
            'checked_at' => now(),
            'production_quantity' => null,
            'all_passed' => true,
            'notes' => null,
        ];
    }

    public function passed(): static
    {
        return $this->state(fn () => ['all_passed' => true]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['all_passed' => false]);
    }
}
