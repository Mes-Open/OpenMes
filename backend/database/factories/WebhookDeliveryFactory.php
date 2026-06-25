<?php

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\WebhookEventRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event_type' => WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED,
            'payload' => ['event' => WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED, 'data' => [], 'timestamp' => now()->toIso8601String()],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDelivery::STATUS_SUCCESS,
            'attempts' => 1,
            'response_code' => 200,
            'delivered_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDelivery::STATUS_FAILED,
            'attempts' => 5,
            'error' => 'Connection timed out',
        ]);
    }
}
