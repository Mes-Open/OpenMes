<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The account pages (Profile, Change Password, PIN, API Tokens) answered in
 * English whatever the UI language: their messages were plain strings, never
 * passed through __(), and had no entry in lang/pl.json.
 */
class AccountMessagesTranslatedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->user = User::factory()->create(['password' => Hash::make('MyPass123!')]);
        $this->user->assignRole('Admin');

        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('pl')]);
    }

    private function enablePinLogin(bool $on = true): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'pin_login_enabled'], ['value' => json_encode($on)]);
    }

    public function test_profile_update_message_is_translated(): void
    {
        $this->actingAs($this->user)
            ->post('/settings/profile', ['name' => 'Anna Nowak', 'email' => 'anna@example.test'])
            ->assertSessionHas('success', 'Profil został zaktualizowany.');
    }

    public function test_profile_name_rule_message_is_translated(): void
    {
        $this->actingAs($this->user)
            ->post('/settings/profile', ['name' => 'Anna <b>', 'email' => 'anna@example.test'])
            ->assertSessionHasErrors(['name' => 'Imię i nazwisko może zawierać tylko litery, cyfry, spacje, kropki, myślniki i apostrofy.']);
    }

    public function test_wrong_current_password_message_is_translated(): void
    {
        $this->actingAs($this->user)
            ->post('/settings/change-password', [
                'current_password' => 'nope',
                'password' => 'NewPass-456!',
                'password_confirmation' => 'NewPass-456!',
            ])
            ->assertSessionHasErrors(['current_password' => 'Aktualne hasło jest nieprawidłowe.']);
    }

    public function test_pin_messages_are_translated(): void
    {
        $this->enablePinLogin();

        $this->actingAs($this->user)
            ->post('/settings/pin', ['current_password' => 'MyPass123!', 'pin' => '4821', 'pin_confirmation' => '4821'])
            ->assertSessionHasErrors(['pin' => 'PIN musi mieć dokładnie 6 cyfr.']);

        $this->actingAs($this->user)
            ->post('/settings/pin', ['current_password' => 'MyPass123!', 'pin' => '482193', 'pin_confirmation' => '482194'])
            ->assertSessionHasErrors(['pin' => 'Potwierdzenie PIN-u nie jest zgodne.']);

        $this->actingAs($this->user)
            ->post('/settings/pin', ['current_password' => 'MyPass123!', 'pin' => '482193', 'pin_confirmation' => '482193'])
            ->assertSessionHas('success', 'PIN został ustawiony. Możesz go teraz używać do logowania.');

        $this->actingAs($this->user)
            ->delete('/settings/pin', ['current_password' => 'MyPass123!'])
            ->assertSessionHas('success', 'PIN został usunięty.');

        $this->enablePinLogin(false);

        $this->actingAs($this->user)
            ->get('/settings/pin')
            ->assertSessionHas('error', 'Logowanie PIN-em nie zostało włączone przez administratora.');
    }

    public function test_token_revoked_message_is_translated(): void
    {
        $token = $this->user->createToken('ERP')->accessToken;

        $this->actingAs($this->user)
            ->delete('/settings/api-tokens/'.$token->id)
            ->assertSessionHas('success', 'Token został unieważniony.');
    }
}
