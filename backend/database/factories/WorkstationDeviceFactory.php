<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkstationDevice>
 */
class WorkstationDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 1;

        return [
            'device_uuid' => fake()->uuid(),
            'name' => 'Station-'.str_pad($counter++, 2, '0', STR_PAD_LEFT),
            'ip_address' => fake()->localIpv4(),
            'hostname' => fake()->domainWord(),
            'app_version' => '0.16.1',
            'line_id' => null,
            'last_seen_at' => now(),
            'registered_at' => now(),
        ];
    }

    /**
     * Device that has not heartbeat within the online window.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => now()->subMinutes(5),
        ]);
    }
}
