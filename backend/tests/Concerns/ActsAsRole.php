<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One way for a test to get a user with a given role or set of permissions.
 *
 * Roles and permissions come from the real RolesAndPermissionsSeeder — the one
 * docker-entrypoint.sh runs on every container start — so a test sees exactly
 * the grants a fresh installation has, not a hand-made approximation that
 * drifts from it. The seeder runs once per test, the first time a helper needs it.
 *
 * Meant to replace the per-file private admin()/operator()/supervisor()
 * helpers over time; new tests should use it.
 */
trait ActsAsRole
{
    /** Seed roles and permissions unless this test already has them. */
    protected function seedRolesAndPermissions(): void
    {
        if (Role::query()->where('name', 'Admin')->where('guard_name', 'web')->doesntExist()) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * A new user holding exactly one seeded role.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(string $role, array $attributes = []): User
    {
        $this->seedRolesAndPermissions();

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    /** A new user with the role, signed in for the requests that follow. */
    protected function actingAsRole(string $role): User
    {
        $user = $this->userWithRole($role);
        $this->actingAs($user);

        return $user;
    }

    /**
     * A new user with no role and only these directly granted permissions.
     * Every permission must already be seeded: a typo fails the test instead of
     * quietly creating a permission the application never checks.
     *
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(array $permissions): User
    {
        $this->seedRolesAndPermissions();

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
