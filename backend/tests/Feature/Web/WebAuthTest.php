<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    // ── Login form ───────────────────────────────────────────────────────────

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('login', false);
    }

    public function test_authenticated_user_is_redirected_from_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect();
    }

    // ── Login action ─────────────────────────────────────────────────────────

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'username' => 'testadmin',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('Admin');

        $response = $this->post('/login', [
            'username' => 'testadmin',
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_login_requires_username_and_password(): void
    {
        $response = $this->post('/login', []);

        $response->assertSessionHasErrors(['username', 'password']);
    }

    public function test_admin_is_redirected_to_admin_dashboard_after_login(): void
    {
        $user = User::factory()->create([
            'username' => 'admin',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('Admin');

        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_supervisor_is_redirected_to_supervisor_dashboard_after_login(): void
    {
        $user = User::factory()->create([
            'username' => 'supervisor',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('Supervisor');

        $response = $this->post('/login', [
            'username' => 'supervisor',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('supervisor.dashboard'));
    }

    public function test_operator_is_redirected_to_select_line_after_login(): void
    {
        $user = User::factory()->create([
            'username' => 'operator',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('Operator');

        $response = $this->post('/login', [
            'username' => 'operator',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('operator.select-line'));
    }

    // ── Logout ───────────────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }

    // ── Password change ──────────────────────────────────────────────────────

    public function test_user_can_change_password_via_settings(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($user)->post('/settings/change-password', [
            'current_password'          => 'oldpassword',
            'password'                  => 'newpassword123',
            'password_confirmation'     => 'newpassword123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($user)->post('/settings/change-password', [
            'current_password'      => 'wrongpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors(['current_password']);
    }

    public function test_password_change_requires_confirmation_match(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($user)->post('/settings/change-password', [
            'current_password'      => 'oldpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertSessionHasErrors(['password']);
    }
}
