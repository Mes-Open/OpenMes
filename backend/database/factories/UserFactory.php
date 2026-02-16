<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'force_password_change' => false,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user should be forced to change password.
     */
    public function forcePasswordChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'force_password_change' => true,
        ]);
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('Admin');
        });
    }

    /**
     * Create a supervisor user.
     */
    public function supervisor(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('Supervisor');
        });
    }

    /**
     * Create an operator user.
     */
    public function operator(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('Operator');
        });
    }
}
