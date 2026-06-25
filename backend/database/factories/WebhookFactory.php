<?php

namespace Database\Factories;

use App\Support\WebhookEventRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Webhook>
 */
class WebhookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)).' hook',
            'url' => 'https://example.com/hooks/'.$this->faker->unique()->numerify('########'),
            'secret' => $this->faker->sha256(),
            'events' => [WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED],
            'headers' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * @param  array<int, string>  $events
     */
    public function subscribedTo(array $events): static
    {
        return $this->state(fn () => ['events' => $events]);
    }
}
