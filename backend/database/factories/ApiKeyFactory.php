<?php

namespace Database\Factories;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        // Rows built by the factory carry a random (unrecoverable) secret — fine
        // for listing/negative tests. Auth tests mint keys via ApiKey::issue()
        // to obtain the plaintext, or override key_hash with a known secret.
        $plaintext = ApiKey::PREFIX.Str::random(40);

        return [
            'integration_config_id' => null,
            'name' => $this->faker->words(2, true).' key',
            'key_prefix' => substr($plaintext, 0, 12),
            'key_hash' => ApiKey::hashSecret($plaintext),
            'scopes' => ApiScope::values(),
            'ip_allowlist' => null,
            'is_active' => true,
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Restrict the key to the given scopes.
     *
     * @param  array<int, ApiScope|string>  $scopes
     */
    public function withScopes(array $scopes): static
    {
        return $this->state(fn () => [
            'scopes' => array_map(fn ($s) => $s instanceof ApiScope ? $s->value : $s, $scopes),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
