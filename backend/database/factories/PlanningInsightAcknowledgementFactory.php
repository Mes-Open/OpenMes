<?php

namespace Database\Factories;

use App\Models\PlanningInsightAcknowledgement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanningInsightAcknowledgement>
 */
class PlanningInsightAcknowledgementFactory extends Factory
{
    protected $model = PlanningInsightAcknowledgement::class;

    public function definition(): array
    {
        return [
            'insight_key' => 'untracked:'.$this->faker->unique()->numberBetween(1, 100000).':2026-11-23',
            'insight_type' => 'untracked_line',
            'action' => PlanningInsightAcknowledgement::ACTION_ACKNOWLEDGE,
            'title' => 'Painting is planned without people',
            'summary' => 'Acknowledged',
            'signature' => md5('x'),
            'payload' => null,
        ];
    }
}
