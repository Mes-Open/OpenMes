<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Changing a password has to take the old one's access with it.
 *
 * Found while checking whether an administrator had a working alternative to
 * the forced password change. They did not: nothing revoked API tokens, and
 * AuthenticateSession was not in the web group, so a password reset after a
 * compromise left the attacker's session and token working. The defender does
 * everything right, considers the incident closed, and has changed nothing.
 *
 * This is the more dangerous half of the pair, because unlike the forced-change
 * bypass there was no procedure that worked around it.
 */
class PasswordChangeRevokesAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('Admin', 'web');

        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
        $user->assignRole('Admin');

        return $user;
    }

    public function test_changing_your_own_password_retires_your_api_tokens(): void
    {
        $user = $this->admin();
        $user->createToken('stolen', ['*']);

        $this->assertSame(1, $user->tokens()->count());

        $this->actingAs($user)->post('/settings/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ])->assertRedirect();

        $this->assertSame(
            0,
            $user->tokens()->count(),
            'A token issued against the old password must not outlive it.',
        );
    }

    public function test_an_administrator_setting_a_password_retires_that_users_tokens(): void
    {
        // The phishing case from the report: IT spots the compromise and resets
        // the victim's password. If the attacker's token survives, the reset
        // accomplished nothing.
        $admin = $this->admin();
        $victim = User::factory()->create(['password' => Hash::make('Compromised123!')]);
        $victim->assignRole('Admin');
        $victim->createToken('attackers-token', ['*']);

        $this->actingAs($admin)->put("/admin/users/{$victim->id}", [
            'name' => $victim->name,
            'username' => $victim->username,
            'email' => $victim->email,
            'account_type' => 'user',
            'role' => 'Admin',
            'password' => 'ResetByAdmin123!',
            'password_confirmation' => 'ResetByAdmin123!',
        ]);

        $this->assertSame(
            0,
            $victim->tokens()->count(),
            'Resetting a compromised account must cut off whoever already holds a token.',
        );
    }

    public function test_an_administrator_who_changes_nothing_does_not_retire_tokens(): void
    {
        // Editing a name or an email is not a security event; logging every
        // integration out because somebody fixed a typo would be its own bug.
        $admin = $this->admin();
        $other = User::factory()->create();
        $other->assignRole('Admin');
        $other->createToken('integration', ['*']);

        $this->actingAs($admin)->put("/admin/users/{$other->id}", [
            'name' => 'Renamed Person',
            'username' => $other->username,
            'email' => $other->email,
            'account_type' => 'user',
            'role' => 'Admin',
        ]);

        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_a_token_issued_while_a_change_is_owed_cannot_do_anything_else(): void
    {
        // Previously login handed out a '*' token regardless of the flag and
        // merely reported it in the body, leaving enforcement to the client's
        // good manners.
        $user = $this->admin();
        $user->update(['force_password_change' => true]);

        $result = app(\App\Services\Auth\AuthService::class)->login($user->username, 'OldPassword123!');

        $abilities = $user->tokens()->latest('id')->first()->abilities;

        $this->assertSame(['password:change'], $abilities);
        $this->assertNotContains('*', $abilities);
        $this->assertTrue($result['force_password_change']);
    }

    public function test_a_normal_login_still_gets_a_full_token(): void
    {
        $user = $this->admin();

        app(\App\Services\Auth\AuthService::class)->login($user->username, 'OldPassword123!');

        $this->assertSame(['*'], $user->tokens()->latest('id')->first()->abilities);
    }

    public function test_the_api_change_password_endpoint_also_retires_tokens(): void
    {
        $user = $this->admin();
        $user->createToken('stolen', ['*']);

        app(\App\Services\Auth\AuthService::class)
            ->changePassword($user, 'OldPassword123!', 'BrandNewPassword123!');

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }
}
