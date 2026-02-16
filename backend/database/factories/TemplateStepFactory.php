<?php

namespace Database\Factories;

use App\Models\ProcessTemplate;
use App\Models\Workstation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TemplateStep>
 */
class TemplateStepFactory extends Factory
{
    public function definition(): array
    {
        static $stepCounter = 0;
        $stepCounter++;

        return [
            'process_template_id' => ProcessTemplate::factory(),
            'step_number' => $stepCounter,
            'name' => fake()->words(3, true),
            'instruction' => fake()->sentence(),
            'estimated_duration_minutes' => fake()->numberBetween(10, 120),
            'workstation_id' => null,
        ];
    }
}
