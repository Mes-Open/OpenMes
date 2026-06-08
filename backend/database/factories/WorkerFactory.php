<?php

namespace Database\Factories;

use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Worker>
 */
class WorkerFactory extends Factory
{
    protected $model = Worker::class;

    public function definition(): array
    {
        return [
            'code' => 'W-'.$this->faker->unique()->numerify('####'),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function paidHourly(float $rate = 40, string $currency = 'PLN'): static
    {
        return $this->state(fn () => [
            'pay_type' => 'hourly',
            'pay_rate' => $rate,
            'pay_currency' => $currency,
        ]);
    }

    public function paidWeekly(float $rate = 1600, string $currency = 'PLN'): static
    {
        return $this->state(fn () => [
            'pay_type' => 'weekly',
            'pay_rate' => $rate,
            'pay_currency' => $currency,
        ]);
    }

    public function paidPiece(float $rate = 2, string $currency = 'PLN'): static
    {
        return $this->state(fn () => [
            'pay_type' => 'piece_rate',
            'pay_rate' => $rate,
            'pay_currency' => $currency,
        ]);
    }
}
