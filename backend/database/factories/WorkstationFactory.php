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
            // Unique so a test that creates several workstations can't hit the
            // workstations.code unique index by chance (WS-??? is only 26^3 codes).
            'code' => strtoupper($this->faker->unique()->lexify('WS-???')),
            'name' => $this->faker->words(2, true),
            'is_active' => true,
        ];
    }
}
