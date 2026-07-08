<?php

namespace Database\Factories;

use App\Models\ProcessTemplate;
use App\Models\TemplateStepChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TemplateStepChecklistItem>
 */
class TemplateStepChecklistItemFactory extends Factory
{
    protected $model = TemplateStepChecklistItem::class;

    public function definition(): array
    {
        return [
            'process_template_id' => ProcessTemplate::factory(),
            'template_step_id' => null,
            'label' => fake()->sentence(4),
            'is_required' => false,
            'sort_order' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }
}
