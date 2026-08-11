<?php

namespace Database\Factories;

use App\Enums\ChangeEffectivePoint;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderSnapshot>
 */
class WorkOrderSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'version' => 1,
            'snapshot' => ['steps' => [], 'bom' => []],
            'effective_from' => ChangeEffectivePoint::Immediate,
        ];
    }
}
