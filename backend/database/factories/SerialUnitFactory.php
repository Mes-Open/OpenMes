<?php

namespace Database\Factories;

use App\Models\SerialUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class SerialUnitFactory extends Factory
{
    protected $model = SerialUnit::class;

    public function definition(): array
    {
        return [
            'serial_no' => 'SN-' . fake()->unique()->numerify('########'),
            'status' => SerialUnit::STATUS_IN_PRODUCTION,
            'produced_at' => null,
        ];
    }
}
