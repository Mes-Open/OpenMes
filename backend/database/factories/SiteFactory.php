<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->company() . ' Plant',
            'code'        => 'SITE-' . $this->faker->unique()->numerify('####'),
            'description' => $this->faker->sentence(),
            'address'     => $this->faker->streetAddress(),
            'city'        => $this->faker->city(),
            'country'     => strtoupper($this->faker->countryCode()),
            'timezone'    => 'Europe/Warsaw',
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
