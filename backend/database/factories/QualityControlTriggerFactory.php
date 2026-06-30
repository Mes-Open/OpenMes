<?php

namespace Database\Factories;

use App\Models\QualityControlTrigger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityControlTrigger>
 */
class QualityControlTriggerFactory extends Factory
{
    protected $model = QualityControlTrigger::class;

    public function definition(): array
    {
        return [
            'name' => 'QC '.fake()->unique()->words(2, true),
            'trigger_type' => QualityControlTrigger::TYPE_IN_PRODUCTION,
            'quality_check_template_id' => null,
            'line_id' => null,
            'workstation_id' => null,
            'product_type_id' => null,
            'threshold_n' => null,
            'downtime_min_minutes' => null,
            'is_blocking' => false,
            'is_active' => true,
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn () => ['trigger_type' => $type]);
    }

    public function everyNUnits(int $n): static
    {
        return $this->state(fn () => [
            'trigger_type' => QualityControlTrigger::TYPE_EVERY_N_UNITS,
            'threshold_n' => $n,
        ]);
    }

    public function everyNMinutes(int $n): static
    {
        return $this->state(fn () => [
            'trigger_type' => QualityControlTrigger::TYPE_EVERY_N_MINUTES,
            'threshold_n' => $n,
        ]);
    }

    public function afterDowntime(): static
    {
        return $this->state(fn () => ['trigger_type' => QualityControlTrigger::TYPE_AFTER_DOWNTIME]);
    }

    public function afterSetup(): static
    {
        return $this->state(fn () => ['trigger_type' => QualityControlTrigger::TYPE_AFTER_SETUP]);
    }

    public function roaming(): static
    {
        return $this->state(fn () => ['trigger_type' => QualityControlTrigger::TYPE_ROAMING]);
    }

    public function blocking(): static
    {
        return $this->state(fn () => ['is_blocking' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
