<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use App\Support\InspectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Admin → Users → Edit grants one person the quality permissions on top of
 * their role — how an operator becomes a "Quality inspector".
 */
class UserQualityPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create(['name' => 'Operator A', 'username' => 'opa', 'email' => 'opa@example.test']);
        $this->operator->assignRole('Operator');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'account_type' => 'user',
            'name' => 'Operator A',
            'username' => 'opa',
            'email' => 'opa@example.test',
            'role' => 'Operator',
        ], $overrides);
    }

    public function test_admin_grants_and_revokes_the_inspector_permission(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->operator), $this->payload(['permissions' => [InspectionPermissions::PERFORM]]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->operator->refresh();
        $this->assertTrue($this->operator->hasDirectPermission(InspectionPermissions::PERFORM));
        $this->assertFalse($this->operator->hasDirectPermission(InspectionPermissions::DISPOSITION));

        $this->get(route('admin.users.edit', $this->operator))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('user.permissions', [InspectionPermissions::PERFORM])
                ->has('grantablePermissions', 2)
                ->where('rolePermissions.Supervisor', InspectionPermissions::ALL));

        $this->put(route('admin.users.update', $this->operator), $this->payload(['permissions' => []]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->operator->fresh()->hasDirectPermission(InspectionPermissions::PERFORM));
    }

    public function test_other_direct_permissions_survive_a_save(): void
    {
        // A permission this form does not manage (a module's, say) is left alone.
        $this->operator->givePermissionTo(Permission::findOrCreate('manage tools', 'web'));

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->operator), $this->payload(['permissions' => [InspectionPermissions::DISPOSITION]]))
            ->assertSessionHasNoErrors();

        $this->operator->refresh();
        $this->assertTrue($this->operator->hasDirectPermission('manage tools'));
        $this->assertTrue($this->operator->hasDirectPermission(InspectionPermissions::DISPOSITION));
    }

    public function test_a_save_without_the_field_keeps_the_grant(): void
    {
        $this->operator->givePermissionTo(InspectionPermissions::PERFORM);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->operator), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->operator->fresh()->hasDirectPermission(InspectionPermissions::PERFORM));
    }

    public function test_a_new_account_can_be_created_with_the_permission(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->payload([
                'username' => 'inspector',
                'email' => 'inspector@example.test',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'permissions' => [InspectionPermissions::PERFORM],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('username', 'inspector')->first()->hasDirectPermission(InspectionPermissions::PERFORM));
    }

    public function test_only_the_offered_permissions_can_be_granted(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->operator), $this->payload(['permissions' => ['manage users']]))
            ->assertSessionHasErrors('permissions.0');

        $this->assertFalse($this->operator->fresh()->hasDirectPermission('manage users'));
    }

    public function test_a_user_without_the_admin_tab_cannot_grant(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->put(route('admin.users.update', $this->operator), $this->payload(['permissions' => [InspectionPermissions::DISPOSITION]]))
            ->assertForbidden();

        $this->assertFalse($this->operator->fresh()->hasDirectPermission(InspectionPermissions::DISPOSITION));
    }
}
