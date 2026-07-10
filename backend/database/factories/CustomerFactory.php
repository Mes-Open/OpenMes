<?php

namespace Database\Factories;

use App\Enums\Tier;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'name' => fake()->company(),
            'code' => 'CUST-'.str_pad((string) $counter++, 4, '0', STR_PAD_LEFT),
            'tier' => fake()->randomElement(Tier::cases()),
            'payment_score' => fake()->numberBetween(0, 100),
            'total_orders' => fake()->numberBetween(0, 50),
            'total_revenue' => fake()->randomFloat(2, 0, 500000),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function tier(Tier $tier): static
    {
        return $this->state(['tier' => $tier]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
