<?php

namespace Database\Factories;

use App\Models\WorkstationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkstationType>
 */
class WorkstationTypeFactory extends Factory
{
    protected $model = WorkstationType::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('WT-###')),
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'is_active' => true,
        ];
    }
}
