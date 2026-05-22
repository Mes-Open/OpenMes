<?php

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Skill>
 */
class SkillFactory extends Factory
{
    protected $model = Skill::class;

    public function definition(): array
    {
        return [
            'code'        => 'SKL-' . $this->faker->unique()->numerify('###'),
            'name'        => ucfirst($this->faker->word()),
            'description' => $this->faker->sentence(),
        ];
    }
}
