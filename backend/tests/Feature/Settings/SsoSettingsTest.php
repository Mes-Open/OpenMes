<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Support\SsoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Admin → Settings → Single Sign-On (#123). */
class SsoSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const ISSUER = 'http://keycloak.plant.local:8080/realms/factory';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        Http::fake([
            self::ISSUER.'/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER.'/auth',
                'token_endpoint' => self::ISSUER.'/token',
                'jwks_uri' => self::ISSUER.'/certs',
            ]),
            '*' => Http::response('', 404),
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'provider_name' => 'Plant Keycloak',
            'issuer' => self::ISSUER.'/',
            'client_id' => 'openmes',
            'client_secret' => 's3cret-value',
            'scopes' => 'openid profile email',
            'username_claim' => 'preferred_username',
            'auto_provision' => true,
            'default_role' => 'Operator',
            'link_by_email' => true,
            'allowed_domains' => '@Company.com; plant.company.com',
        ], $overrides);
    }

    public function test_admin_sees_the_settings_page_with_the_redirect_uri(): void
    {
        $this->actingAs($this->admin())
            ->get('/settings/sso')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Sso')
                ->where('callbackUrl', route('sso.callback'))
                ->where('settings.has_client_secret', false)
                ->where('roles', ['Operator', 'Supervisor']));
    }

    public function test_admin_saves_the_configuration_and_the_secret_is_stored_encrypted(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/sso', $this->payload())
            ->assertRedirect(route('settings.sso'))
            ->assertSessionHas('success');

        $settings = SsoSettings::all();
        $this->assertTrue(SsoSettings::enabled());
        $this->assertSame(self::ISSUER, $settings['issuer']);
        $this->assertSame('company.com, plant.company.com', $settings['allowed_domains']);
        $this->assertSame('s3cret-value', SsoSettings::clientSecret());

        $raw = DB::table('system_settings')->where('key', SsoSettings::SETTING_KEY)->value('value');
        $this->assertStringNotContainsString('s3cret-value', $raw);
        $this->assertSame('s3cret-value', Crypt::decryptString(json_decode($raw, true)['client_secret']));
    }

    public function test_the_secret_is_never_sent_to_the_browser(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/settings/sso', $this->payload());

        $this->actingAs($admin)->get('/settings/sso')
            ->assertInertia(fn ($page) => $page
                ->where('settings.has_client_secret', true)
                ->missing('settings.client_secret'))
            ->assertDontSee('s3cret-value');
    }

    public function test_blank_secret_keeps_the_stored_one_and_clear_removes_it(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/settings/sso', $this->payload());

        $this->actingAs($admin)->post('/settings/sso', $this->payload(['client_secret' => '']));
        $this->assertSame('s3cret-value', SsoSettings::clientSecret());

        $this->actingAs($admin)->post('/settings/sso', $this->payload(['client_secret' => '', 'clear_client_secret' => true]));
        $this->assertNull(SsoSettings::clientSecret());
    }

    public function test_the_secret_is_left_out_of_the_configuration_export(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/settings/sso', $this->payload());

        $export = $this->actingAs($admin)->get('/settings/export')->assertOk()->json();

        $this->assertArrayNotHasKey(SsoSettings::SETTING_KEY, $export['system_settings']);
    }

    public function test_validation_errors(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/settings/sso', $this->payload([
                'issuer' => 'not a url',
                'client_id' => '',
                'scopes' => 'profile email',
                'username_claim' => 'bad claim!',
                'default_role' => 'Admin',
                'allowed_domains' => 'company.com, not_a domain!',
                'provider_name' => '',
            ]))
            ->assertSessionHasErrors(['issuer', 'client_id', 'scopes', 'username_claim', 'default_role', 'allowed_domains', 'provider_name']);

        $this->assertFalse(SsoSettings::enabled());
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/sso', $this->payload(['default_role' => 'Nobody']))
            ->assertSessionHasErrors('default_role');
    }

    public function test_enabling_checks_that_the_issuer_answers(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/sso', $this->payload(['issuer' => 'https://wrong.plant.local/realms/x']))
            ->assertSessionHasErrors(['issuer' => 'Could not load the OpenID Connect configuration from this issuer.']);

        $this->assertFalse(SsoSettings::enabled());
    }

    public function test_a_disabled_configuration_can_be_saved_without_an_issuer(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/sso', $this->payload(['enabled' => false, 'issuer' => '', 'client_id' => '']))
            ->assertSessionHasNoErrors();

        $this->assertFalse(SsoSettings::enabled());
        Http::assertNothingSent();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/settings/sso')->assertRedirect(route('login'));
        $this->post('/settings/sso', $this->payload())->assertRedirect(route('login'));
        $this->assertFalse(SsoSettings::enabled());
    }

    public function test_non_admins_are_forbidden(): void
    {
        foreach (['Supervisor', 'Operator'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)->get('/settings/sso')->assertForbidden();
            $this->actingAs($user)->post('/settings/sso', $this->payload())->assertForbidden();
        }

        $this->assertFalse(SsoSettings::enabled());
    }

    public function test_an_imported_file_cannot_change_the_sso_configuration(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/settings/sso', $this->payload());
        $before = DB::table('system_settings')->where('key', SsoSettings::SETTING_KEY)->value('value');

        app(\App\Services\Settings\ConfigurationImporter::class)->import([
            'system_settings' => [
                SsoSettings::SETTING_KEY => json_encode(['enabled' => true, 'issuer' => 'https://evil.example.test', 'client_id' => 'x']),
            ],
        ]);

        $this->assertSame($before, DB::table('system_settings')->where('key', SsoSettings::SETTING_KEY)->value('value'));
        $this->assertSame(self::ISSUER, SsoSettings::all()['issuer']);
    }
}
