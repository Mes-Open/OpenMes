<?php

namespace Database\Factories;

use App\Models\ScrapReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScrapReason>
 */
class ScrapReasonFactory extends Factory
{
    protected $model = ScrapReason::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'code' => 'SCR-' . str_pad((string) $counter++, 3, '0', STR_PAD_LEFT),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(ScrapReason::CATEGORIES),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function category(string $category): static
    {
        return $this->state(['category' => $category]);
    }
}
