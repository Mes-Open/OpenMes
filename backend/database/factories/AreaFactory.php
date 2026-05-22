<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id'     => Site::factory(),
            'name'        => ucfirst($this->faker->word()) . ' Area',
            'code'        => 'AREA-' . $this->faker->unique()->numerify('####'),
            'description' => $this->faker->sentence(),
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
