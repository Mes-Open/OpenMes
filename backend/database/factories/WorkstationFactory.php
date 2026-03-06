<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workstation>
 */
class WorkstationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code'      => strtoupper($this->faker->lexify('WS-???')),
            'name'      => $this->faker->words(2, true),
            'is_active' => true,
        ];
    }
}
