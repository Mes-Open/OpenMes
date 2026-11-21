<?php

namespace Database\Factories;

use App\Models\UnitCarton;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitCarton>
 */
class UnitCartonFactory extends Factory
{
    protected $model = UnitCarton::class;

    public function definition(): array
    {
        return [
            // carton_no is left unset so the model's creating hook numbers it.
            'status' => UnitCarton::STATUS_OPEN,
            'qty' => 0,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => UnitCarton::STATUS_CLOSED, 'closed_at' => now()]);
    }
}
