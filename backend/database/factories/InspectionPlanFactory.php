<?php

namespace Database\Factories;

use App\Models\InspectionPlan;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

class InspectionPlanFactory extends Factory
{
    protected $model = InspectionPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Inspection',
            'description' => fake()->sentence(),
            'material_id' => Material::factory(),
            'material_type_id' => null,
            'criteria' => [
                ['name' => 'Visual condition', 'type' => 'pass_fail', 'required' => true],
                ['name' => 'Diameter', 'type' => 'measurement', 'unit' => 'mm', 'spec_min' => 9.8, 'spec_max' => 10.2, 'required' => true],
            ],
            'is_active' => true,
            'version' => 1,
            'published_at' => now(),
            'root_id' => null,
        ];
    }

    /** A draft version (editable in place, not selectable for inspections). */
    public function draft(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'published_at' => null,
        ]);
    }
}
