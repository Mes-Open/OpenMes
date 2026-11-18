<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A guest who opens a link is sent to the login form; after signing in they
 * belong on that link, not on their dashboard.
 */
class LoginIntendedRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['password' => Hash::make('password123')] + $attributes);
        $user->assignRole($role);

        return $user;
    }

    private function completeOnboarding(): void
    {
        DB::table('system_settings')->where('key', 'onboarding_completed')->update(['value' => json_encode(true)]);
    }

    public function test_admin_returns_to_the_page_that_asked_for_the_login(): void
    {
        $this->completeOnboarding();
        $admin = $this->user('Admin');

        $this->get('/settings/api-tokens')->assertRedirect(route('login'));

        $this->post('/login', ['username' => $admin->username, 'password' => 'password123'])
            ->assertRedirect('/settings/api-tokens');
    }

    public function test_supervisor_returns_to_the_page_that_asked_for_the_login(): void
    {
        $supervisor = $this->user('Supervisor');

        $this->get('/supervisor/issues')->assertRedirect(route('login'));

        $this->post('/login', ['username' => $supervisor->username, 'password' => 'password123'])
            ->assertRedirect('/supervisor/issues');
    }

    public function test_without_a_remembered_page_the_role_dashboard_is_used(): void
    {
        $supervisor = $this->user('Supervisor');

        $this->post('/login', ['username' => $supervisor->username, 'password' => 'password123'])
            ->assertRedirect(route('supervisor.dashboard'));
    }

    public function test_a_page_the_role_may_not_open_is_still_refused_after_login(): void
    {
        $operator = $this->user('Operator');

        $this->get('/settings/api-tokens')->assertRedirect(route('login'));

        $this->post('/login', ['username' => $operator->username, 'password' => 'password123'])
            ->assertRedirect('/settings/api-tokens');
        $this->get('/settings/api-tokens')->assertForbidden();
    }

    public function test_pending_onboarding_still_comes_before_the_remembered_page(): void
    {
        DB::table('system_settings')->where('key', 'onboarding_completed')->update(['value' => json_encode(false)]);
        $admin = $this->user('Admin');

        $this->get('/settings/api-tokens');

        $this->post('/login', ['username' => $admin->username, 'password' => 'password123'])
            ->assertRedirect(route('onboarding.index'));
    }

    public function test_two_factor_login_returns_to_the_remembered_page(): void
    {
        $supervisor = $this->user('Supervisor', [
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode([Hash::make('recover-me')])),
        ]);

        $this->get('/supervisor/issues')->assertRedirect(route('login'));
        $this->post('/login', ['username' => $supervisor->username, 'password' => 'password123'])
            ->assertRedirect(route('two-factor.challenge'));

        $this->post('/2fa/challenge', ['recovery_code' => 'recover-me'])
            ->assertRedirect('/supervisor/issues');
        $this->assertAuthenticatedAs($supervisor);
    }
}
