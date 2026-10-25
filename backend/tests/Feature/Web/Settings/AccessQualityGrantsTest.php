<?php

namespace Tests\Feature\Web\Settings;

use App\Models\User;
use App\Support\InspectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → Access grants the quality permissions to a whole role, next to
 * the tab matrix — and the seeder, which runs on every container start, keeps
 * those grants.
 */
class AccessQualityGrantsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function operatorRole(): Role
    {
        return Role::findByName('Operator', 'web')->fresh();
    }

    public function test_page_lists_the_quality_grants_per_role(): void
    {
        $this->actingAs($this->admin)->get('/settings/access')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Access')
                ->has('grants', 2)
                ->where('grants.0.key', InspectionPermissions::PERFORM)
                ->where('grantMatrix.Supervisor', InspectionPermissions::ALL)
                ->where('grantMatrix.Operator', []));
    }

    public function test_admin_grants_a_role_the_inspector_permission_and_the_seeder_keeps_it(): void
    {
        $this->actingAs($this->admin)->post('/settings/access', [
            'access' => ['Operator' => ['orders']],
            'grants' => ['Operator' => [InspectionPermissions::PERFORM], 'Admin' => []],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($this->operatorRole()->hasPermissionTo(InspectionPermissions::PERFORM));
        $this->assertFalse($this->operatorRole()->hasPermissionTo(InspectionPermissions::DISPOSITION));
        $this->assertTrue($this->operatorRole()->hasPermissionTo('tab:orders'));
        // The operational permissions the role had are untouched.
        $this->assertTrue($this->operatorRole()->hasPermissionTo('view work orders'));
        // Admin is locked to everything.
        $this->assertTrue(Role::findByName('Admin', 'web')->hasPermissionTo(InspectionPermissions::DISPOSITION));

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->assertTrue($this->operatorRole()->hasPermissionTo(InspectionPermissions::PERFORM));
        $this->assertTrue($this->operatorRole()->hasPermissionTo('tab:orders'));
    }

    public function test_a_post_without_grants_leaves_them_alone(): void
    {
        $this->operatorRole()->givePermissionTo(InspectionPermissions::PERFORM);

        $this->actingAs($this->admin)->post('/settings/access', [
            'access' => ['Operator' => []],
        ])->assertRedirect();

        $this->assertTrue($this->operatorRole()->hasPermissionTo(InspectionPermissions::PERFORM));
    }

    public function test_revoking_a_grant(): void
    {
        $this->actingAs($this->admin)->post('/settings/access', [
            'access' => [],
            'grants' => ['Supervisor' => [InspectionPermissions::PERFORM]],
        ])->assertRedirect();

        $supervisor = Role::findByName('Supervisor', 'web')->fresh();
        $this->assertTrue($supervisor->hasPermissionTo(InspectionPermissions::PERFORM));
        $this->assertFalse($supervisor->hasPermissionTo(InspectionPermissions::DISPOSITION));

        // The seeder grants Supervisors these only the first time; a revoke
        // made here survives the next container start.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->assertFalse(Role::findByName('Supervisor', 'web')->fresh()->hasPermissionTo(InspectionPermissions::DISPOSITION));
    }

    public function test_saving_the_form_as_shown_when_two_roles_hold_the_same_grants(): void
    {
        // The page posts every role's grants. On a fresh install Admin and
        // Supervisor both hold both quality grants; the same value under two
        // roles is normal and must not fail validation.
        $this->actingAs($this->admin)->from('/settings/access')->post('/settings/access', [
            'access' => ['Supervisor' => ['orders'], 'Operator' => ['orders']],
            'grants' => [
                'Admin' => InspectionPermissions::ALL,
                'Supervisor' => InspectionPermissions::ALL,
                'Operator' => [],
            ],
        ])->assertRedirect('/settings/access')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertTrue($this->operatorRole()->hasPermissionTo('tab:orders'));
        $this->assertTrue(Role::findByName('Supervisor', 'web')->fresh()->hasPermissionTo(InspectionPermissions::PERFORM));
    }

    public function test_an_unknown_grant_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/settings/access', [
            'access' => [],
            'grants' => ['Operator' => ['manage users']],
        ])->assertSessionHasErrors('grants.Operator.0');

        $this->assertFalse($this->operatorRole()->hasPermissionTo('manage users'));
    }

    public function test_non_admin_cannot_change_grants(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $this->actingAs($supervisor)->post('/settings/access', [
            'access' => [],
            'grants' => ['Operator' => [InspectionPermissions::DISPOSITION]],
        ])->assertForbidden();

        $this->assertFalse($this->operatorRole()->hasPermissionTo(InspectionPermissions::DISPOSITION));
    }
}
