<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InspectionFactory extends Factory
{
    protected $model = Inspection::class;

    public function definition(): array
    {
        return [
            'inspection_plan_id' => null,
            'material_id' => Material::factory(),
            'lot_number' => 'LOT-' . fake()->numerify('######'),
            'supplier_lot_ref' => null,
            'quantity_received' => fake()->randomFloat(2, 10, 500),
            'inspector_id' => User::factory(),
            'started_at' => now(),
            'completed_at' => null,
            'status' => Inspection::STATUS_PENDING,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => Inspection::STATUS_PENDING, 'completed_at' => null]);
    }

    public function passed(): static
    {
        return $this->state(['status' => Inspection::STATUS_PASS, 'completed_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(['status' => Inspection::STATUS_FAIL, 'completed_at' => now()]);
    }
}
