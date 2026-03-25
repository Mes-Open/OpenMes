<?php

namespace Database\Factories;

use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;

class ToolFactory extends Factory
{
    protected $model = Tool::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('TOOL-####'),
            'name' => $this->faker->word() . ' Tool',
            'status' => 'available',
        ];
    }
}
