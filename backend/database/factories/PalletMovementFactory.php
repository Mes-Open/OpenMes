<?php

namespace Database\Factories;

use App\Models\Pallet;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PalletMovement>
 */
class PalletMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pallet_id' => Pallet::factory(),
            'worker_id' => Worker::factory()->logistics(),
            'from_location' => fake()->optional()->bothify('A-##-##'),
            'to_location' => fake()->bothify('B-##-##'),
            'moved_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
