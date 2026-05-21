<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RequestLog>
 */
class RequestLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $method = fake()->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
        $isMutating = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        return [
            'user_id'     => User::factory(),
            'method'      => $method,
            'path'        => '/'.fake()->slug(),
            'route_name'  => null,
            'status'      => fake()->randomElement([200, 201, 302, 404, 500]),
            'duration_ms' => fake()->numberBetween(1, 2000),
            'ip_address'  => fake()->ipv4(),
            'user_agent'  => substr(fake()->userAgent(), 0, 500),
            'sampled'     => ! $isMutating,
        ];
    }
}
