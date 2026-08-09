<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IntegrationConfig>
 */
class IntegrationConfigFactory extends Factory
{
    public function definition(): array
    {
        return [
            // system_type is unique among live rows, so vary it by default and let a
            // state pin it when a test is about one specific integration.
            'system_type' => 'erp_custom_'.$this->faker->unique()->numberBetween(1000, 999999),
            'system_name' => $this->faker->company().' ERP',
            'api_config' => [],
            'is_active' => true,
        ];
    }

    /**
     * A configured Datalab Pantheon connection. HTTPS by default — the connector
     * refuses plain http:// unless the config opts in.
     *
     * @param  array<string, mixed>  $config
     */
    public function pantheon(array $config = []): static
    {
        return $this->state([
            'system_type' => 'pantheon',
            'system_name' => 'Datalab Pantheon',
            'api_config' => [
                'base_url' => 'https://paws.plant.local',
                'username' => 'openmes',
                'password' => 'secret',
                'company_db' => 'DEMO',
                ...$config,
            ],
        ]);
    }
}
