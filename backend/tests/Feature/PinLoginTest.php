<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['username' => 'admin']);
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create([
            'username' => 'operator1',
            'password' => Hash::make('Secret123!'),
            'pin' => Hash::make('1234'),
        ]);
        $this->operator->assignRole('Operator');
    }

    private function enablePinLogin(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'pin_login_enabled'],
            ['value' => json_encode(true)]
        );
    }

    public function test_pin_login_rejected_when_disabled(): void
    {
        $response = $this->post('/login/pin', [
            'username' => 'operator1',
            'pin' => '1234',
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
    }

    public function test_pin_login_succeeds_when_enabled(): void
    {
        $this->enablePinLogin();

        $response = $this->post('/login/pin', [
            'username' => 'operator1',
            'pin' => '1234',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->operator);
    }

    public function test_pin_login_fails_with_wrong_pin(): void
    {
        $this->enablePinLogin();

        $response = $this->post('/login/pin', [
            'username' => 'operator1',
            'pin' => '9999',
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
    }

    public function test_pin_login_fails_for_user_without_pin(): void
    {
        $this->enablePinLogin();

        $noPinUser = User::factory()->create([
            'username' => 'nopin',
            'pin' => null,
        ]);
        $noPinUser->assignRole('Operator');

        $response = $this->post('/login/pin', [
            'username' => 'nopin',
            'pin' => '1234',
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
    }

    public function test_pin_validation_rejects_short_pin(): void
    {
        $this->enablePinLogin();

        $response = $this->post('/login/pin', [
            'username' => 'operator1',
            'pin' => '12',
        ]);

        $response->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_user_can_set_pin_in_settings(): void
    {
        $this->enablePinLogin();

        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('MyPass123!'),
            'pin' => null,
        ]);
        $user->assignRole('Operator');

        $response = $this->actingAs($user)->post('/settings/pin', [
            'current_password' => 'MyPass123!',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ]);

        $response->assertRedirect(route('settings.index'));
        $user->refresh();
        $this->assertTrue(Hash::check('5678', $user->pin));
    }

    public function test_user_cannot_set_pin_with_wrong_password(): void
    {
        $this->enablePinLogin();

        $user = User::factory()->create([
            'username' => 'testuser2',
            'password' => Hash::make('MyPass123!'),
        ]);
        $user->assignRole('Operator');

        $response = $this->actingAs($user)->post('/settings/pin', [
            'current_password' => 'WrongPassword',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_user_can_remove_pin(): void
    {
        $this->enablePinLogin();

        $response = $this->actingAs($this->operator)->delete('/settings/pin', [
            'current_password' => 'Secret123!',
        ]);

        $response->assertRedirect(route('settings.index'));
        $this->operator->refresh();
        $this->assertNull($this->operator->pin);
    }

    public function test_system_settings_shows_pin_option(): void
    {
        $response = $this->actingAs($this->admin)->get('/settings/system');

        $response->assertStatus(200);
        $response->assertSee('Enable PIN login');
    }

    public function test_admin_can_enable_pin_login(): void
    {
        $response = $this->actingAs($this->admin)->post('/settings/system', [
            'production_period' => 'none',
            'workflow_mode' => 'status',
            'pin_login_enabled' => '1',
        ]);

        $response->assertRedirect(route('settings.system'));

        $value = DB::table('system_settings')->where('key', 'pin_login_enabled')->value('value');
        $this->assertTrue(json_decode($value, true));
    }

    public function test_login_page_shows_pin_tab_when_enabled(): void
    {
        $this->enablePinLogin();

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Quick PIN');
    }

    public function test_login_page_hides_pin_tab_when_disabled(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('Quick PIN');
    }
}
