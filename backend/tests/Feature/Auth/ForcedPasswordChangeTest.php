<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A forced password change has to hold for longer than one redirect.
 *
 * Reported privately by Maxwell Jones in September 2026: an administrator
 * ticks "require password change at next login", the user is taken to the form
 * — and simply types another address. The session was already authenticated,
 * so nothing stopped them. The control an administrator reaches for after a
 * suspected compromise did not work.
 *
 * These tests walk his report: log in owing a change, then try to be somewhere
 * else.
 */
class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function userOwingAChange(string $role = 'Admin'): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
            'force_password_change' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_an_admin_owing_a_password_change_cannot_reach_the_admin_pages(): void
    {
        // The exact escalation from the report: the second administrator was
        // able to open Users & Accounts and delete the first one.
        $user = $this->userOwingAChange();

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertRedirect(route('settings.change-password'));
    }

    public function test_the_block_holds_across_the_whole_application(): void
    {
        $user = $this->userOwingAChange();

        foreach (['/admin/users', '/settings/system', '/admin/dashboard'] as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertRedirect(route('settings.change-password'), "{$path} was reachable.");
        }
    }

    public function test_an_operator_owing_a_change_cannot_record_production(): void
    {
        // The second half of the report: the operator submits an issue without
        // ever changing the password, and it is accepted.
        $user = $this->userOwingAChange('Operator');

        $this->actingAs($user)
            ->post('/operator/issue', ['work_order_id' => 1, 'issue_type_id' => 1, 'title' => 'x'])
            ->assertRedirect(route('settings.change-password'));
    }

    public function test_the_user_can_still_reach_the_form_and_the_way_out(): void
    {
        // Enforcement must not become a lockout: the page that collects the new
        // password, and logout, stay reachable.
        $user = $this->userOwingAChange();

        $this->actingAs($user)->get('/settings/change-password')->assertOk();
        $this->actingAs($user)->post('/logout')->assertRedirect();
    }

    public function test_changing_the_password_lifts_the_block(): void
    {
        $user = $this->userOwingAChange();

        $this->actingAs($user)->post('/settings/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ])->assertRedirect();

        $this->assertFalse($user->fresh()->force_password_change);

        $this->actingAs($user->fresh())->get('/admin/users')->assertOk();
    }

    public function test_a_user_who_owes_nothing_is_not_impeded(): void
    {
        Role::findOrCreate('Admin', 'web');
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole('Admin');

        $this->actingAs($user)->get('/admin/users')->assertOk();
    }

    public function test_an_api_client_is_refused_in_terms_it_can_read(): void
    {
        // A redirect to an HTML form is useless to the mobile app. It gets a
        // 403 carrying the reason instead.
        $user = $this->userOwingAChange();

        $this->actingAs($user)
            ->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('force_password_change', true);
    }
}
