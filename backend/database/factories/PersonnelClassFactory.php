<?php

namespace Database\Factories;

use App\Models\PersonnelClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersonnelClass>
 */
class PersonnelClassFactory extends Factory
{
    protected $model = PersonnelClass::class;

    public function definition(): array
    {
        return [
            'code'                        => 'PC-' . $this->faker->unique()->numerify('###'),
            'name'                        => $this->faker->jobTitle(),
            'description'                 => $this->faker->sentence(),
            'required_skill_ids'          => [],
            'default_required_cert_level' => [],
            'is_active'                   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
