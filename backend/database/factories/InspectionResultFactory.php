<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class InspectionResultFactory extends Factory
{
    protected $model = InspectionResult::class;

    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'criterion_name' => fake()->words(2, true),
            'criterion_type' => InspectionResult::TYPE_PASS_FAIL,
            'required' => true,
            'unit' => null,
            'spec_min' => null,
            'spec_max' => null,
            'value_numeric' => null,
            'value_boolean' => true,
            'value_text' => null,
            'is_passed' => true,
        ];
    }
}
