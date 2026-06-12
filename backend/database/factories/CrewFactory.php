<?php

namespace Database\Factories;

use App\Models\Crew;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrewFactory extends Factory
{
    protected $model = Crew::class;

    public function definition(): array
    {
        return [
            'code' => 'CREW-'.strtoupper($this->faker->unique()->bothify('??##')),
            'name' => $this->faker->words(2, true).' Crew',
            'is_active' => true,
        ];
    }
}
